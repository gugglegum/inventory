<?php

namespace backend\controllers;

use common\helpers\PostDataHelper;
use common\models\LoginForm;
use common\services\OidcFlowRateLimiter;
use common\services\OidcProvider;
use common\services\OidcTokenVerifier;
use common\services\SsoUserLinker;
use RuntimeException;
use Throwable;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ErrorAction;
use yii\web\HttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\TooManyRequestsHttpException;

/**
 * Site controller
 */
class SiteController extends Controller
{
    private const string OIDC_SESSION_KEY = 'stockhub.oidc.pending';

    private const int OIDC_PENDING_LIFETIME = 1200;

    private const int OIDC_PENDING_MAX_FLOWS = 5;

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['login', 'sso-login', 'sso-callback', 'error'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['logout', 'index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'login' => ['get', 'post'],
                    'sso-login' => ['get'],
                    'sso-callback' => ['get'],
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
        ];
    }

    /**
     * @return Response|string
     */
    public function actionLogin(): Response|string
    {
        if (!\Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        if (Yii::$app->request->getIsPost() && !$this->isPasswordLoginEnabled()) {
            throw new MethodNotAllowedHttpException('Password login is disabled.');
        }

        $model = new LoginForm();
        if ($model->load(PostDataHelper::toArray(Yii::$app->request->post())) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Начинает OIDC authorization code flow с обязательным S256 PKCE.
     */
    public function actionSsoLogin(): Response
    {
        if (!$this->isSsoLoginEnabled()) {
            throw new NotFoundHttpException('SSO login is disabled.');
        }

        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->consumeOidcAuthorizationStartQuota();

        try {
            $provider = $this->createOidcProvider();
            $authorizationEndpoint = $provider->authorizationEndpoint();
            $state = Yii::$app->security->generateRandomString(64);
            $nonce = Yii::$app->security->generateRandomString(64);
            $codeVerifier = $this->base64UrlEncode(random_bytes(64));
            $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));

            $query = http_build_query([
                'client_id' => $provider->clientId(),
                'redirect_uri' => $provider->redirectUri(),
                'response_type' => 'code',
                'scope' => implode(' ', $provider->scopes()),
                'state' => $state,
                'nonce' => $nonce,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
            ], '', '&', PHP_QUERY_RFC3986);

            $this->storePendingOidcFlow($state, $nonce, $codeVerifier);

            return $this->redirect($authorizationEndpoint . '?' . $query);
        } catch (Throwable $exception) {
            $this->logSsoFailure('Unable to start OIDC authorization.', $exception);
            Yii::$app->session->setFlash('error', 'Не удалось начать вход через Pyrda SSO.');

            return $this->redirect(['site/login']);
        }
    }

    /**
     * Завершает OIDC flow, проверяет id_token и авторизует связанного локального пользователя.
     *
     * @throws HttpException
     */
    public function actionSsoCallback(): Response
    {
        if (!$this->isSsoLoginEnabled()) {
            throw new NotFoundHttpException('SSO login is disabled.');
        }

        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $state = Yii::$app->request->getQueryParam('state');

        if (!is_string($state) || $state === '') {
            throw new HttpException(419, 'Invalid OIDC state.');
        }

        $pending = $this->takePendingOidcFlow($state);
        if ($pending === null) {
            throw new HttpException(419, 'Invalid OIDC state.');
        }

        $error = Yii::$app->request->getQueryParam('error');
        if (is_string($error) && $error !== '') {
            Yii::$app->session->setFlash('error', 'Pyrda SSO отклонил запрос авторизации.');

            return $this->redirect(['site/login']);
        }

        $code = Yii::$app->request->getQueryParam('code');
        if (!is_string($code) || $code === '') {
            Yii::$app->session->setFlash('error', 'Pyrda SSO не вернул код авторизации.');

            return $this->redirect(['site/login']);
        }

        // Резервируем квоту до discovery: накопленные state не должны позволять
        // burst-ом занять PHP-FPM workers исходящими запросами к недоступному SSO.
        $this->consumeOidcTokenExchangeQuota();

        try {
            $provider = $this->createOidcProvider();
            $provider->discovery();
            $tokens = $provider->exchangeCode($code, $pending['codeVerifier']);
            $idToken = $tokens['id_token'] ?? null;

            if (!is_string($idToken) || $idToken === '') {
                throw new RuntimeException('OIDC token response does not contain id_token.');
            }

            $claims = (new OidcTokenVerifier($provider))->verify($idToken, $pending['nonce']);
            $user = (new SsoUserLinker())->link($claims);

            if (!Yii::$app->user->login($user)) {
                throw new RuntimeException('Yii user login failed.');
            }

            return $this->goBack();
        } catch (Throwable $exception) {
            return $this->ssoCallbackFailureResponse($exception);
        }
    }

    /**
     * @return Response|string
     */
    public function actionLogout(): Response|string
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Создает provider через DI-контейнер, чтобы HTTP transport можно было подменить в тестах.
     */
    protected function createOidcProvider(): OidcProvider
    {
        $provider = Yii::$container->get(OidcProvider::class);
        if (!$provider instanceof OidcProvider) {
            throw new RuntimeException('OIDC provider is not configured.');
        }

        return $provider;
    }

    /**
     * Создает общий для deployment limiter через DI-контейнер.
     */
    protected function createOidcFlowRateLimiter(): OidcFlowRateLimiter
    {
        $limiter = Yii::$container->get(OidcFlowRateLimiter::class);
        if (!$limiter instanceof OidcFlowRateLimiter) {
            throw new RuntimeException('OIDC flow rate limiter is not configured.');
        }

        return $limiter;
    }

    private function isPasswordLoginEnabled(): bool
    {
        return (bool) (Yii::$app->params['auth']['passwordLoginEnabled'] ?? true);
    }

    private function isSsoLoginEnabled(): bool
    {
        return (bool) (Yii::$app->params['auth']['ssoLoginEnabled'] ?? false);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function storePendingOidcFlow(
        string $state,
        string $nonce,
        string $codeVerifier,
    ): void {
        $pendingFlows = $this->validPendingOidcFlows(
            Yii::$app->session->get(self::OIDC_SESSION_KEY),
        );

        while (count($pendingFlows) >= self::OIDC_PENDING_MAX_FLOWS) {
            array_shift($pendingFlows);
        }

        $pendingFlows[$state] = [
            'nonce' => $nonce,
            'codeVerifier' => $codeVerifier,
            'createdAt' => time(),
        ];
        Yii::$app->session->set(self::OIDC_SESSION_KEY, $pendingFlows);
    }

    /**
     * @return array{nonce:string,codeVerifier:string,createdAt:int}|null
     */
    private function takePendingOidcFlow(string $state): ?array
    {
        $pendingFlows = $this->validPendingOidcFlows(
            Yii::$app->session->get(self::OIDC_SESSION_KEY),
        );
        $pending = $pendingFlows[$state] ?? null;

        if ($pending === null) {
            $this->savePendingOidcFlows($pendingFlows);

            return null;
        }

        unset($pendingFlows[$state]);
        $this->savePendingOidcFlows($pendingFlows);

        return $pending;
    }

    /**
     * @return array<string, array{nonce:string,codeVerifier:string,createdAt:int}>
     */
    private function validPendingOidcFlows(mixed $pendingFlows): array
    {
        if (!is_array($pendingFlows)) {
            return [];
        }

        $now = time();
        $validFlows = [];

        foreach ($pendingFlows as $state => $pending) {
            if (!is_string($state) || !is_array($pending)) {
                continue;
            }

            $nonce = $pending['nonce'] ?? null;
            $codeVerifier = $pending['codeVerifier'] ?? null;
            $createdAt = $pending['createdAt'] ?? null;

            if (
                is_string($nonce)
                && $nonce !== ''
                && is_string($codeVerifier)
                && $codeVerifier !== ''
                && is_int($createdAt)
                && $createdAt >= $now - self::OIDC_PENDING_LIFETIME
                && $createdAt <= $now + 60
            ) {
                $validFlows[$state] = [
                    'nonce' => $nonce,
                    'codeVerifier' => $codeVerifier,
                    'createdAt' => $createdAt,
                ];
            }
        }

        return $validFlows;
    }

    /**
     * @param array<string, array{nonce:string,codeVerifier:string,createdAt:int}> $pendingFlows
     */
    private function savePendingOidcFlows(array $pendingFlows): void
    {
        if ($pendingFlows === []) {
            Yii::$app->session->remove(self::OIDC_SESSION_KEY);

            return;
        }

        Yii::$app->session->set(self::OIDC_SESSION_KEY, $pendingFlows);
    }

    /**
     * Ограничивает discovery-запросы до обращения к OIDC provider.
     */
    private function consumeOidcAuthorizationStartQuota(): void
    {
        try {
            $allowed = $this->createOidcFlowRateLimiter()->consumeAuthorizationStart(
                $this->oidcClientIp(),
            );
        } catch (Throwable $exception) {
            $this->throwOidcRateLimitUnavailable($exception);
        }

        if (!$allowed) {
            throw new TooManyRequestsHttpException('Too many SSO authorization attempts.');
        }
    }

    /**
     * Резервирует локальную квоту до callback discovery и последующего /oauth/token.
     */
    private function consumeOidcTokenExchangeQuota(): void
    {
        try {
            $allowed = $this->createOidcFlowRateLimiter()->consumeTokenExchange(
                $this->oidcClientIp(),
                $this->oidcHttpTimeout(),
            );
        } catch (Throwable $exception) {
            $this->throwOidcRateLimitUnavailable($exception);
        }

        if (!$allowed) {
            throw new TooManyRequestsHttpException('Too many SSO login attempts.');
        }
    }

    private function oidcClientIp(): string
    {
        $clientIp = Yii::$app->request->getUserIP();
        if (!is_string($clientIp) || @inet_pton($clientIp) === false) {
            throw new RuntimeException('OIDC client IP address is unavailable.');
        }

        return $clientIp;
    }

    private function oidcHttpTimeout(): int
    {
        $httpTimeout = Yii::$app->params['oidc']['httpTimeout'] ?? null;
        if (!is_int($httpTimeout) || $httpTimeout < 1) {
            throw new RuntimeException('OIDC HTTP timeout is not configured.');
        }

        return $httpTimeout;
    }

    private function throwOidcRateLimitUnavailable(Throwable $exception): never
    {
        $this->logSsoFailure('OIDC rate limiter failed.', $exception);

        throw new HttpException(
            503,
            'SSO login is temporarily unavailable.',
            0,
            $exception,
        );
    }

    private function ssoCallbackFailureResponse(Throwable $exception): Response
    {
        $this->logSsoFailure('OIDC callback failed.', $exception);
        Yii::$app->session->setFlash(
            'error',
            'Не удалось войти через Pyrda SSO. Проверьте, что учётная запись связана со Stockhub.'
        );

        return $this->redirect(['site/login']);
    }

    private function logSsoFailure(string $message, Throwable $exception): void
    {
        Yii::error([
            'message' => $message,
            'exception' => $exception::class,
            'error' => $exception->getMessage(),
        ], __METHOD__);
    }
}
