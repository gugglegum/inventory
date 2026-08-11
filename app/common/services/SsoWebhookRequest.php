<?php

declare(strict_types=1);

namespace common\services;

/**
 * Проверенный и разобранный запрос от Pyrda SSO.
 */
final readonly class SsoWebhookRequest
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $rawPayload,
        public array $payload,
    ) {
    }
}
