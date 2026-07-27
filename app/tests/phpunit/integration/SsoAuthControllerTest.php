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

    private static OpenSSLAsymmetricKey $privateKey;

    /** @var array<string, mixed> */
    private static array $jwk;

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
    }

    protected function tearDown(): void
    {
        Yii::$container->clear(OidcProvider::class);
        parent::tearDown();
    }

    /**
     * Redirect содержит state, nonce и корректный S256 PKCE challenge.
     */
    public function testSsoLoginBuildsAuthorizationRequestAndStoresPendingState(): void
    {
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $this->setGetRequest([], '/auth/sso/redirect');

        $response = $controller->actionSsoLogin();

        self::assertSame(302, $response->statusCode);
        $location = $response->headers->get('Location');
        self::assertIsString($location);
        self::assertStringStartsWith(self::ISSUER . '/oauth/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $pending = Yii::$app->session->get('stockhub.oidc.pending');
        self::assertIsArray($pending);
        self::assertSame(self::CLIENT_ID, $query['client_id'] ?? null);
        self::assertSame(self::REDIRECT_URI, $query['redirect_uri'] ?? null);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('openid profile email', $query['scope'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
        self::assertSame($pending['state'], $query['state'] ?? null);
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

        $response = $controller->actionSsoCallback();

        self::assertSame(302, $response->statusCode);
        self::assertFalse(Yii::$app->user->isGuest);
        self::assertSame((int) $user->id, (int) Yii::$app->user->id);

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
     * State одноразовый: неверное значение дает 419 и удаляет pending flow до token exchange.
     */
    public function testCallbackRejectsInvalidStateAndConsumesPendingFlow(): void
    {
        $transport = $this->configuredTransport();
        $this->registerProvider($transport);
        $controller = $this->prepareController();
        $this->startAuthorization($controller);
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

        self::assertNull(Yii::$app->session->get('stockhub.oidc.pending'));
        self::assertSame([], $transport->postRequests);
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

        $pending = Yii::$app->session->get('stockhub.oidc.pending');
        self::assertIsArray($pending);
        self::assertIsString($pending['state'] ?? null);
        self::assertIsString($pending['nonce'] ?? null);
        self::assertIsString($pending['codeVerifier'] ?? null);
        self::assertIsInt($pending['createdAt'] ?? null);

        /** @var array{state:string,nonce:string,codeVerifier:string,createdAt:int} $pending */
        return $pending;
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
            'httpTimeout' => 5,
            'clockSkewSeconds' => 60,
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
