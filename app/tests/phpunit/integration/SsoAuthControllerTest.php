<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\SiteController;
use common\models\User;
use common\services\OidcProvider;
use OpenSSLAsymmetricKey;
use RuntimeException;
use tests\phpunit\DbTestCase;
use tests\phpunit\unit\FakeOidcHttpTransport;
use Yii;
use yii\web\Cookie;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Сквозные integration-тесты browser-части OIDC authorization code flow.
 */
final class SsoAuthControllerTest extends DbTestCase
{
    private const string ISSUER = 'https://sso.example.test';

    private const string CLIENT_ID = 'stockhub-client';

    private const string CLIENT_SECRET = 'stockhub-secret';

    private const string REDIRECT_URI = 'https://stockhub.example.test/auth/sso/callback';

    private const string KEY_ID = 'stockhub-test-key';

    private const int HTTP_TIMEOUT = 5;

    private static OpenSSLAsymmetricKey $privateKey;

    /** @var array<string, mixed> */
    private static array $jwk;

    private bool $hadRemoteAddress;

    private mixed $originalRemoteAddress;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$privateKey instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('Could not generate RSA key for SSO controller tests.');
        }

        $details = openssl_pkey_get_details($privateKey);
        $rsa = is_array($details) ? ($details['rsa'] ?? null) : null;
        $modulus = is_array($rsa) ? ($rsa['n'] ?? null) : null;
        $exponent = is_array($rsa) ? ($rsa['e'] ?? null) : null;
        if (!is_string($modulus) || !is_string($exponent)) {
            throw new RuntimeException('Generated RSA key has no public parameters.');
        }

        self::$privateKey = $privateKey;
        self::$jwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'key_ops' => ['verify'],
            'alg' => 'RS256',
            'kid' => self::KEY_ID,
            'n' => self::base64UrlEncode($modulus),
            'e' => self::base64UrlEncode($exponent),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->params['auth']['ssoLoginEnabled'] = true;
        Yii::$app->params['oidc'] = $this->oidcConfig();
        Yii::$app->session->remove('stockhub.oidc.pending');
        $this->hadRemoteAddress = array_key_exists('REMOTE_ADDR', $_SERVER);
        $this->originalRemoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    }

    protected function tearDown(): void
    {
        Yii::$container->clear(OidcProvider::class);
        if ($this->hadRemoteAddress) {
            $_SERVER['REMOTE_ADDR'] = $this->originalRemoteAddress;
        } else {
            unset($_SERVER['REMOTE_ADDR']);
        }
        parent::tearDown();
    }

    /**
     * Redirect содержит state, nonce и корректный S256 PKCE challenge.
     */
    public function testSsoLoginBuildsAuthorizationRequestAndStoresPendingState(): void
    {
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        // Authorize request должен брать тот же проверенный snapshot, что и
        // token exchange, а не перечитывать потенциально отличающиеся params.
        Yii::$app->params['oidc']['clientId'] = 'raw-client-id ';
        Yii::$app->params['oidc']['redirectUri'] = 'https://raw.example.test/callback ';
        Yii::$app->params['oidc']['scopes'] = ['openid', 'raw'];
        $controller = $this->prepareController();
        $this->setGetRequest([], '/auth/sso/redirect');

        $response = $controller->actionSsoLogin();

        self::assertSame(302, $response->statusCode);
        $location = $response->headers->get('Location');
        self::assertIsString($location);
        self::assertStringStartsWith(self::ISSUER . '/oauth/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        self::assertIsString($state);
        $pendingFlows = $this->pendingFlows();
        $pending = $pendingFlows[$state] ?? null;
        self::assertIsArray($pending);
        self::assertSame(self::CLIENT_ID, $query['client_id'] ?? null);
        self::assertSame(self::REDIRECT_URI, $query['redirect_uri'] ?? null);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('openid profile email', $query['scope'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
        self::assertSame($pending['nonce'], $query['nonce'] ?? null);

        $codeVerifier = $pending['codeVerifier'] ?? null;
        self::assertIsString($codeVerifier);
        self::assertSame(
            self::base64UrlEncode(hash('sha256', $codeVerifier, true)),
            $query['code_challenge'] ?? null,
        );
    }

    /**
     * Выключенный SSO endpoint не начинает authorization flow.
     */
    public function testSsoLoginIsUnavailableWhenDisabled(): void
    {
        Yii::$app->params['auth']['ssoLoginEnabled'] = false;
        Yii::$app->session->remove('stockhub.oidc.pending');
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $this->setGetRequest([], '/auth/sso/redirect');

        try {
            $controller->actionSsoLogin();
            self::fail('Disabled SSO login endpoint was accepted.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame(404, $exception->statusCode);
        }

        self::assertNull(Yii::$app->session->get('stockhub.oidc.pending'));
        self::assertSame([], $transport->getRequests);
        self::assertSame([], $transport->postRequests);
    }

    /**
     * Выключенный SSO callback не обрабатывает пришедшие параметры.
     */
    public function testSsoCallbackIsUnavailableWhenDisabled(): void
    {
        Yii::$app->params['auth']['ssoLoginEnabled'] = false;
        $pending = [
            'state' => 'pending-state',
            'nonce' => 'pending-nonce',
            'codeVerifier' => 'pending-verifier',
            'createdAt' => time(),
        ];
        Yii::$app->session->set('stockhub.oidc.pending', $pending);
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $this->setGetRequest([
            'code' => 'unused-code',
            'state' => 'unused-state',
        ], '/auth/sso/callback');

        try {
            $controller->actionSsoCallback();
            self::fail('Disabled SSO callback endpoint was accepted.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame(404, $exception->statusCode);
        }

        self::assertSame($pending, Yii::$app->session->get('stockhub.oidc.pending'));
        self::assertSame([], $transport->getRequests);
        self::assertSame([], $transport->postRequests);
    }

    /**
     * OpenID-only callback без email claims привязывает пользователя и открывает Yii-сессию.
     */
    public function testValidOpenidOnlyCallbackLinksExistingUserAndLogsIn(): void
    {
        Yii::$app->params['oidc']['scopes'] = ['openid'];
        $user = $this->createUser([
            'username' => 'existing-stockhub-user',
            'email' => 'user@example.test',
            'password' => 'legacy-password',
            'access' => User::ACCESS_MANAGE_USERS,
        ]);
        $user->updateAttributes([
            'ssoIssuer' => self::ISSUER,
            'ssoSubject' => 'sso-subject-42',
        ]);
        $originalPasswordHash = $user->passwordHash;
        $originalAuthKey = $user->authKey;
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();

        $pending = $this->startAuthorization($controller);
        $transport->respondToPost(self::ISSUER . '/oauth/token', [
            'token_type' => 'Bearer',
            'access_token' => 'access-token',
            'id_token' => $this->idToken([
                'sub' => 'sso-subject-42',
                'nonce' => $pending['nonce'],
            ]),
        ]);
        $transport->respondToGet(self::ISSUER . '/oauth/jwks', ['keys' => [self::$jwk]]);
        $this->setGetRequest([
            'code' => 'one-time-code',
            'state' => $pending['state'],
        ], '/auth/sso/callback');
        Yii::$app->user->enableAutoLogin = true;
        $loginStartedAt = time();

        $response = $controller->actionSsoCallback();

        self::assertSame(302, $response->statusCode);
        self::assertFalse(Yii::$app->user->isGuest);
        self::assertSame((int) $user->id, (int) Yii::$app->user->id);

        $loginDuration = (int) Yii::$app->params['auth']['sessionDurationSeconds'];
        $identityCookie = Yii::$app->response->cookies->get(Yii::$app->user->identityCookie['name']);
        self::assertInstanceOf(Cookie::class, $identityCookie);
        self::assertGreaterThanOrEqual($loginStartedAt + $loginDuration, $identityCookie->expire);
        self::assertLessThanOrEqual(time() + $loginDuration, $identityCookie->expire);

        $user->refresh();
        self::assertSame(self::ISSUER, $user->ssoIssuer);
        self::assertSame('sso-subject-42', $user->ssoSubject);
        self::assertSame($originalPasswordHash, $user->passwordHash);
        self::assertSame($originalAuthKey, $user->authKey);
        self::assertSame(User::ACCESS_MANAGE_USERS, $user->access);
        self::assertTrue($user->validatePassword('legacy-password'));
    }

    /**
     * Проверенный SSO-пользователь без локальной учетной записи не создается автоматически.
     */
    public function testCallbackRejectsUnknownLocalUserWithoutCreatingIt(): void
    {
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $pending = $this->startAuthorization($controller);
        $usersBefore = User::find()->count();
        $transport->respondToPost(self::ISSUER . '/oauth/token', [
            'id_token' => $this->idToken([
                'sub' => 'unknown-subject',
                'email' => 'unknown@example.test',
                'email_verified' => true,
                'nonce' => $pending['nonce'],
            ]),
        ]);
        $transport->respondToGet(self::ISSUER . '/oauth/jwks', ['keys' => [self::$jwk]]);
        $this->setGetRequest([
            'code' => 'one-time-code',
            'state' => $pending['state'],
        ], '/auth/sso/callback');

        $response = $controller->actionSsoCallback();

        self::assertSame(302, $response->statusCode);
        self::assertTrue(Yii::$app->user->isGuest);
        self::assertSame($usersBefore, User::find()->count());
        self::assertNotNull(Yii::$app->session->getFlash('error', null, false));
    }

    /**
     * Неизвестный state не может удалить действующие flows из других вкладок.
     */
    public function testCallbackRejectsInvalidStateWithoutConsumingPendingFlows(): void
    {
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $firstPending = $this->startAuthorization($controller);
        $secondPending = $this->startAuthorization($controller);
        $this->setGetRequest([
            'code' => 'one-time-code',
            'state' => 'wrong-state',
        ], '/auth/sso/callback');

        try {
            $controller->actionSsoCallback();
            self::fail('Invalid OIDC state was accepted.');
        } catch (HttpException $exception) {
            self::assertSame(419, $exception->statusCode);
        }

        $pendingFlows = $this->pendingFlows();
        self::assertArrayHasKey($firstPending['state'], $pendingFlows);
        self::assertArrayHasKey($secondPending['state'], $pendingFlows);
        self::assertSame([], $transport->postRequests);
    }

    /**
     * Истекший flow дает 419, а свежий flow из другой вкладки сохраняется.
     */
    public function testExpiredAuthorizationFlowDoesNotConsumeFreshFlow(): void
    {
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $expiredPending = $this->startAuthorization($controller);
        $freshPending = $this->startAuthorization($controller);
        $pendingFlows = $this->pendingFlows();
        $pendingFlows[$expiredPending['state']]['createdAt'] = time() - 1201;
        Yii::$app->session->set('stockhub.oidc.pending', $pendingFlows);
        $this->setGetRequest([
            'code' => 'expired-code',
            'state' => $expiredPending['state'],
        ], '/auth/sso/callback');

        try {
            $controller->actionSsoCallback();
            self::fail('Expired OIDC state was accepted.');
        } catch (HttpException $exception) {
            self::assertSame(419, $exception->statusCode);
        }

        $storedFlows = $this->pendingFlows();
        self::assertArrayNotHasKey($expiredPending['state'], $storedFlows);
        self::assertArrayHasKey($freshPending['state'], $storedFlows);
        self::assertSame([], $transport->postRequests);
    }

    /**
     * Flow остаётся действительным почти двадцать минут, нужных на первый вход и настройку TOTP.
     */
    public function testAuthorizationFlowAllowsLongFirstLogin(): void
    {
        $transport = $this->configuredTransport();
        $transport->respondToPost(self::ISSUER . '/oauth/token', []);
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $pending = $this->startAuthorization($controller);
        $pendingFlows = $this->pendingFlows();
        $pendingFlows[$pending['state']]['createdAt'] = time() - 1190;
        Yii::$app->session->set('stockhub.oidc.pending', $pendingFlows);
        $this->setGetRequest([
            'code' => 'long-first-login-code',
            'state' => $pending['state'],
        ], '/auth/sso/callback');

        $response = $controller->actionSsoCallback();

        self::assertSame(302, $response->statusCode);
        self::assertCount(1, $transport->postRequests);
        self::assertNull(Yii::$app->session->get('stockhub.oidc.pending'));
    }

    /**
     * Две вкладки хранят независимые state/nonce/verifier и расходуются по одной.
     */
    public function testParallelAuthorizationFlowsDoNotOverwriteEachOther(): void
    {
        $transport = $this->configuredTransport();
        $transport->respondToPost(self::ISSUER . '/oauth/token', []);
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $firstPending = $this->startAuthorization($controller);
        $secondPending = $this->startAuthorization($controller);

        self::assertCount(2, $this->pendingFlows());

        $this->setGetRequest([
            'code' => 'first-code',
            'state' => $firstPending['state'],
        ], '/auth/sso/callback');
        $controller->actionSsoCallback();

        $pendingFlows = $this->pendingFlows();
        self::assertArrayNotHasKey($firstPending['state'], $pendingFlows);
        self::assertArrayHasKey($secondPending['state'], $pendingFlows);
        self::assertSame(
            $firstPending['codeVerifier'],
            $transport->postRequests[0]['formData']['code_verifier'] ?? null,
        );

        $this->setGetRequest([
            'code' => 'second-code',
            'state' => $secondPending['state'],
        ], '/auth/sso/callback');
        $controller->actionSsoCallback();

        self::assertNull(Yii::$app->session->get('stockhub.oidc.pending'));
        self::assertSame(
            $secondPending['codeVerifier'],
            $transport->postRequests[1]['formData']['code_verifier'] ?? null,
        );
    }

    /**
     * Pending storage ограничен пятью свежими flows; самый старый вытесняется.
     */
    public function testPendingAuthorizationStorageIsBounded(): void
    {
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $pendingFlows = [];

        for ($index = 0; $index < 6; $index++) {
            $pendingFlows[] = $this->startAuthorization($controller);
        }

        $storedFlows = $this->pendingFlows();
        self::assertCount(5, $storedFlows);
        self::assertArrayNotHasKey($pendingFlows[0]['state'], $storedFlows);
        self::assertArrayHasKey($pendingFlows[5]['state'], $storedFlows);
    }

    /**
     * Повторные успешные входы и выходы не ограничиваются локальной IP-квотой.
     */
    public function testRepeatedSuccessfulLoginsAreNotLocallyRateLimited(): void
    {
        $user = $this->createUser();
        $user->updateAttributes([
            'ssoIssuer' => self::ISSUER,
            'ssoSubject' => 'repeated-login-user',
        ]);
        $transport = $this->configuredTransport();
        $transport->respondToGet(self::ISSUER . '/oauth/jwks', ['keys' => [self::$jwk]]);
        $this->registerProvider($transport);
        $controller = $this->prepareController();

        for ($index = 0; $index < 12; $index++) {
            $pending = $this->startAuthorization($controller);
            $transport->respondToPost(self::ISSUER . '/oauth/token', [
                'token_type' => 'Bearer',
                'access_token' => 'access-token',
                'id_token' => $this->idToken([
                    'sub' => 'repeated-login-user',
                    'nonce' => $pending['nonce'],
                ]),
            ]);
            $this->setGetRequest([
                'code' => 'code-' . $index,
                'state' => $pending['state'],
            ], '/auth/sso/callback');

            self::assertSame(302, $controller->actionSsoCallback()->statusCode);
            self::assertSame($user->id, Yii::$app->user->id);
            $controller->actionLogout();
            self::assertTrue(Yii::$app->user->isGuest);
        }

        self::assertCount(12, $transport->postRequests);
    }

    /**
     * @return array{state:string,nonce:string,codeVerifier:string,createdAt:int}
     */
    private function startAuthorization(SiteController $controller): array
    {
        $this->setGetRequest([], '/auth/sso/redirect');
        $response = $controller->actionSsoLogin();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);

        $location = $response->headers->get('Location');
        self::assertIsString($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        self::assertIsString($state);
        $pending = $this->pendingFlows()[$state] ?? null;
        self::assertIsArray($pending);
        self::assertIsString($pending['nonce'] ?? null);
        self::assertIsString($pending['codeVerifier'] ?? null);
        self::assertIsInt($pending['createdAt'] ?? null);

        return [
            'state' => $state,
            'nonce' => $pending['nonce'],
            'codeVerifier' => $pending['codeVerifier'],
            'createdAt' => $pending['createdAt'],
        ];
    }

    /**
     * @return array<string, array{nonce:string,codeVerifier:string,createdAt:int}>
     */
    private function pendingFlows(): array
    {
        $pendingFlows = Yii::$app->session->get('stockhub.oidc.pending');
        self::assertIsArray($pendingFlows);

        /** @var array<string, array{nonce:string,codeVerifier:string,createdAt:int}> $pendingFlows */
        return $pendingFlows;
    }

    private function configuredTransport(): FakeOidcHttpTransport
    {
        $transport = new FakeOidcHttpTransport();
        $transport->respondToGet(
            self::ISSUER . '/.well-known/openid-configuration',
            [
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER . '/oauth/authorize',
                'token_endpoint' => self::ISSUER . '/oauth/token',
                'jwks_uri' => self::ISSUER . '/oauth/jwks',
            ],
        );

        return $transport;
    }

    private function registerProvider(FakeOidcHttpTransport $transport): void
    {
        Yii::$container->setSingleton(
            OidcProvider::class,
            new OidcProvider($this->oidcConfig(), $transport),
        );
    }

    private function prepareController(): SiteController
    {
        $controller = new SiteController('site', Yii::$app);
        Yii::$app->controller = $controller;

        return $controller;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function idToken(array $overrides): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'sub' => 'sso-user',
            'nonce' => 'nonce',
            'iat' => $now - 10,
            'nbf' => $now - 10,
            'exp' => $now + 300,
        ], $overrides);
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => self::KEY_ID,
        ];
        $encodedHeader = self::base64UrlEncode(
            json_encode($header, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $encodedClaims = self::base64UrlEncode(
            json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $signedPayload = $encodedHeader . '.' . $encodedClaims;
        $signature = '';

        if (!openssl_sign($signedPayload, $signature, self::$privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign SSO controller test token.');
        }

        return $signedPayload . '.' . self::base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    private function oidcConfig(): array
    {
        return [
            'issuer' => self::ISSUER,
            'clientId' => self::CLIENT_ID,
            'clientSecret' => self::CLIENT_SECRET,
            'redirectUri' => self::REDIRECT_URI,
            'scopes' => ['openid', 'profile', 'email'],
            'httpTimeout' => self::HTTP_TIMEOUT,
            'clockSkewSeconds' => 60,
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
