<?php
return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'user.passwordResetTokenExpire' => 3600,
    'photos' => [
        'storagePath' => dirname(__DIR__, 2) . '/photos',
        'storageTemp' => dirname(__DIR__, 2) . '/photos/temp',
        'storageRelativeUrl' => '/photos',
        'thumbnailPath' => dirname(__DIR__, 2) . '/thumbnails',
        'thumbnailTemp' => dirname(__DIR__, 2) . '/thumbnails/temp',
        'thumbnailRelativeUrl' => '/thumbnails',
        'md5salt' => '',
        'resize' => [
            'width' => 1024,
            'height' => 1024,
            'upscale' => false,
            'crop' => false,
            'quality' => 90,
        ],
    ],
];
