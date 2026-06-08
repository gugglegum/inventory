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
            'storagePath' => '@phpunitTests/_runtime/photos',
            'storageTemp' => '@phpunitTests/_runtime/photos/temp',
            'thumbnailPath' => '@phpunitTests/_runtime/thumbnails',
            'thumbnailTemp' => '@phpunitTests/_runtime/thumbnails/temp',
            'md5salt' => 'test-salt',
        ],
    ],
];
