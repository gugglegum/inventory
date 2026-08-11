<?php

$params = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../common/config/params.php'),
    require(__DIR__ . '/../../common/config/params-local.php'),
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);
$secureCookies = defined('YII_ENV_PROD') && YII_ENV_PROD;

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => (bool) ($params['auth']['ssoLoginEnabled'] ?? false)
        ? ['log', 'canonicalHost']
        : ['log'],
    'modules' => [],
    'defaultRoute' => 'repo/index',
    'components' => [
        'request' => [
            // Forwarded origin/client-IP headers are accepted only when the
            // immediate reverse proxy belongs to an explicitly trusted CIDR.
            'trustedHosts' => array_fill_keys(
                $params['trustedProxies'] ?? [],
                [
                    'X-Forwarded-For',
                    'X-Forwarded-Host',
                    'X-Forwarded-Proto',
                    'X-Forwarded-Port',
                ]
            ),
        ],
        'session' => [
            'cookieParams' => [
                'httpOnly' => true,
                'secure' => $secureCookies,
                'sameSite' => yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
        'canonicalHost' => [
            'class' => common\components\CanonicalHostRedirect::class,
            'canonicalOrigin' => $params['auth']['canonicalOrigin'] ?? '',
            'oidcRedirectUri' => $params['oidc']['redirectUri'] ?? '',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                '' => 'repo/index',
                'auth/sso/redirect' => 'site/sso-login',
                'auth/sso/callback' => 'site/sso-callback',
                'photo/<id:\d+>/thumbnail' => 'photo/thumbnail',
                'photo/<id:\d+>.jpg' => 'photo/view',
                'repo/<repoId:\d+>/items' => 'items/index',
                'repo/<repoId:\d+>/items/<itemId:\d+>' => 'items/view',
                'repo/<repoId:\d+>/items/<itemId:\d+>/json-preview' => 'items/json-preview',
                'repo/<repoId:\d+>/items/<itemId:\d+>/pick-container' => 'items/pick-container',
                'repo/<repoId:\d+>/items/<parentItemId:\d+>/create' => 'items/create',
                'repo/<repoId:\d+>/items/<itemId:\d+>/update' => 'items/update',
                'repo/<repoId:\d+>/items/search' => 'items/search',
                'repo/<repoId:\d+>/items/search-container' => 'items/search-container',
                'repo/<repoId:\d+>/items/<itemId:\d+>/delete' => 'items/delete',
                'repo/<repoId:\d+>/items/<parentItemId:\d+>/import' => 'items/import',
                'repo/create' => 'repo/create',
                'repo/<repoId:\d+>' => 'repo/view',
                'repo/<repoId:\d+>/update' => 'repo/update',
                'repo/<repoId:\d+>/delete' => 'repo/delete',
                'repo/<repoId:\d+>/items/<itemId:\d+>/posts' => 'posts/index',
                'repo/<repoId:\d+>/items/<itemId:\d+>/posts/create' => 'posts/create',
                'repo/<repoId:\d+>/items/<itemId:\d+>/posts/<postId:\d+>' => 'posts/view',
                'repo/<repoId:\d+>/items/<itemId:\d+>/posts/<postId:\d+>/edit' => 'posts/update',
                'repo/<repoId:\d+>/items/<itemId:\d+>/posts/<postId:\d+>/delete' => 'posts/delete',
                'repo/<repoId:\d+>/items/<itemId:\d+>/inventory' => 'inventory/index',
                'repo/<repoId:\d+>/items/<itemId:\d+>/inventory/create' => 'inventory/create',
                'repo/<repoId:\d+>/items/<itemId:\d+>/inventory/<inventoryId:\d+>' => 'inventory/view',
                'repo/<repoId:\d+>/items/<itemId:\d+>/inventory/<inventoryId:\d+>/delete' => 'inventory/delete',
            ],
        ],
        'user' => [
            'class' => common\components\WebUser::class,
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => (bool) ($params['auth']['passwordLoginEnabled'] ?? true),
            'identityCookie' => [
                'name' => '_identity',
                'httpOnly' => true,
                'secure' => $secureCookies,
                'sameSite' => yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
        'log' => [
            'traceLevel' => (defined('YII_DEBUG') && (bool) constant('YII_DEBUG')) ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    // Не добавляем request/session/server globals к ошибкам:
                    // в них находятся authorization code, cookies и OIDC client secret.
                    'logVars' => [],
                    'maskVars' => [
                        '_GET.code',
                        '_GET.state',
                        '_COOKIE',
                        '_SESSION',
                        '_SERVER.HTTP_AUTHORIZATION',
                        '_SERVER.PHP_AUTH_USER',
                        '_SERVER.PHP_AUTH_PW',
                        '_SERVER.*SECRET*',
                        '_ENV.*SECRET*',
                    ],
                    // Стандартный prefix Yii содержит session ID.
                    'prefix' => static fn (array $_message): string => '',
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
    ],
    'params' => $params,
];
