<?php

$params = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'console\controllers',
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    // Console processes inherit environment variables,
                    // including OIDC_CLIENT_SECRET. Do not append globals.
                    'logVars' => [],
                    'maskVars' => [
                        '_SERVER.HTTP_AUTHORIZATION',
                        '_SERVER.PHP_AUTH_USER',
                        '_SERVER.PHP_AUTH_PW',
                        '_SERVER.*SECRET*',
                        '_ENV.*SECRET*',
                    ],
                    'prefix' => static fn (array $_message): string => '',
                ],
            ],
        ],
    ],
    'params' => $params,
];
