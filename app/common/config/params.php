<?php

$passwordLoginEnabled = getenv('STOCKHUB_PASSWORD_LOGIN_ENABLED');
$ssoLoginEnabled = getenv('STOCKHUB_SSO_LOGIN_ENABLED');
$oidcIssuer = getenv('OIDC_ISSUER');
$oidcClientId = getenv('OIDC_CLIENT_ID');
$oidcClientSecret = getenv('OIDC_CLIENT_SECRET');
$oidcRedirectUri = getenv('OIDC_REDIRECT_URI');
$scopeString = getenv('OIDC_SCOPES');
$httpTimeout = getenv('OIDC_HTTP_TIMEOUT');
$clockSkewSeconds = getenv('OIDC_CLOCK_SKEW_SECONDS');

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

return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'user.passwordResetTokenExpire' => 3600,
    'auth' => [
        'passwordLoginEnabled' => $passwordLoginEnabled === false
            ? true
            : (bool) $passwordLoginEnabled,
        'ssoLoginEnabled' => (bool) $ssoLoginEnabled,
        'canonicalOrigin' => $canonicalOrigin,
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
