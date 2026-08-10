<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for environment-dependent web cookie security.
 */
final class SecureCookieConfigurationTest extends TestCase
{
    /**
     * Production must never issue authentication cookies over plain HTTP.
     */
    public function testProductionCookiesAreSecureHttpOnlyAndSameSiteLax(): void
    {
        $cookies = $this->loadCookieConfiguration('prod');

        self::assertSame('PHPSESSID', $cookies['session']['name']);
        self::assertTrue($cookies['session']['secure']);
        self::assertTrue($cookies['session']['httpOnly']);
        self::assertSame('Lax', $cookies['session']['sameSite']);

        self::assertSame('_identity', $cookies['identity']['name']);
        self::assertTrue($cookies['identity']['secure']);
        self::assertTrue($cookies['identity']['httpOnly']);
        self::assertSame('Lax', $cookies['identity']['sameSite']);
    }

    /**
     * Local development intentionally remains usable through its HTTP proxy.
     */
    public function testDevelopmentCookiesRemainAvailableOverHttp(): void
    {
        $cookies = $this->loadCookieConfiguration('dev');

        self::assertFalse($cookies['session']['secure']);
        self::assertTrue($cookies['session']['httpOnly']);
        self::assertSame('Lax', $cookies['session']['sameSite']);

        self::assertFalse($cookies['identity']['secure']);
        self::assertTrue($cookies['identity']['httpOnly']);
        self::assertSame('Lax', $cookies['identity']['sameSite']);
    }

    /**
     * Loads backend config in an isolated PHP process because the PHPUnit
     * bootstrap has already defined immutable YII_ENV=test in this process.
     *
     * @return array{
     *     session: array{name: string, secure: bool, httpOnly: bool, sameSite: string},
     *     identity: array{name: string, secure: bool, httpOnly: bool, sameSite: string}
     * }
     */
    private function loadCookieConfiguration(string $environment): array
    {
        $appRoot = dirname(__DIR__, 3);
        $probe = <<<'PHP'
define('YII_ENV', $argv[1]);
define('YII_DEBUG', false);

require $argv[2] . '/vendor/autoload.php';
require $argv[2] . '/vendor/yiisoft/yii2/Yii.php';
require $argv[2] . '/common/config/bootstrap.php';

$config = require $argv[2] . '/backend/config/main.php';
$session = Yii::createObject(array_merge(
    ['class' => yii\web\Session::class],
    $config['components']['session']
));
$sessionParams = $session->getCookieParams();
$user = Yii::createObject($config['components']['user']);
$identity = $user->identityCookie;

echo json_encode([
    'session' => [
        'name' => $session->getName(),
        'secure' => $sessionParams['secure'],
        'httpOnly' => $sessionParams['httponly'],
        'sameSite' => $sessionParams['samesite'],
    ],
    'identity' => [
        'name' => $identity['name'],
        'secure' => $identity['secure'],
        'httpOnly' => $identity['httpOnly'],
        'sameSite' => $identity['sameSite'],
    ],
], JSON_THROW_ON_ERROR);
PHP;

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        /** @psalm-suppress ForbiddenCode Isolated PHP runtime is required to test immutable YII_ENV semantics. */
        $process = proc_open(
            [PHP_BINARY, '-r', $probe, $environment, $appRoot],
            $descriptorSpec,
            $pipes,
            $appRoot
        );
        self::assertIsResource($process);

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertIsString($errorOutput);
        self::assertIsString($output);
        self::assertSame(0, $exitCode, $errorOutput . $output);

        /** @var array{
         *     session: array{name: string, secure: bool, httpOnly: bool, sameSite: string},
         *     identity: array{name: string, secure: bool, httpOnly: bool, sameSite: string}
         * } $cookies
         */
        $cookies = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return $cookies;
    }
}
