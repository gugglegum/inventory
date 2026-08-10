<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use tests\phpunit\TestCase;
use Yii;
use yii\log\FileTarget;
use yii\log\Logger;

/**
 * Regression-тесты безопасной конфигурации web/console логирования.
 */
final class LoggingConfigurationTest extends TestCase
{
    /**
     * Backend dev-профиль не должен сохранять snapshots Yii Debug.
     */
    public function testBackendDevProfileDoesNotRegisterDebugDataCollector(): void
    {
        $configPath = dirname(__DIR__, 3) . '/environments/dev/backend/config/main-local.php';
        /** @var array<string, mixed> $config */
        $config = require $configPath;

        /** @var array<int|string, mixed> $bootstrap */
        $bootstrap = $config['bootstrap'] ?? [];
        /** @var array<string, mixed> $modules */
        $modules = $config['modules'] ?? [];

        self::assertNotContains('debug', $bootstrap);
        self::assertArrayNotHasKey('debug', $modules);

        // PHPUnit bootstrap uses YII_ENV=test, so also guard the dev-only branch
        // against accidentally reintroducing Yii Debug configuration.
        $configSource = file_get_contents($configPath);
        self::assertIsString($configSource);
        self::assertStringNotContainsString('yii\\debug\\Module', $configSource);
        self::assertStringNotContainsString("['modules']['debug']", $configSource);
    }

    /**
     * Console FileTarget не добавляет globals, session ID или env secrets.
     */
    public function testConsoleLogTargetDoesNotCaptureSensitiveContext(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/console/config/main.php';
        /** @var array<int, array<string, mixed>> $targetConfigs */
        $targetConfigs = $config['components']['log']['targets'];

        $target = Yii::createObject($targetConfigs[0]);

        self::assertInstanceOf(FileTarget::class, $target);
        self::assertSame([], $target->logVars);
        self::assertContains('_SERVER.*SECRET*', $target->maskVars);
        self::assertContains('_ENV.*SECRET*', $target->maskVars);
        self::assertIsCallable($target->prefix);
        self::assertSame('', ($target->prefix)([]));
    }

    /**
     * Console target действительно пишет сообщение без inherited secrets.
     */
    public function testConsoleLogTargetWritesWithoutLeakingGlobals(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/console/config/main.php';
        /** @var array<int, array<string, mixed>> $targetConfigs */
        $targetConfigs = $config['components']['log']['targets'];

        $target = Yii::createObject($targetConfigs[0]);
        self::assertInstanceOf(FileTarget::class, $target);

        $logFile = Yii::getAlias('@phpunitRuntime') . '/console-logging/security.log';
        $target->logFile = $logFile;
        if (is_file($logFile)) {
            unlink($logFile);
        }

        $previousServerSecret = $_SERVER['OIDC_CLIENT_SECRET'] ?? null;
        $previousSessionCookie = $_COOKIE['PHPSESSID'] ?? null;
        $_SERVER['OIDC_CLIENT_SECRET'] = 'console-secret-leak-marker';
        $_COOKIE['PHPSESSID'] = 'console-cookie-leak-marker';
        $_SESSION['stockhub.console-log-test'] = 'console-session-leak-marker';

        try {
            $target->collect([
                ['console-log-probe', Logger::LEVEL_WARNING, 'security-test', microtime(true)],
            ], true);

            $contents = file_get_contents($logFile);
            self::assertIsString($contents);
            self::assertStringContainsString('console-log-probe', $contents);
            self::assertStringNotContainsString('console-secret-leak-marker', $contents);
            self::assertStringNotContainsString('console-cookie-leak-marker', $contents);
            self::assertStringNotContainsString('console-session-leak-marker', $contents);
        } finally {
            if (is_string($previousServerSecret)) {
                $_SERVER['OIDC_CLIENT_SECRET'] = $previousServerSecret;
            } else {
                unset($_SERVER['OIDC_CLIENT_SECRET']);
            }
            if (is_string($previousSessionCookie)) {
                $_COOKIE['PHPSESSID'] = $previousSessionCookie;
            } else {
                unset($_COOKIE['PHPSESSID']);
            }
            unset($_SESSION['stockhub.console-log-test']);
            if (is_file($logFile)) {
                unlink($logFile);
            }
        }
    }

}
