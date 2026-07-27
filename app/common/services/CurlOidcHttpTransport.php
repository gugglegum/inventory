<?php

declare(strict_types=1);

namespace common\services;

use CurlHandle;
use JsonException;

/**
 * HTTP transport OIDC-клиента на ext-curl.
 *
 * Redirects запрещены, URL ограничены HTTP(S), а штатная TLS-проверка cURL
 * включена явно. Ошибки не включают тело ответа или отправленные form-поля.
 */
final class CurlOidcHttpTransport implements OidcHttpTransportInterface
{
    private const USER_AGENT = 'Stockhub OIDC client';

    /**
     * @inheritDoc
     */
    public function getJson(string $url, int $timeoutSeconds): array
    {
        return $this->requestJson($url, $timeoutSeconds);
    }

    /**
     * @inheritDoc
     */
    public function postForm(
        string $url,
        #[\SensitiveParameter] array $formData,
        int $timeoutSeconds,
    ): array {
        return $this->requestJson($url, $timeoutSeconds, $formData);
    }

    /**
     * @param array<string, string>|null $formData
     * @return array<string, mixed>
     */
    private function requestJson(
        string $url,
        int $timeoutSeconds,
        #[\SensitiveParameter] ?array $formData = null,
    ): array {
        $this->validateUrl($url);

        if ($timeoutSeconds < 1) {
            throw new OidcException('OIDC HTTP timeout must be at least one second.');
        }

        $handle = curl_init($url);
        if (!$handle instanceof CurlHandle) {
            throw new OidcException('OIDC HTTP request could not be initialized.');
        }

        $headers = ['Accept: application/json'];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => self::USER_AGENT,
        ];

        if ($formData !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($formData, '', '&', PHP_QUERY_RFC3986);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;

        try {
            if (!curl_setopt_array($handle, $options)) {
                throw new OidcException('OIDC HTTP request could not be configured.');
            }

            $body = curl_exec($handle);
            if (!is_string($body)) {
                $errorNumber = curl_errno($handle);
                throw new OidcException("OIDC HTTP request failed (cURL error {$errorNumber}).");
            }

            $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if (!is_int($statusCode) || $statusCode < 200 || $statusCode >= 300) {
                $status = is_int($statusCode) && $statusCode > 0 ? (string) $statusCode : 'unknown';
                throw new OidcException("OIDC HTTP request returned status {$status}.");
            }
        } finally {
            curl_close($handle);
        }

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OidcException('OIDC HTTP response is not valid JSON.', previous: $exception);
        }

        if (!is_array($payload)) {
            throw new OidcException('OIDC HTTP response must be a JSON object.');
        }

        return $payload;
    }

    /**
     * Запрещает нестандартные схемы, относительные URL, credentials и fragments.
     */
    private function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (
            !is_array($parts)
            || !is_string($scheme)
            || !in_array(strtolower($scheme), ['http', 'https'], true)
            || !is_string($host)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new OidcException('OIDC endpoint must be an absolute HTTP(S) URL without credentials or fragment.');
        }
    }
}
