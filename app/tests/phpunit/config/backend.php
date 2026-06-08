<?php

$params = yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/params.php',
    require dirname(__DIR__, 3) . '/backend/config/params.php',
    (require __DIR__ . '/common.php')['params']
);

return yii\helpers\ArrayHelper::merge(
    require dirname(__DIR__, 3) . '/common/config/main.php',
    require __DIR__ . '/common.php',
    require dirname(__DIR__, 3) . '/backend/config/main.php',
    [
        'id' => 'app-backend-test',
        'basePath' => dirname(__DIR__, 3) . '/backend',
        'runtimePath' => '@phpunitTests/_runtime/backend',
        'params' => $params,
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
                'basePath' => '@phpunitTests/_runtime/assets',
                'baseUrl' => '/assets',
            ],
        ],
    ]
);
