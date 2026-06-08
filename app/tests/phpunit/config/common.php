<?php

declare(strict_types=1);

return [
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => getenv('STOCKHUB_TEST_DB_DSN') ?: 'mysql:host=db;dbname=stockhub_test',
            'username' => getenv('STOCKHUB_TEST_DB_USERNAME') ?: 'stockhub',
            'password' => getenv('STOCKHUB_TEST_DB_PASSWORD') ?: 'stockhub',
            'charset' => 'utf8mb4',
        ],
        'mailer' => [
            'class' => yii\swiftmailer\Mailer::class,
            'viewPath' => '@common/mail',
            'useFileTransport' => true,
        ],
    ],
    'params' => [
        'photos' => [
            'storagePath' => dirname(__DIR__) . '/_runtime/photos',
            'storageTemp' => dirname(__DIR__) . '/_runtime/photos/temp',
            'thumbnailPath' => dirname(__DIR__) . '/_runtime/thumbnails',
            'thumbnailTemp' => dirname(__DIR__) . '/_runtime/thumbnails/temp',
            'md5salt' => 'test-salt',
        ],
    ],
];
