<?php

declare(strict_types=1);

namespace common\services;

use Yii;
use yii\web\BadRequestHttpException;

/**
 * Применяет отзыв/восстановление SSO-доступа и глобальный отзыв сессий.
 */
final class SsoAccessWebhookHandler
{
    public const string EVENT_ACCESS_REVOKED = 'user.access.revoked';

    public const string EVENT_ACCESS_RESTORED = 'user.access.restored';

    public const string EVENT_SESSIONS_REVOKED = 'user.sessions.revoked';

    /** @var non-empty-list<string> */
    public const array SUPPORTED_EVENTS = [
        self::EVENT_ACCESS_REVOKED,
        self::EVENT_ACCESS_RESTORED,
        self::EVENT_SESSIONS_REVOKED,
    ];

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

        $subject = $this->requiredString($userPayload, 'sub', 255, 'Incomplete webhook access payload.');
        $reason = $this->requiredString($request->payload, 'reason', 255, 'Incomplete webhook access payload.');
        $accessVersion = null;
        $sessionVersion = null;

        if ($request->eventType === self::EVENT_SESSIONS_REVOKED) {
            $sessionVersion = $this->requiredPositiveInt(
                $userPayload,
                'session_version',
                'Invalid webhook session version.'
            );
        } else {
            $accessVersion = $this->requiredPositiveInt(
                $userPayload,
                'access_version',
                'Invalid webhook access version.'
            );
        }

        Yii::$app->db->transaction(function () use (
            $accessVersion,
            $reason,
            $request,
            $sessionVersion,
            $subject,
        ): void {
            if (
                !$this->recordDelivery(
                    $request,
                    $subject,
                    $reason,
                    $accessVersion,
                    $sessionVersion
                )
            ) {
                return;
            }

            $user = $this->userFinder->findForUpdate($subject);
            if ($user === null) {
                return;
            }

            if ($request->eventType === self::EVENT_ACCESS_RESTORED) {
                if ($this->isStale($user->ssoAccessVersion, $accessVersion)) {
                    return;
                }
                $user->updateAttributes([
                    'ssoDisabledAt' => null,
                    'ssoAccessVersion' => $accessVersion,
                    'updated' => time(),
                ]);

                return;
            }

            if ($request->eventType === self::EVENT_ACCESS_REVOKED) {
                if ($this->isStale($user->ssoAccessVersion, $accessVersion)) {
                    return;
                }
                $user->generateAuthKey();
                $user->updateAttributes([
                    'authKey' => $user->authKey,
                    'ssoDisabledAt' => time(),
                    'ssoAccessVersion' => $accessVersion,
                    'updated' => time(),
                ]);

                return;
            }

            if ($this->isStale($user->ssoSessionVersion, $sessionVersion)) {
                return;
            }
            $user->generateAuthKey();
            $user->updateAttributes([
                'authKey' => $user->authKey,
                'ssoSessionVersion' => $sessionVersion,
                'updated' => time(),
            ]);
        });
    }

    private function recordDelivery(
        SsoWebhookRequest $request,
        string $subject,
        string $reason,
        ?int $accessVersion,
        ?int $sessionVersion,
    ): bool {
        $now = time();
        $sql = <<<'SQL'
INSERT IGNORE INTO {{%sso_access_webhook_delivery}}
    ([[eventId]], [[eventType]], [[ssoSubject]], [[accessVersion]], [[sessionVersion]],
     [[reason]], [[payload]], [[processedAt]], [[created]])
VALUES
    (:eventId, :eventType, :subject, :accessVersion, :sessionVersion,
     :reason, :payload, :processedAt, :created)
SQL;

        return Yii::$app->db->createCommand($sql, [
            ':eventId' => $request->eventId,
            ':eventType' => $request->eventType,
            ':subject' => $subject,
            ':accessVersion' => $accessVersion,
            ':sessionVersion' => $sessionVersion,
            ':reason' => $reason,
            ':payload' => $request->rawPayload,
            ':processedAt' => $now,
            ':created' => $now,
        ])->execute() === 1;
    }

    private function isStale(mixed $storedVersion, ?int $incomingVersion): bool
    {
        return $incomingVersion === null
            || ($storedVersion !== null && (int) $storedVersion >= $incomingVersion);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key, int $maxLength, string $message): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $maxLength) {
            throw new BadRequestHttpException($message);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredPositiveInt(array $payload, string $key, string $message): int
    {
        $value = $payload[$key] ?? null;
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new BadRequestHttpException($message);
    }
}
