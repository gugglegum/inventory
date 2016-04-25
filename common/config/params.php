<?php
return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'user.passwordResetTokenExpire' => 3600,
    'photos' => [
        'storagePath' => dirname(dirname(__DIR__)) . '/photos',
        'storageTemp' => dirname(dirname(__DIR__)) . '/photos/temp',
        'storageRelativeUrl' => '/photos',
        'thumbnailPath' => dirname(dirname(__DIR__)) . '/thumbnails',
        'thumbnailTemp' => dirname(dirname(__DIR__)) . '/thumbnails/temp',
        'thumbnailRelativeUrl' => '/thumbnails',
        'resize' => [
            'width' => 1024,
            'height' => 1024,
            'upscale' => false,
            'crop' => false,
            'quality' => 90,
        ],
    ],
];
