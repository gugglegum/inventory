<?php

declare(strict_types=1);

namespace common\services;

use JsonException;
use RuntimeException;
use Yii;

/**
 * Ограничивает исходящие OIDC discovery и token exchange запросы.
 *
 * Состояние общее для PHP-FPM workers одного deployment. Отдельный постоянный
 * lock-файл не меняет inode при атомарной замене JSON state-файла.
 */
final class OidcFlowRateLimiter
{
    public const int DEFAULT_TOKEN_GLOBAL_LIMIT = 8;

    public const int DEFAULT_TOKEN_CLIENT_LIMIT = 2;

    public const int DEFAULT_AUTHORIZATION_GLOBAL_LIMIT = 40;

    public const int DEFAULT_AUTHORIZATION_CLIENT_LIMIT = 10;

    public const int BASE_WINDOW_SECONDS = 60;

    private const int STATE_VERSION = 1;

    private const int MAX_STORAGE_BYTES = 32768;

    public function __construct(
        private readonly int $tokenGlobalLimit = self::DEFAULT_TOKEN_GLOBAL_LIMIT,
        private readonly int $tokenClientLimit = self::DEFAULT_TOKEN_CLIENT_LIMIT,
        private readonly int $authorizationGlobalLimit = self::DEFAULT_AUTHORIZATION_GLOBAL_LIMIT,
        private readonly int $authorizationClientLimit = self::DEFAULT_AUTHORIZATION_CLIENT_LIMIT,
        private readonly ?string $storageFile = null,
    ) {
        if (
            $this->tokenGlobalLimit < 1
            || $this->tokenClientLimit < 1
            || $this->authorizationGlobalLimit < 1
            || $this->authorizationClientLimit < 1
        ) {
            throw new RuntimeException('OIDC rate limits must be positive.');
        }
    }

    /**
     * Резервирует один discovery request перед началом authorization flow.
     */
    public function consumeAuthorizationStart(string $clientIp): bool
    {
        return $this->consume(
            'authorization',
            $clientIp,
            $this->authorizationGlobalLimit,
            $this->authorizationClientLimit,
            self::BASE_WINDOW_SECONDS,
        );
    }

    /**
     * Резервирует callback discovery и последующий token exchange.
     *
     * Запись живёт дольше upstream-окна на максимальное суммарное время двух
     * последовательных подключений: discovery и POST /oauth/token. Локальная
     * квота не должна освободиться раньше, чем token request попадёт в
     * middleware Pyrda SSO.
     */
    public function consumeTokenExchange(string $clientIp, int $upstreamTimeoutSeconds): bool
    {
        if ($upstreamTimeoutSeconds < 1) {
            throw new RuntimeException('OIDC upstream timeout must be positive.');
        }
        if ($upstreamTimeoutSeconds > intdiv(PHP_INT_MAX - self::BASE_WINDOW_SECONDS, 2)) {
            throw new RuntimeException('OIDC upstream timeout is too large.');
        }

        return $this->consume(
            'token',
            $clientIp,
            $this->tokenGlobalLimit,
            $this->tokenClientLimit,
            self::BASE_WINDOW_SECONDS + (2 * $upstreamTimeoutSeconds),
        );
    }

    /**
     * @param 'authorization'|'token' $scope
     */
    private function consume(
        string $scope,
        string $clientIp,
        int $globalLimit,
        int $clientLimit,
        int $lifetimeSeconds,
    ): bool {
        $clientKey = $this->clientKey($clientIp);
        $storageFile = $this->storageFile();
        $lockFile = $storageFile . '.lock';
        $temporaryFile = $storageFile . '.tmp';
        $lockHandle = @fopen($lockFile, 'c+b');
        if ($lockHandle === false) {
            throw new RuntimeException('Cannot open OIDC rate-limit lock storage.');
        }

        $locked = false;

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock OIDC rate-limit storage.');
            }
            $locked = true;
            $this->removeTemporaryFile($temporaryFile);

            $now = microtime(true);
            $state = $this->readState($storageFile, $now);
            $scopeState = $state[$scope];
            $clientExpirations = $scopeState['clients'][$clientKey] ?? [];

            if (
                count($scopeState['global']) >= $globalLimit
                || count($clientExpirations) >= $clientLimit
            ) {
                return false;
            }

            $expiresAt = $now + (float) $lifetimeSeconds;
            $scopeState['global'][] = $expiresAt;
            $clientExpirations[] = $expiresAt;
            $scopeState['clients'][$clientKey] = $clientExpirations;
            $state[$scope] = $scopeState;
            $this->writeState($storageFile, $temporaryFile, $state);

