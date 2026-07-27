<?php

declare(strict_types=1);

namespace tests\phpunit\unit;

use common\services\OidcException;
use common\services\OidcProvider;
use common\services\OidcTokenVerifier;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use tests\phpunit\TestCase;
use Yii;

/**
 * Unit-тесты криптографической и claim-валидации OIDC id_token.
 */
final class OidcTokenVerifierTest extends TestCase
{
    private const ISSUER = 'https://sso.example.test';
    private const CLIENT_ID = 'stockhub-client';
    private const NONCE = 'expected-nonce';
    private const KEY_ID = 'current-signing-key';
    private const NOW = 1_800_000_000;

    private static OpenSSLAsymmetricKey $privateKey;

    /** @var array<string, mixed> */
    private static array $jwk;

    /**
     * Генерирует реальную 2048-bit RSA-пару для RS256-тестов.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$privateKey instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('Could not generate RSA key for OIDC tests.');
        }

        $details = openssl_pkey_get_details($privateKey);
        $rsa = is_array($details) ? ($details['rsa'] ?? null) : null;
        $modulus = is_array($rsa) ? ($rsa['n'] ?? null) : null;
        $exponent = is_array($rsa) ? ($rsa['e'] ?? null) : null;
        if (!is_string($modulus) || !is_string($exponent)) {
            throw new RuntimeException('Generated RSA key has no public parameters.');
        }

        self::$privateKey = $privateKey;
        self::$jwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'key_ops' => ['verify'],
            'alg' => 'RS256',
            'kid' => self::KEY_ID,
            'n' => self::base64UrlEncode($modulus),
            'e' => self::base64UrlEncode($exponent),
        ];
    }

    /**
     * Валидный RS256 id_token возвращает только после проверки подписи и claims.
     */
    public function testValidTokenReturnsVerifiedClaims(): void
    {
        $claims = $this->validClaims();
        $claims['email'] = 'user@example.test';

        $verified = $this->verifier()->verify($this->token($claims), self::NONCE);

        self::assertSame($claims, $verified);
    }

    /**
     * nbf является опциональным по OIDC, но остальные обязательные даты сохраняются.
     */
    public function testTokenWithoutOptionalNotBeforeClaimIsAccepted(): void
    {
        $claims = $this->validClaims();
        unset($claims['nbf']);

        self::assertSame($claims, $this->verifier()->verify($this->token($claims), self::NONCE));
    }

    /**
     * При null config verifier читает Yii::$app->params['oidc'].
     */
    public function testVerifierReadsConfigurationFromYiiParams(): void
    {
        $config = $this->config();
        Yii::$app->params['oidc'] = $config;
        $provider = new OidcProvider($config, $this->transport());
        $verifier = new OidcTokenVerifier(
            $provider,
            null,
            static fn (): int => self::NOW,
        );
        $claims = $this->validClaims();

        self::assertSame($claims, $verifier->verify($this->token($claims), self::NONCE));
    }

    /**
     * Проверяет обязательные identity/time claims и допустимый clock skew.
     *
     * @param mixed $value
     */
    #[DataProvider('invalidClaimsProvider')]
    public function testInvalidClaimsAreRejected(
        string $claim,
        mixed $value,
        bool $remove,
        string $message,
    ): void {
        $claims = $this->validClaims();
        if ($remove) {
            unset($claims[$claim]);
        } else {
            $claims[$claim] = $value;
        }

        $this->assertVerificationFails($this->token($claims), $message);
    }

    /**
     * @return iterable<string, array{string, mixed, bool, string}>
     */
    public static function invalidClaimsProvider(): iterable
    {
        yield 'issuer' => ['iss', 'https://attacker.example.test', false, 'Invalid id_token issuer.'];
        yield 'audience' => ['aud', 'another-client', false, 'Invalid id_token audience.'];
        yield 'subject' => ['sub', '', false, 'Missing id_token subject.'];
        yield 'nonce' => ['nonce', 'another-nonce', false, 'Invalid id_token nonce.'];
        yield 'expired' => ['exp', self::NOW - 60, false, 'Expired id_token.'];
        yield 'future nbf' => ['nbf', self::NOW + 61, false, 'id_token is not valid yet.'];
        yield 'future iat' => ['iat', self::NOW + 61, false, 'id_token was issued in the future.'];
        yield 'missing exp' => ['exp', null, true, 'Missing id_token exp claim.'];
        yield 'missing iat' => ['iat', null, true, 'Missing id_token iat claim.'];
        yield 'string exp' => ['exp', (string) (self::NOW + 300), false, 'Invalid id_token exp claim.'];
    }

