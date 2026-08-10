<?php

declare(strict_types=1);

namespace common\services;

use Yii;

/**
 * Проверенная конфигурация OIDC-клиента.
 */
final readonly class OidcConfiguration
{
    /**
     * @param list<string> $scopes
     */
    private function __construct(
        public string $issuer,
        public string $clientId,
        public string $clientSecret,
        public string $redirectUri,
        public array $scopes,
        public int $httpTimeout,
        public int $clockSkewSeconds,
    ) {
    }

    /**
     * Создает конфигурацию из переданного массива или Yii::$app->params['oidc'].
     *
     * @param array<string, mixed>|null $config
     */
    public static function fromArray(#[\SensitiveParameter] ?array $config): self
    {
        if ($config === null) {
            /** @psalm-suppress DocblockTypeContradiction Yii::$app may be absent outside application runtime. */
            if (Yii::$app === null) {
                throw new OidcException('OIDC configuration is unavailable outside a Yii application.');
            }

            $config = Yii::$app->params['oidc'] ?? null;
            if (!is_array($config)) {
                throw new OidcException('Yii application params do not contain OIDC configuration.');
            }
        }

        $issuer = self::canonicalizeIssuer(self::requiredString($config, 'issuer'));
        $clientId = self::requiredString($config, 'clientId');
        $clientSecret = self::requiredString($config, 'clientSecret');
        $redirectUri = self::requiredString($config, 'redirectUri');
        $scopes = self::scopes($config['scopes'] ?? null);
        $httpTimeout = self::nonNegativeInt($config, 'httpTimeout', 1);
        $clockSkewSeconds = self::nonNegativeInt($config, 'clockSkewSeconds', 0);

        self::validateUrl($redirectUri, 'redirectUri', true);

        return new self(
            $issuer,
            $clientId,
            $clientSecret,
            $redirectUri,
            $scopes,
            $httpTimeout,
            $clockSkewSeconds,
        );
    }

    /**
     * Возвращает единственное представление issuer, используемое web-runtime
     * и административной привязкой пользователя.
     */
    public static function canonicalizeIssuer(string $issuer): string
    {
        if (trim($issuer) !== $issuer) {
            throw new OidcException(
                'OIDC configuration field issuer must not contain surrounding whitespace.'
            );
        }

        $issuer = rtrim($issuer, '/');
        self::validateUrl($issuer, 'issuer', false);

        return $issuer;
    }

    /**
     * Не показывает client secret при диагностическом dump объекта.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'issuer' => $this->issuer,
            'clientId' => $this->clientId,
            'clientSecret' => '[redacted]',
            'redirectUri' => $this->redirectUri,
            'scopes' => $this->scopes,
            'httpTimeout' => $this->httpTimeout,
            'clockSkewSeconds' => $this->clockSkewSeconds,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function requiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new OidcException("OIDC configuration field {$key} must be a non-empty string.");
        }

        if (trim($value) !== $value) {
            throw new OidcException(
                "OIDC configuration field {$key} must not contain surrounding whitespace."
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function nonNegativeInt(array $config, string $key, int $minimum): int
    {
        $value = $config[$key] ?? null;
        if (!is_int($value) || $value < $minimum) {
            throw new OidcException("OIDC configuration field {$key} must be an integer of at least {$minimum}.");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function scopes(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            throw new OidcException('OIDC configuration field scopes must be a non-empty string list.');
        }

        $scopes = [];
        foreach ($value as $scope) {
            if (!is_string($scope) || trim($scope) === '') {
                throw new OidcException('OIDC configuration field scopes must be a non-empty string list.');
            }

            if (trim($scope) !== $scope) {
                throw new OidcException(
                    'OIDC configuration field scopes must not contain surrounding whitespace.'
                );
            }

            $scopes[] = $scope;
        }

        $scopes = array_values(array_unique($scopes));
        if (!in_array('openid', $scopes, true)) {
            throw new OidcException('OIDC configuration scopes must contain openid.');
        }

        return $scopes;
    }

    /**
     * Проверяет абсолютный HTTP(S) URL. Redirect URI может содержать query,
     * issuer — нет; fragment запрещен для обоих.
     */
    private static function validateUrl(string $url, string $field, bool $allowQuery): void
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
            || (!$allowQuery && isset($parts['query']))
        ) {
            throw new OidcException("OIDC configuration field {$field} must be a valid HTTP(S) URL.");
        }
    }
}
