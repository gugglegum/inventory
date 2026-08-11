<?php

declare(strict_types=1);

namespace common\services;

use JsonException;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Request;
use yii\web\UnauthorizedHttpException;

/**
 * Проверяет заголовки, timestamp и HMAC входящего webhook до работы с БД.
 */
final class SsoWebhookVerifier
{
    private const int MAX_PAYLOAD_BYTES = 65_536;

    /**
     * @param non-empty-string $configurationKey
     * @param non-empty-list<string> $supportedEvents
     */
    public function verify(
        Request $request,
        string $configurationKey,
        array $supportedEvents,
    ): SsoWebhookRequest {
        $configuration = Yii::$app->params['ssoWebhooks'][$configurationKey] ?? null;
        if (!is_array($configuration)) {
            throw new HttpException(503, 'SSO webhook is not configured.');
        }

        $secret = $configuration['secret'] ?? null;
        if (!is_string($secret) || $secret === '') {
            throw new HttpException(503, 'SSO webhook is not configured.');
        }

        $rawPayload = $request->getRawBody();
        if ($rawPayload === '') {
            throw new BadRequestHttpException('Empty webhook payload.');
        }
        if (strlen($rawPayload) > self::MAX_PAYLOAD_BYTES) {
            throw new BadRequestHttpException('Webhook payload is too large.');
        }

        $eventType = $request->headers->get('X-SSO-Event');
        if (!is_string($eventType) || !in_array($eventType, $supportedEvents, true)) {
            throw new BadRequestHttpException('Unsupported webhook event header.');
        }

        $eventId = $request->headers->get('X-SSO-Delivery');
        if (
            !is_string($eventId)
            || preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',
                $eventId
            ) !== 1
        ) {
            throw new BadRequestHttpException('Invalid webhook delivery id.');
        }

        $timestampHeader = $request->headers->get('X-SSO-Timestamp');
        if (!is_string($timestampHeader) || preg_match('/^[0-9]{1,19}$/D', $timestampHeader) !== 1) {
            throw new UnauthorizedHttpException('Missing webhook timestamp.');
        }

        $timestamp = filter_var($timestampHeader, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $tolerance = $configuration['timestampToleranceSeconds'] ?? 300;
        if (!is_int($timestamp) || !is_int($tolerance) || $tolerance < 0 || abs(time() - $timestamp) > $tolerance) {
            throw new UnauthorizedHttpException('Stale webhook timestamp.');
        }

        $signature = $request->headers->get('X-SSO-Signature');
        if (!is_string($signature) || preg_match('/^sha256=([a-f0-9]{64})$/Di', $signature, $matches) !== 1) {
            throw new UnauthorizedHttpException('Missing webhook signature.');
        }

        $expectedSignature = hash_hmac('sha256', $timestampHeader . '.' . $rawPayload, $secret);
        if (!hash_equals($expectedSignature, strtolower($matches[1]))) {
            throw new UnauthorizedHttpException('Invalid webhook signature.');
        }

        try {
            $payload = json_decode($rawPayload, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BadRequestHttpException('Invalid webhook JSON.', previous: $exception);
        }
        if (!is_array($payload) || $payload === [] || array_is_list($payload)) {
            throw new BadRequestHttpException('Invalid webhook payload.');
        }

        if (($payload['event_id'] ?? null) !== $eventId) {
            throw new BadRequestHttpException('Webhook event id does not match delivery header.');
        }
        if (($payload['event_type'] ?? null) !== $eventType) {
            throw new BadRequestHttpException('Webhook event type does not match event header.');
        }

        return new SsoWebhookRequest($eventId, $eventType, $rawPayload, $payload);
    }
}
