<?php

$passwordLoginEnabled = getenv('STOCKHUB_PASSWORD_LOGIN_ENABLED');
$ssoLoginEnabled = getenv('STOCKHUB_SSO_LOGIN_ENABLED');
$authSessionDurationSeconds = getenv('STOCKHUB_AUTH_SESSION_DURATION_SECONDS');
$oidcIssuer = getenv('OIDC_ISSUER');
$oidcClientId = getenv('OIDC_CLIENT_ID');
$oidcClientSecret = getenv('OIDC_CLIENT_SECRET');
$oidcRedirectUri = getenv('OIDC_REDIRECT_URI');
$scopeString = getenv('OIDC_SCOPES');
$httpTimeout = getenv('OIDC_HTTP_TIMEOUT');
$clockSkewSeconds = getenv('OIDC_CLOCK_SKEW_SECONDS');
$trustedProxiesString = getenv('TRUSTED_PROXIES');

$oidcRedirectUri = $oidcRedirectUri === false ? '' : $oidcRedirectUri;
$scopeString = $scopeString === false ? 'openid profile email' : $scopeString;
$scopes = preg_split('/\s+/', $scopeString, -1, PREG_SPLIT_NO_EMPTY);
$redirectParts = parse_url($oidcRedirectUri);
$redirectScheme = is_array($redirectParts) ? ($redirectParts['scheme'] ?? '') : '';
$redirectHost = is_array($redirectParts) ? ($redirectParts['host'] ?? '') : '';
$redirectPort = is_array($redirectParts) && isset($redirectParts['port'])
    ? ':' . $redirectParts['port']
    : '';
$redirectOrigin = $redirectScheme !== '' && $redirectHost !== ''
    ? $redirectScheme . '://' . $redirectHost . $redirectPort
    : '';
$canonicalOrigin = getenv('STOCKHUB_CANONICAL_ORIGIN');
$canonicalOrigin = $canonicalOrigin === false || $canonicalOrigin === ''
    ? $redirectOrigin
    : $canonicalOrigin;
$trustedProxies = $trustedProxiesString === false
    ? []
    : preg_split('/\s*,\s*/', trim($trustedProxiesString), -1, PREG_SPLIT_NO_EMPTY);

return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'user.passwordResetTokenExpire' => 3600,
    'trustedProxies' => $trustedProxies === false ? [] : $trustedProxies,
    'auth' => [
        'passwordLoginEnabled' => $passwordLoginEnabled === false
            ? true
            : (bool) $passwordLoginEnabled,
        'ssoLoginEnabled' => (bool) $ssoLoginEnabled,
        'canonicalOrigin' => $canonicalOrigin,
        'sessionDurationSeconds' => $authSessionDurationSeconds === false
            ? 86400 * 180
            : (int) $authSessionDurationSeconds,
    ],
    'oidc' => [
        'issuer' => $oidcIssuer === false ? '' : $oidcIssuer,
        'clientId' => $oidcClientId === false ? '' : $oidcClientId,
        'clientSecret' => $oidcClientSecret === false ? '' : $oidcClientSecret,
        'redirectUri' => $oidcRedirectUri,
        'scopes' => $scopes === false ? [] : $scopes,
        'httpTimeout' => $httpTimeout === false ? 10 : (int) $httpTimeout,
        'clockSkewSeconds' => $clockSkewSeconds === false ? 60 : (int) $clockSkewSeconds,
    ],
    'photos' => [
        'storagePath' => dirname(__DIR__, 2) . '/photos',
        'storageTemp' => dirname(__DIR__, 2) . '/photos/temp',
        'storageXAccelUrl' => '/_protected-photos',
        'thumbnailPath' => dirname(__DIR__, 2) . '/thumbnails',
        'thumbnailTemp' => dirname(__DIR__, 2) . '/thumbnails/temp',
        'thumbnailXAccelUrl' => '/_protected-thumbnails',
        'md5salt' => '',
        'maxUploadBytes' => 50 * 1024 * 1024,
        'maxUploadPixels' => 60_000_000,
        'maxFilesPerUploadSession' => 100,
        'maxTemporaryFilesPerUser' => 300,
        'maxOpenUploadSessionsPerUser' => 20,
        'resize' => [
            'width' => 1024,
            'height' => 1024,
            'upscale' => false,
            'crop' => false,
            'quality' => 90,
        ],
    ],
];
