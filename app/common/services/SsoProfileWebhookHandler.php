<?php

declare(strict_types=1);

namespace common\services;

use JsonException;
use Yii;
use yii\db\IntegrityException;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;

/**
 * Идемпотентно применяет latest-state profile webhook к связанному пользователю.
 */
final class SsoProfileWebhookHandler
{
    public function __construct(
        private readonly SsoWebhookUserFinder $userFinder = new SsoWebhookUserFinder(),
    ) {
    }

    public function handle(SsoWebhookRequest $request): void
    {
        $userPayload = $request->payload['user'] ?? null;
        if (!is_array($userPayload) || array_is_list($userPayload)) {
            throw new BadRequestHttpException('Missing webhook user payload.');
        }

        $subject = $this->requiredString($userPayload, 'sub', 255);
        $name = $this->requiredString($userPayload, 'name', 1_020);
        $email = $this->requiredString($userPayload, 'email', 255);
        $preferredUsername = $this->requiredString($userPayload, 'preferred_username', 255);
        $profileVersion = $this->optionalPositiveInt($userPayload, 'profile_version');

        if (mb_strlen($name, 'UTF-8') > 255) {
            throw new BadRequestHttpException('Invalid webhook display name.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BadRequestHttpException('Invalid webhook email.');
        }

        try {
            Yii::$app->db->transaction(function () use (
                $email,
                $name,
                $preferredUsername,
                $profileVersion,
                $request,
                $subject,
            ): void {
                if (!$this->recordDelivery($request, $subject)) {
                    return;
                }

                $user = $this->userFinder->findForUpdate($subject);
                if ($user === null || $this->isStale($user->ssoProfileVersion, $profileVersion)) {
                    return;
                }

                $claims = $this->decodeClaims($user->ssoClaims);
                $claims['sub'] = $subject;
                $claims['name'] = $name;
                $claims['email'] = $email;
                $claims['preferred_username'] = $preferredUsername;

                $attributes = [
                    'username' => $preferredUsername,
                    'email' => $email,
                    'updated' => time(),
                ];
                if ($profileVersion !== null) {
                    $claims['profile_version'] = $profileVersion;
                    $attributes['ssoProfileVersion'] = $profileVersion;
                }

                try {
                    $attributes['ssoClaims'] = json_encode(
                        $claims,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                } catch (JsonException $exception) {
                    throw new BadRequestHttpException('Invalid webhook profile payload.', previous: $exception);
                }

                $user->updateAttributes($attributes);
            });
        } catch (IntegrityException $exception) {
            throw new ConflictHttpException(
                'SSO profile conflicts with another Stockhub user.',
                previous: $exception
            );
        }
    }

    private function recordDelivery(SsoWebhookRequest $request, string $subject): bool
    {
        $now = time();
        $sql = <<<'SQL'
INSERT IGNORE INTO {{%sso_profile_webhook_delivery}}
    ([[eventId]], [[eventType]], [[ssoSubject]], [[payload]], [[processedAt]], [[created]])
VALUES
    (:eventId, :eventType, :subject, :payload, :processedAt, :created)
SQL;

        return Yii::$app->db->createCommand($sql, [
            ':eventId' => $request->eventId,
            ':eventType' => $request->eventType,
            ':subject' => $subject,
            ':payload' => $request->rawPayload,
            ':processedAt' => $now,
            ':created' => $now,
        ])->execute() === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeClaims(?string $claimsJson): array
    {
        if ($claimsJson === null || $claimsJson === '') {
            return [];
        }

        try {
            $claims = json_decode($claimsJson, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($claims) && !array_is_list($claims) ? $claims : [];
    }

    private function isStale(mixed $storedVersion, ?int $incomingVersion): bool
    {
        if ($incomingVersion === null) {
            return $storedVersion !== null;
        }

        return $storedVersion !== null && (int) $storedVersion >= $incomingVersion;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key, int $maxLength): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength) {
            throw new BadRequestHttpException('Incomplete webhook user payload.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function optionalPositiveInt(array $payload, string $key): ?int
    {
        if (!array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new BadRequestHttpException('Invalid webhook profile version.');
    }
}
