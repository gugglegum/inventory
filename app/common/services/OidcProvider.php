<?php

declare(strict_types=1);

namespace common\services;

/**
 * Получает OIDC metadata/JWKS и обменивает authorization code на токены.
 */
final class OidcProvider
{
    private OidcConfiguration $configuration;
    private OidcHttpTransportInterface $transport;

    /** @var array<string, mixed>|null */
    private ?array $discovery = null;

    /** @var array<string, mixed>|null */
    private ?array $jwks = null;

    /**
     * @param array<string, mixed>|null $config При null используется Yii::$app->params['oidc'].
     */
    public function __construct(
        #[\SensitiveParameter] ?array $config = null,
        ?OidcHttpTransportInterface $transport = null,
    ) {
        $this->configuration = OidcConfiguration::fromArray($config);
        $this->transport = $transport ?? new CurlOidcHttpTransport();
    }

    /**
     * Возвращает проверенный discovery document.
     *
     * @return array<string, mixed>
     */
    public function discovery(): array
    {
        if ($this->discovery !== null) {
            return $this->discovery;
        }

        $url = $this->configuration->issuer . '/.well-known/openid-configuration';
        $metadata = $this->transport->getJson($url, $this->configuration->httpTimeout);

        $metadataIssuer = $this->requiredString($metadata, 'issuer');
        if ($metadataIssuer !== $this->configuration->issuer) {
            throw new OidcException('OIDC discovery issuer does not match configured issuer.');
        }

        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
            $this->validateEndpoint($this->requiredString($metadata, $field), $field);
        }

        $this->discovery = $metadata;

        return $metadata;
    }

    /**
     * Возвращает endpoint начала Authorization Code flow.
     */
    public function authorizationEndpoint(): string
    {
        return $this->requiredString($this->discovery(), 'authorization_endpoint');
    }

    /**
     * Возвращает проверенные несекретные параметры OIDC-клиента для authorize request.
     */
    public function clientId(): string
    {
        return $this->configuration->clientId;
    }

    public function redirectUri(): string
    {
        return $this->configuration->redirectUri;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->configuration->scopes;
    }

    /**
     * Обменивает authorization code с PKCE verifier на token response.
     *
     * @return array<string, mixed>
     */
    public function exchangeCode(
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] string $codeVerifier,
    ): array {
        if ($code === '') {
            throw new OidcException('OIDC authorization code must not be empty.');
        }

        if (
            strlen($codeVerifier) < 43
            || strlen($codeVerifier) > 128
            || preg_match('/^[A-Za-z0-9._~-]+$/D', $codeVerifier) !== 1
        ) {
            throw new OidcException('OIDC PKCE code verifier is invalid.');
        }

        $tokenEndpoint = $this->requiredString($this->discovery(), 'token_endpoint');

        return $this->transport->postForm(
            $tokenEndpoint,
            [
                'grant_type' => 'authorization_code',
                'client_id' => $this->configuration->clientId,
                'client_secret' => $this->configuration->clientSecret,
                'redirect_uri' => $this->configuration->redirectUri,
                'code' => $code,
                'code_verifier' => $codeVerifier,
            ],
            $this->configuration->httpTimeout,
        );
    }

    /**
     * Возвращает JSON Web Key Set провайдера.
     *
     * @return array<string, mixed>
     */
    public function jwks(): array
    {
        if ($this->jwks !== null) {
            return $this->jwks;
        }

        $jwksUri = $this->requiredString($this->discovery(), 'jwks_uri');
        $jwks = $this->transport->getJson($jwksUri, $this->configuration->httpTimeout);

        if (!isset($jwks['keys']) || !is_array($jwks['keys']) || $jwks['keys'] === []) {
            throw new OidcException('OIDC JWKS does not contain signing keys.');
        }

        $this->jwks = $jwks;

        return $jwks;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function requiredString(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new OidcException("OIDC metadata field {$key} is missing.");
        }

        return $value;
    }

    /**
     * Запрещает discovery endpoints с нестандартными схемами, credentials или fragment.
     */
    private function validateEndpoint(string $url, string $field): void
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
            throw new OidcException("OIDC metadata field {$field} must be an absolute HTTP(S) URL.");
        }
    }
}