            return true;
        } finally {
            if ($locked) {
                flock($lockHandle, LOCK_UN);
            }
            fclose($lockHandle);
        }
    }

    /**
     * @return array{
     *     version:int,
     *     authorization:array{global:list<float>,clients:array<string,list<float>>},
     *     token:array{global:list<float>,clients:array<string,list<float>>}
     * }
     */
    private function readState(string $storageFile, float $now): array
    {
        if (!file_exists($storageFile)) {
            return $this->emptyState();
        }
        if (!is_file($storageFile)) {
            throw new RuntimeException('OIDC rate-limit state path is not a regular file.');
        }

        $handle = @fopen($storageFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cannot open OIDC rate-limit state.');
        }

        try {
            $stat = fstat($handle);
            if ($stat === false) {
                throw new RuntimeException('Cannot inspect OIDC rate-limit state.');
            }
            if ($stat['size'] < 1 || $stat['size'] > self::MAX_STORAGE_BYTES) {
                throw new RuntimeException('OIDC rate-limit state has an invalid size.');
            }

            $encodedState = stream_get_contents($handle);
            if (!is_string($encodedState) || $encodedState === '') {
                throw new RuntimeException('Cannot read OIDC rate-limit state.');
            }
        } finally {
            fclose($handle);
        }

        try {
            $rawState = json_decode($encodedState, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('OIDC rate-limit state is malformed.', 0, $exception);
        }

        if (!is_array($rawState) || ($rawState['version'] ?? null) !== self::STATE_VERSION) {
            throw new RuntimeException('OIDC rate-limit state has an unsupported version.');
        }

        return [
            'version' => self::STATE_VERSION,
            'authorization' => $this->readScopeState(
                $rawState['authorization'] ?? null,
                $now,
                'authorization',
            ),
            'token' => $this->readScopeState($rawState['token'] ?? null, $now, 'token'),
        ];
    }

    /**
     * @return array{global:list<float>,clients:array<string,list<float>>}
     */
    private function readScopeState(mixed $rawScope, float $now, string $scope): array
    {
        if (!is_array($rawScope)) {
            throw new RuntimeException("OIDC {$scope} rate-limit state is malformed.");
        }

        $global = $this->readExpirations($rawScope['global'] ?? null, $now, $scope);
        $rawClients = $rawScope['clients'] ?? null;
        if (!is_array($rawClients)) {
            throw new RuntimeException("OIDC {$scope} client rate-limit state is malformed.");
        }

        $clients = [];
        $clientAttemptCount = 0;

        foreach ($rawClients as $clientKey => $rawExpirations) {
            if (!is_string($clientKey) || preg_match('/^[a-f0-9]{64}$/D', $clientKey) !== 1) {
                throw new RuntimeException("OIDC {$scope} client rate-limit key is malformed.");
            }

            $expirations = $this->readExpirations($rawExpirations, $now, "{$scope} client");
            if ($expirations !== []) {
                $clients[$clientKey] = $expirations;
                $clientAttemptCount += count($expirations);
            }
        }

        if ($clientAttemptCount !== count($global)) {
            throw new RuntimeException("OIDC {$scope} rate-limit counters are inconsistent.");
        }

        return [
            'global' => $global,
            'clients' => $clients,
        ];
    }

    /**
     * @return list<float>
     */
    private function readExpirations(mixed $rawExpirations, float $now, string $scope): array
    {
        if (!is_array($rawExpirations) || !array_is_list($rawExpirations)) {
            throw new RuntimeException("OIDC {$scope} rate-limit expirations are malformed.");
        }

        $expirations = [];

        foreach ($rawExpirations as $expiration) {
            if ((!is_int($expiration) && !is_float($expiration)) || !is_finite((float) $expiration)) {
                throw new RuntimeException("OIDC {$scope} rate-limit expiration is malformed.");
            }

            $expiresAt = (float) $expiration;
            if ($expiresAt > $now) {
                $expirations[] = $expiresAt;
            }
        }

        return $expirations;
    }

    /**
     * @param array{
     *     version:int,
     *     authorization:array{global:list<float>,clients:array<string,list<float>>},
     *     token:array{global:list<float>,clients:array<string,list<float>>}
     * } $state
     */
    private function writeState(string $storageFile, string $temporaryFile, array $state): void
    {
        $encodedState = json_encode($state, JSON_THROW_ON_ERROR);
        if (strlen($encodedState) > self::MAX_STORAGE_BYTES) {
            throw new RuntimeException('OIDC rate-limit state is unexpectedly large.');
        }

        $handle = @fopen($temporaryFile, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Cannot create temporary OIDC rate-limit state.');
        }

        $renamed = false;

        try {
            $remaining = $encodedState;
            while ($remaining !== '') {
                $written = fwrite($handle, $remaining);
                if (!is_int($written) || $written < 1) {
                    throw new RuntimeException('Cannot write temporary OIDC rate-limit state.');
                }
                $remaining = substr($remaining, $written);
            }

            if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                throw new RuntimeException('Cannot flush temporary OIDC rate-limit state.');
            }
            if (!fclose($handle)) {
                throw new RuntimeException('Cannot close temporary OIDC rate-limit state.');
            }
            $handle = null;

            if (!@rename($temporaryFile, $storageFile)) {
                throw new RuntimeException('Cannot atomically replace OIDC rate-limit state.');
            }
            $renamed = true;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (!$renamed && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function removeTemporaryFile(string $temporaryFile): void
    {
        if (!file_exists($temporaryFile)) {
            return;
        }
        if (!is_file($temporaryFile) || !@unlink($temporaryFile)) {
            throw new RuntimeException('Cannot clean temporary OIDC rate-limit state.');
        }
    }

    /**
     * @return array{
     *     version:int,
     *     authorization:array{global:list<float>,clients:array<string,list<float>>},
     *     token:array{global:list<float>,clients:array<string,list<float>>}
     * }
     */
    private function emptyState(): array
    {
        return [
            'version' => self::STATE_VERSION,
            'authorization' => [
                'global' => [],
                'clients' => [],
            ],
            'token' => [
                'global' => [],
                'clients' => [],
            ],
        ];
    }

    private function clientKey(string $clientIp): string
    {
        $packedIp = @inet_pton($clientIp);
        if (!is_string($packedIp)) {
            throw new RuntimeException('OIDC client IP address is invalid.');
        }

        return hash('sha256', $packedIp);
    }

    private function storageFile(): string
    {
        if ($this->storageFile !== null && $this->storageFile !== '') {
            return $this->storageFile;
        }

        $runtimePath = Yii::getAlias('@runtime');
        if ($runtimePath === '') {
            throw new RuntimeException('Yii runtime path is not configured.');
        }

        return $runtimePath . '/oidc-flow-rate-limit-v1.json';
    }
}
