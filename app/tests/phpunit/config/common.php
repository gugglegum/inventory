<?php

declare(strict_types=1);

$dbDsn = getenv('STOCKHUB_TEST_DB_DSN');
$dbUsername = getenv('STOCKHUB_TEST_DB_USERNAME');
$dbPassword = getenv('STOCKHUB_TEST_DB_PASSWORD');

return [
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => $dbDsn !== false && $dbDsn !== '' ? $dbDsn : 'mysql:host=db;dbname=stockhub_test',
            'username' => $dbUsername !== false && $dbUsername !== '' ? $dbUsername : 'stockhub',
            'password' => $dbPassword !== false && $dbPassword !== '' ? $dbPassword : 'stockhub',
            'charset' => 'utf8mb4',
        ],
        'mailer' => [
            'class' => yii\swiftmailer\Mailer::class,
            'viewPath' => '@common/mail',
            'useFileTransport' => true,
        ],
    ],
    'params' => [
        'auth' => [
            'passwordLoginEnabled' => true,
            'ssoLoginEnabled' => false,
            'canonicalOrigin' => 'https://stockhub.example.test',
        ],
        'oidc' => [
            'issuer' => 'https://sso.example.test',
            'clientId' => 'stockhub-test-client',
            'clientSecret' => 'stockhub-test-secret',
            'redirectUri' => 'https://stockhub.example.test/auth/sso/callback',
            'scopes' => ['openid', 'profile', 'email'],
            'httpTimeout' => 5,
            'clockSkewSeconds' => 60,
        ],
        'photos' => [
            'storagePath' => Yii::getAlias('@phpunitRuntime/photos'),
            'storageTemp' => Yii::getAlias('@phpunitRuntime/photos/temp'),
            'thumbnailPath' => Yii::getAlias('@phpunitRuntime/thumbnails'),
            'thumbnailTemp' => Yii::getAlias('@phpunitRuntime/thumbnails/temp'),
            'md5salt' => 'test-salt',
        ],
    ],
];
