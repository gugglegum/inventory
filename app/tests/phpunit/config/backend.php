<?php

declare(strict_types=1);

$testParams = yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/params.php',
    require dirname(__DIR__, 3) . '/backend/config/params.php',
    (require __DIR__ . '/common.php')['params']
);

$testConfig = yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/main.php',
    require __DIR__ . '/common.php',
    require dirname(__DIR__, 3) . '/backend/config/main.php',
    [
        'id' => 'app-backend-test',
        'basePath' => dirname(__DIR__, 3) . '/backend',
        'runtimePath' => '@phpunitRuntime/backend',
        'bootstrap' => ['log'],
        // backend/config/main.php assigns its own local $params while required
        // in this scope, so the test value must use a distinct variable name.
        'params' => $testParams,
        'components' => [
            'request' => [
                'cookieValidationKey' => 'phpunit-test-cookie-key',
                'enableCsrfValidation' => false,
            ],
            'user' => [
                'identityClass' => common\models\User::class,
                'enableAutoLogin' => false,
            ],
            'assetManager' => [
                'basePath' => '@phpunitRuntime/assets',
                'baseUrl' => '/assets',
            ],
        ],
    ]
);

// Numeric arrays are appended by ArrayHelper::merge(), so replace bootstrap
// explicitly to keep tests independent from runtime auth feature flags.
$testConfig['bootstrap'] = ['log'];

return $testConfig;