    /**
     * Несколько audiences требуют корректный azp, однозначно указывающий client.
     */
    public function testMultipleAudiencesRequireMatchingAuthorizedParty(): void
    {
        $claims = $this->validClaims();
        $claims['aud'] = [self::CLIENT_ID, 'resource-server'];

        try {
            $this->verifier()->verify($this->token($claims), self::NONCE);
            self::fail('Token with ambiguous audiences was accepted.');
        } catch (OidcException $exception) {
            self::assertSame('Invalid id_token authorized party.', $exception->getMessage());
        }

        $claims['azp'] = self::CLIENT_ID;
        self::assertSame($claims, $this->verifier()->verify($this->token($claims), self::NONCE));
    }

    /**
     * Разрешен только явно указанный RS256; alg из header не выбирает произвольный алгоритм.
     */
    public function testNonRs256AlgorithmIsRejectedBeforeJwksRequest(): void
    {
        $transport = $this->transport();
        $header = ['alg' => 'HS256', 'typ' => 'JWT', 'kid' => self::KEY_ID];

        $this->assertVerificationFails(
            $this->token($this->validClaims(), $header),
            'Unsupported id_token signing algorithm.',
            $this->verifier($transport),
        );
        self::assertSame([], $transport->getRequests);
    }

    /**
     * Измененная подпись не проходит openssl_verify.
     */
    public function testInvalidSignatureIsRejected(): void
    {
        $parts = explode('.', $this->token($this->validClaims()));
        $parts[2] = self::base64UrlEncode(str_repeat("\x00", 256));

        $this->assertVerificationFails(implode('.', $parts), 'Invalid id_token signature.');
    }

    /**
     * kid обязан однозначно выбрать допустимый signing key.
     */
    public function testUnknownSigningKeyIsRejected(): void
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'retired-key'];

        $this->assertVerificationFails(
            $this->token($this->validClaims(), $header),
            'No unique matching OIDC signing key.',
        );
    }

    /**
     * Поврежденные JWT segments отбрасываются до обращения к OpenSSL.
     */
    public function testMalformedBase64UrlTokenIsRejected(): void
    {
        $this->assertVerificationFails(
            'not+base64.claims.signature',
            'Invalid base64url value in id_token or JWK.',
        );
    }

    /**
     * @param array<string, mixed>|null $claims
     * @param array<string, mixed>|null $header
     */
    private function token(?array $claims = null, ?array $header = null): string
    {
        $header ??= ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => self::KEY_ID];
        $claims ??= $this->validClaims();
        $encodedHeader = self::base64UrlEncode(
            json_encode($header, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $encodedClaims = self::base64UrlEncode(
            json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $signedPayload = $encodedHeader . '.' . $encodedClaims;

        $signature = '';
        if (!openssl_sign($signedPayload, $signature, self::$privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign OIDC test token.');
        }

        return $signedPayload . '.' . self::base64UrlEncode($signature);
    }

    private function verifier(?FakeOidcHttpTransport $transport = null): OidcTokenVerifier
    {
        $transport ??= $this->transport();
        $config = $this->config();
        $provider = new OidcProvider($config, $transport);

        return new OidcTokenVerifier(
            $provider,
            $config,
            static fn (): int => self::NOW,
        );
    }

    /**
     * Проверяет отказ verifier без deprecated exception-expectation API PHPUnit.
     */
    private function assertVerificationFails(
        string $token,
        string $expectedMessage,
        ?OidcTokenVerifier $verifier = null,
    ): void {
        try {
            ($verifier ?? $this->verifier())->verify($token, self::NONCE);
            self::fail('Invalid id_token was accepted.');
        } catch (OidcException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
        }
    }

    private function transport(): FakeOidcHttpTransport
    {
        $transport = new FakeOidcHttpTransport();
        $transport->respondToGet(
            self::ISSUER . '/.well-known/openid-configuration',
            [
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/oauth/authorize',
                'token_endpoint' => self::ISSUER . '/oauth/token',
                'jwks_uri' => self::ISSUER . '/oauth/jwks',
            ],
        );
        $transport->respondToGet(self::ISSUER . '/oauth/jwks', ['keys' => [self::$jwk]]);

        return $transport;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'issuer' => self::ISSUER,
            'clientId' => self::CLIENT_ID,
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
    private function validClaims(): array
    {
        return [
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'sso-user-42',
            'nonce' => self::NONCE,
            'iat' => self::NOW - 10,
            'nbf' => self::NOW - 10,
            'exp' => self::NOW + 300,
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
