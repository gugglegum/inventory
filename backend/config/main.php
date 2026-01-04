<?php

$params = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [],
    'defaultRoute' => 'repo/index',
    'components' => [
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                '' => 'repo/index',
                'repo/<repoId:\d+>/items' => 'items/index',
                'repo/<repoId:\d+>/items/<id:\d+>' => 'items/view',
                'repo/<repoId:\d+>/items/<id:\d+>/json-preview' => 'items/json-preview',
                'repo/<repoId:\d+>/items/<id:\d+>/pick-container' => 'items/pick-container',
                'repo/<repoId:\d+>/items/<parentItemId:\d+>/create' => 'items/create',
                'repo/<repoId:\d+>/items/<id:\d+>/update' => 'items/update',
                'repo/<repoId:\d+>/items/search' => 'items/search',
                'repo/<repoId:\d+>/items/search-container' => 'items/search-container',
                'repo/<repoId:\d+>/items/<id:\d+>/delete' => 'items/delete',
                'repo/<repoId:\d+>/items/<parentItemId:\d+>/import' => 'items/import',
                'repo/create' => 'repo/create',
                'repo/<repoId:\d+>/update' => 'repo/update',
                'repo/<repoId:\d+>/delete' => 'repo/delete',
            ],
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
    ],
    'params' => $params,
];
