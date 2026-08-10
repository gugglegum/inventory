<?php

$config = [
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '',
        ],
    ],
];

if (YII_ENV_DEV && YII_DEBUG) {
    // Yii Debug intentionally stays disabled: its stored request snapshots may
    // contain cookies, session data, OIDC callback parameters and environment
    // secrets. Gii does not collect per-request diagnostic data.
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
