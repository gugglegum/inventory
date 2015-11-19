<?php
return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'user.passwordResetTokenExpire' => 3600,
    'photos' => [
        'storagePath' => dirname(dirname(__DIR__)) . '/photos',
        'storageRelativeUrl' => 'photos',
        'resize' => [
            'width' => 1024,
            'height' => 1024,
            'antiAliasing' => true,
            'upscale' => false,
            'crop' => false,
            'quality' => 90,
        ],
    ],
];
