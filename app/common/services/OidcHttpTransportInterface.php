<?php

declare(strict_types=1);

namespace common\services;

/**
 * Минимальный HTTP-контракт для OIDC discovery, JWKS и token endpoint.
 *
 * Интерфейс отделяет протокольную логику от cURL и позволяет unit-тестам
 * использовать transport без сетевых запросов.
 */
interface OidcHttpTransportInterface
{
    /**
     * Выполняет GET и возвращает JSON-объект.
     *
     * @return array<string, mixed>
     */
    public function getJson(string $url, int $timeoutSeconds): array;

    /**
     * Отправляет application/x-www-form-urlencoded POST и возвращает JSON-объект.
     *
     * @param array<string, string> $formData
     * @return array<string, mixed>
     */
    public function postForm(
        string $url,
        #[\SensitiveParameter] array $formData,
        int $timeoutSeconds,
    ): array;
}
