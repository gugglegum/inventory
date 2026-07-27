<?php

declare(strict_types=1);

namespace tests\phpunit\unit;

use common\services\CurlOidcHttpTransport;
use common\services\OidcException;
use common\services\OidcProvider;
use tests\phpunit\TestCase;
use Yii;

/**
 * Unit-тесты discovery, JWKS и Authorization Code exchange OIDC-клиента.
 */
final class OidcProviderTest extends TestCase
{
    private const ISSUER = 'https://sso.example.test';
    private const DISCOVERY_URL = self::ISSUER . '/.well-known/openid-configuration';
    private const AUTHORIZATION_ENDPOINT = self::ISSUER . '/oauth/authorize';
    private const TOKEN_ENDPOINT = self::ISSUER . '/oauth/token';
    private const JWKS_URI = self::ISSUER . '/oauth/jwks';

    /**
     * Provider кэширует discovery, использует найденные endpoints и отправляет
     * confidential-client + PKCE параметры без сетевого обращения.
     */
    public function testProviderUsesDiscoveryForAuthorizationJwksAndCodeExchange(): void
    {
        $transport = new FakeOidcHttpTransport();
        $transport->respondToGet(self::DISCOVERY_URL, $this->discovery());
        $transport->respondToGet(self::JWKS_URI, [
            'keys' => [['kty' => 'RSA', 'kid' => 'signing-key']],
        ]);
        $transport->respondToPost(self::TOKEN_ENDPOINT, [
            'token_type' => 'Bearer',
            'id_token' => 'header.claims.signature',
        ]);

        $provider = new OidcProvider($this->config(), $transport);
        $codeVerifier = str_repeat('v', 43);

        self::assertSame(self::AUTHORIZATION_ENDPOINT, $provider->authorizationEndpoint());
        self::assertSame(
            ['keys' => [['kty' => 'RSA', 'kid' => 'signing-key']]],
            $provider->jwks(),
        );
        self::assertSame(
            [
                'token_type' => 'Bearer',
                'id_token' => 'header.claims.signature',
            ],
            $provider->exchangeCode('one-time-code', $codeVerifier),
        );

        self::assertSame(
            [
                ['url' => self::DISCOVERY_URL, 'timeout' => 7],
                ['url' => self::JWKS_URI, 'timeout' => 7],
            ],
            $transport->getRequests,
        );
        self::assertSame(
            [[
                'url' => self::TOKEN_ENDPOINT,
                'formData' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => 'stockhub-client',
                    'client_secret' => 'client-secret',
                    'redirect_uri' => 'https://stockhub.example.test/auth/sso/callback',
                    'code' => 'one-time-code',
                    'code_verifier' => $codeVerifier,
                ],
                'timeout' => 7,
            ]],
            $transport->postRequests,
        );
    }

    /**
     * При null config Provider читает тот же контракт из Yii params.
     */
    public function testProviderReadsConfigurationFromYiiParams(): void
    {
        Yii::$app->params['oidc'] = $this->config();

        $transport = new FakeOidcHttpTransport();
        $transport->respondToGet(self::DISCOVERY_URL, $this->discovery());

        $provider = new OidcProvider(null, $transport);

        self::assertSame(self::AUTHORIZATION_ENDPOINT, $provider->authorizationEndpoint());
        self::assertSame([['url' => self::DISCOVERY_URL, 'timeout' => 7]], $transport->getRequests);
    }

    /**
     * `email` не является обязательным scope для административно привязанной OIDC identity.
     */
    public function testConfigurationAcceptsOpenidOnlyScopes(): void
    {
        $config = $this->config();
        $config['scopes'] = ['openid'];
        $transport = new FakeOidcHttpTransport();
        $transport->respondToGet(self::DISCOVERY_URL, $this->discovery());

        $provider = new OidcProvider($config, $transport);

        self::assertSame(self::AUTHORIZATION_ENDPOINT, $provider->authorizationEndpoint());
    }

    /**
     * Metadata от другого issuer не может подменить endpoints доверенного провайдера.
     */
    public function testDiscoveryIssuerMustMatchConfiguredIssuer(): void
    {
        $transport = new FakeOidcHttpTransport();
        $metadata = $this->discovery();
        $metadata['issuer'] = 'https://attacker.example.test';
        $transport->respondToGet(self::DISCOVERY_URL, $metadata);

        try {
            (new OidcProvider($this->config(), $transport))->discovery();
            self::fail('Mismatched discovery issuer was accepted.');
        } catch (OidcException $exception) {
            self::assertSame(
                'OIDC discovery issuer does not match configured issuer.',
                $exception->getMessage(),
            );
        }
    }

    /**
     * Даже fake transport не позволяет discovery направить запрос на нестандартную схему.
     */
    public function testDiscoveryRejectsNonHttpEndpoint(): void
    {
        $transport = new FakeOidcHttpTransport();
        $metadata = $this->discovery();
        $metadata['jwks_uri'] = 'file:///etc/passwd';
        $transport->respondToGet(self::DISCOVERY_URL, $metadata);

        try {
            (new OidcProvider($this->config(), $transport))->jwks();
            self::fail('Non-HTTP JWKS endpoint was accepted.');
        } catch (OidcException $exception) {
            self::assertSame(
                'OIDC metadata field jwks_uri must be an absolute HTTP(S) URL.',
                $exception->getMessage(),
            );
        }
    }

    /**
     * PKCE verifier проверяется до token request.
     */
    public function testExchangeRejectsInvalidPkceVerifierWithoutPostRequest(): void
    {
        $transport = new FakeOidcHttpTransport();
        $provider = new OidcProvider($this->config(), $transport);

        try {
            $provider->exchangeCode('one-time-code', 'too-short');
            self::fail('Invalid PKCE verifier was accepted.');
        } catch (OidcException $exception) {
            self::assertSame('OIDC PKCE code verifier is invalid.', $exception->getMessage());
        }

        self::assertSame([], $transport->postRequests);
        self::assertSame([], $transport->getRequests);
    }

    /**
     * cURL transport отбрасывает опасную схему до инициализации сетевого запроса.
     */
    public function testCurlTransportRejectsNonHttpUrl(): void
    {
        try {
            (new CurlOidcHttpTransport())->getJson('ftp://sso.example.test/discovery', 5);
            self::fail('cURL transport accepted a non-HTTP URL.');
        } catch (OidcException $exception) {
            self::assertSame(
                'OIDC endpoint must be an absolute HTTP(S) URL without credentials or fragment.',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'issuer' => self::ISSUER,
            'clientId' => 'stockhub-client',
            'clientSecret' => 'client-secret',
            'redirectUri' => 'https://stockhub.example.test/auth/sso/callback',
            'scopes' => ['openid', 'profile', 'email'],
            'httpTimeout' => 7,
            'clockSkewSeconds' => 60,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function discovery(): array
    {
        return [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::AUTHORIZATION_ENDPOINT,
            'token_endpoint' => self::TOKEN_ENDPOINT,
            'jwks_uri' => self::JWKS_URI,
        ];
    }
}
