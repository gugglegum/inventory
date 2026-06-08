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
            'storagePath' => Yii::getAlias('@phpunitRuntime/photos'),
            'storageTemp' => Yii::getAlias('@phpunitRuntime/photos/temp'),
            'thumbnailPath' => Yii::getAlias('@phpunitRuntime/thumbnails'),
            'thumbnailTemp' => Yii::getAlias('@phpunitRuntime/thumbnails/temp'),
            'md5salt' => 'test-salt',
        ],
    ],
];
