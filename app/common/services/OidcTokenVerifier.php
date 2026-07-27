<?php

declare(strict_types=1);

namespace common\services;

use Closure;
use JsonException;
use OpenSSLAsymmetricKey;

/**
 * Проверяет подпись и обязательные security claims OIDC id_token.
 */
final class OidcTokenVerifier
{
    private OidcConfiguration $configuration;

    /** @var Closure(): int */
    private Closure $clock;

    /**
     * @param array<string, mixed>|null $config При null используется Yii::$app->params['oidc'].
     * @param (Closure(): int)|null $clock Инъекция времени предназначена для детерминированных unit-тестов.
     */
    public function __construct(
        private readonly OidcProvider $provider,
        #[\SensitiveParameter] ?array $config = null,
        ?Closure $clock = null,
    ) {
        $this->configuration = OidcConfiguration::fromArray($config);
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Проверяет id_token и возвращает подтвержденные claims.
     *
     * @return array<string, mixed>
     */
    public function verify(
        #[\SensitiveParameter] string $idToken,
        #[\SensitiveParameter] string $expectedNonce,
    ): array {
        if ($expectedNonce === '') {
            throw new OidcException('Expected OIDC nonce must not be empty.');
        }

        [$header, $claims, $signature, $signedPayload] = $this->parse($idToken);

        if (($header['alg'] ?? null) !== 'RS256') {
            throw new OidcException('Unsupported id_token signing algorithm.');
        }

        $this->verifySignature($header, $signature, $signedPayload);
        $this->verifyClaims($claims, $expectedNonce);

        return $claims;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string}
     */
    private function parse(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new OidcException('Malformed id_token.');
        }

        $header = $this->decodeJsonObject($parts[0], 'header');
        $claims = $this->decodeJsonObject($parts[1], 'claims');
        $signature = $this->base64UrlDecode($parts[2]);

        if ($signature === '') {
            throw new OidcException('Malformed id_token signature.');
        }

        return [$header, $claims, $signature, $parts[0] . '.' . $parts[1]];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $encoded, string $part): array
    {
        try {
            $value = json_decode($this->base64UrlDecode($encoded), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OidcException("Malformed id_token {$part} JSON.", previous: $exception);
        }

        if (!is_array($value) || $value === [] || array_is_list($value)) {
            throw new OidcException("Malformed id_token {$part} JSON.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $header
     */
    private function verifySignature(array $header, string $signature, string $signedPayload): void
    {
        $kid = $header['kid'] ?? null;
        if ($kid !== null && (!is_string($kid) || $kid === '')) {
            throw new OidcException('Invalid id_token signing key identifier.');
        }

        $keys = $this->provider->jwks()['keys'] ?? null;
        if (!is_array($keys)) {
            throw new OidcException('OIDC JWKS does not contain signing keys.');
        }

        $candidates = [];
        foreach ($keys as $candidate) {
            if (!is_array($candidate) || !$this->isSigningKey($candidate)) {
                continue;
            }

            if ($kid === null || ($candidate['kid'] ?? null) === $kid) {
                $candidates[] = $candidate;
            }
        }

        if (count($candidates) !== 1) {
            throw new OidcException('No unique matching OIDC signing key.');
        }

        $publicKey = $this->jwkToPublicKey($candidates[0]);
        $verified = openssl_verify($signedPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new OidcException('Invalid id_token signature.');
        }
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function isSigningKey(array $jwk): bool
    {
        if (($jwk['kty'] ?? null) !== 'RSA') {
            return false;
        }

        if (isset($jwk['use']) && $jwk['use'] !== 'sig') {
            return false;
        }

        if (isset($jwk['alg']) && $jwk['alg'] !== 'RS256') {
            return false;
        }

        if (isset($jwk['key_ops'])) {
            if (!is_array($jwk['key_ops']) || !in_array('verify', $jwk['key_ops'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function verifyClaims(array $claims, string $expectedNonce): void
    {
        if (($claims['iss'] ?? null) !== $this->configuration->issuer) {
            throw new OidcException('Invalid id_token issuer.');
        }

        $this->verifyAudience($claims);

        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new OidcException('Missing id_token subject.');
        }

        $nonce = $claims['nonce'] ?? null;
        if (!is_string($nonce) || !hash_equals($expectedNonce, $nonce)) {
            throw new OidcException('Invalid id_token nonce.');
        }

        $now = ($this->clock)();
        $skew = $this->configuration->clockSkewSeconds;
        $expiresAt = $this->numericDate($claims, 'exp', true);
        $issuedAt = $this->numericDate($claims, 'iat', true);
        $notBefore = $this->numericDate($claims, 'nbf', false);

        if ($expiresAt <= $now - $skew) {
            throw new OidcException('Expired id_token.');
        }

        if ($issuedAt > $now + $skew) {
            throw new OidcException('id_token was issued in the future.');
        }

        if ($notBefore !== null && $notBefore > $now + $skew) {
            throw new OidcException('id_token is not valid yet.');
        }

        if ($issuedAt >= $expiresAt) {
            throw new OidcException('Invalid id_token lifetime.');
        }

        if ($notBefore !== null && $notBefore >= $expiresAt) {
            throw new OidcException('Invalid id_token lifetime.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function verifyAudience(array $claims): void
    {
        $audience = $claims['aud'] ?? null;
        if (is_string($audience) && $audience !== '') {
            $audiences = [$audience];
        } elseif (is_array($audience) && $audience !== [] && array_is_list($audience)) {
            $audiences = [];
            foreach ($audience as $value) {
                if (!is_string($value) || $value === '') {
                    throw new OidcException('Invalid id_token audience.');
                }
                $audiences[] = $value;
            }
        } else {
            throw new OidcException('Invalid id_token audience.');
        }

        if (!in_array($this->configuration->clientId, $audiences, true)) {
            throw new OidcException('Invalid id_token audience.');
        }

        $authorizedParty = $claims['azp'] ?? null;
        if (
            (count(array_unique($audiences)) > 1 && $authorizedParty === null)
            || ($authorizedParty !== null && $authorizedParty !== $this->configuration->clientId)
        ) {
            throw new OidcException('Invalid id_token authorized party.');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function numericDate(array $claims, string $name, bool $required): int|float|null
    {
        if (!array_key_exists($name, $claims)) {
            if ($required) {
                throw new OidcException("Missing id_token {$name} claim.");
            }

            return null;
        }

        $value = $claims[$name];
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0) {
            throw new OidcException("Invalid id_token {$name} claim.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function jwkToPublicKey(array $jwk): OpenSSLAsymmetricKey
    {
        $modulusValue = $jwk['n'] ?? null;
        $exponentValue = $jwk['e'] ?? null;
        if (!is_string($modulusValue) || !is_string($exponentValue)) {
            throw new OidcException('Unsupported OIDC signing key.');
        }

        $modulusBytes = $this->base64UrlDecode($modulusValue);
        $exponentBytes = $this->base64UrlDecode($exponentValue);
        if (
            strlen($modulusBytes) < 256
            || strlen($modulusBytes) > 1024
            || $exponentBytes === ''
            || strlen($exponentBytes) > 8
        ) {
            throw new OidcException('Unsupported OIDC signing key.');
        }

        $modulus = $this->asn1Integer($modulusBytes);
        $exponent = $this->asn1Integer($exponentBytes);
        $rsaPublicKey = $this->asn1Sequence($modulus . $exponent);
        $algorithm = $this->asn1Sequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");
        $subjectPublicKey = "\x03" . $this->asn1Length(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
        $publicKeyInfo = $this->asn1Sequence($algorithm . $subjectPublicKey);
        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($publicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $publicKey = openssl_pkey_get_public($pem);
        if (!$publicKey instanceof OpenSSLAsymmetricKey) {
            throw new OidcException('Unsupported OIDC signing key.');
        }

        $details = openssl_pkey_get_details($publicKey);
        if (!is_array($details) || !is_int($details['bits'] ?? null) || $details['bits'] < 2048) {
            throw new OidcException('OIDC RSA signing key is too small.');
        }

        return $publicKey;
    }

    private function asn1Integer(string $value): string
    {
        $value = ltrim($value, "\x00");
        $value = $value === '' ? "\x00" : $value;

        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }

        return "\x02" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw new OidcException('Invalid base64url value in id_token or JWK.');
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);

        if ($decoded === false) {
            throw new OidcException('Invalid base64url value in id_token or JWK.');
        }

        return $decoded;
    }
}
