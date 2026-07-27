<?php

namespace backend\controllers;

use common\helpers\PostDataHelper;
use common\models\LoginForm;
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

/**
 * Site controller
 */
class SiteController extends Controller
{
    private const string OIDC_SESSION_KEY = 'stockhub.oidc.pending';

    private const int OIDC_PENDING_LIFETIME = 600;

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

        try {
            $provider = $this->createOidcProvider();
            $state = Yii::$app->security->generateRandomString(64);
            $nonce = Yii::$app->security->generateRandomString(64);
            $codeVerifier = $this->base64UrlEncode(random_bytes(64));
            $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));

            Yii::$app->session->set(self::OIDC_SESSION_KEY, [
                'state' => $state,
                'nonce' => $nonce,
                'codeVerifier' => $codeVerifier,
                'createdAt' => time(),
            ]);

            $query = http_build_query([
                'client_id' => $this->oidcRequiredString('clientId'),
                'redirect_uri' => $this->oidcRequiredString('redirectUri'),
                'response_type' => 'code',
                'scope' => implode(' ', $this->oidcScopes()),
                'state' => $state,
                'nonce' => $nonce,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
            ], '', '&', PHP_QUERY_RFC3986);

            return $this->redirect($provider->authorizationEndpoint() . '?' . $query);
        } catch (Throwable $exception) {
            $this->logSsoFailure('Unable to start OIDC authorization.', $exception);
            Yii::$app->session->remove(self::OIDC_SESSION_KEY);
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

        $pending = Yii::$app->session->remove(self::OIDC_SESSION_KEY);
        $state = Yii::$app->request->getQueryParam('state');

        if (!$this->isValidOidcState($pending, $state)) {
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

        try {
            /** @var array{nonce:string,codeVerifier:string} $pending */
            $provider = $this->createOidcProvider();
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
            $this->logSsoFailure('OIDC callback failed.', $exception);
            Yii::$app->session->setFlash(
                'error',
                'Не удалось войти через Pyrda SSO. Проверьте, что учётная запись связана со Stockhub.'
            );

            return $this->redirect(['site/login']);
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

    private function isPasswordLoginEnabled(): bool
    {
        return (bool) (Yii::$app->params['auth']['passwordLoginEnabled'] ?? true);
    }

    private function isSsoLoginEnabled(): bool
    {
        return (bool) (Yii::$app->params['auth']['ssoLoginEnabled'] ?? false);
    }

    /**
     * @return list<string>
     */
    private function oidcScopes(): array
    {
        $scopes = Yii::$app->params['oidc']['scopes'] ?? [];
        if (!is_array($scopes)) {
            throw new RuntimeException('OIDC scopes are not configured.');
        }

        $result = [];
        foreach ($scopes as $scope) {
            if (is_string($scope) && $scope !== '') {
                $result[] = $scope;
            }
        }

        if (!in_array('openid', $result, true)) {
            throw new RuntimeException('OIDC openid scope is required.');
        }

        return $result;
    }

    private function oidcRequiredString(string $key): string
    {
        $value = Yii::$app->params['oidc'][$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("OIDC configuration value {$key} is missing.");
        }

        return $value;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function isValidOidcState(mixed $pending, mixed $state): bool
    {
        if (!is_array($pending) || !is_string($state)) {
            return false;
        }

        $expectedState = $pending['state'] ?? null;
        $nonce = $pending['nonce'] ?? null;
        $codeVerifier = $pending['codeVerifier'] ?? null;
        $createdAt = $pending['createdAt'] ?? null;

        return is_string($expectedState)
            && is_string($nonce)
            && $nonce !== ''
            && is_string($codeVerifier)
            && $codeVerifier !== ''
            && is_int($createdAt)
            && $createdAt >= time() - self::OIDC_PENDING_LIFETIME
            && $createdAt <= time() + 60
            && hash_equals($expectedState, $state);
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
