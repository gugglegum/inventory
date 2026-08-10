<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\SiteController;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\log\Dispatcher;
use yii\log\FileTarget;
use yii\web\Cookie;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * Integration-тесты auth-сценариев SiteController.
 *
 * Проверяют успешный вход, неуспешный вход и выход пользователя.
 */
final class SiteControllerTest extends DbTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->params['auth']['passwordLoginEnabled'] = true;
        Yii::$app->params['auth']['ssoLoginEnabled'] = true;
    }

    /**
     * POST login авторизует пользователя с корректным паролем.
     */
    public function testLoginPostAuthenticatesUserAndRedirectsBack(): void
    {
        $user = $this->createUser([
            'username' => 'login_user',
            'password' => 'secret123',
        ]);
        Yii::$app->user->logout();
        $controller = $this->prepareController();

        $this->setPostRequest([
            'LoginForm' => [
                'username' => 'login_user',
                'password' => 'secret123',
                'rememberMe' => '0',
            ],
        ], [], '/site/login');

        $response = $controller->actionLogin();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertFalse(Yii::$app->user->isGuest);
        self::assertSame((int) $user->id, (int) Yii::$app->user->id);
    }

    /**
     * POST login с неверным паролем оставляет пользователя гостем и рендерит форму с ошибкой.
     */
    public function testLoginPostWithWrongPasswordRendersLoginForm(): void
    {
        $this->createUser([
            'username' => 'wrong_password_user',
            'password' => 'secret123',
        ]);
        Yii::$app->user->logout();
        $controller = $this->prepareController();

        $this->setPostRequest([
            'LoginForm' => [
                'username' => 'wrong_password_user',
                'password' => 'wrong-password',
                'rememberMe' => '0',
            ],
        ], [], '/site/login');

        $response = $controller->actionLogin();

        self::assertIsString($response);
        self::assertTrue(Yii::$app->user->isGuest);
        self::assertStringContainsString('Incorrect username or password.', $response);
        self::assertStringContainsString('Используйте для входа учётную запись pyrda.ru.', $response);
        self::assertStringContainsString('Войти через Pyrda SSO', $response);
        self::assertStringContainsString('Войти по паролю', $response);
        self::assertStringNotContainsString('Временный вход', $response);
        self::assertStringNotContainsString('на время перехода', $response);
    }

    /**
     * GET login оставляет SSO доступным, но скрывает парольную форму после выключения fallback.
     */
    public function testLoginGetHidesPasswordFormWhenPasswordLoginIsDisabled(): void
    {
        Yii::$app->params['auth']['passwordLoginEnabled'] = false;
        $controller = $this->prepareController();
        $this->setGetRequest([], '/site/login');

        $response = $controller->actionLogin();

        self::assertIsString($response);
        self::assertStringContainsString('Войти через Pyrda SSO', $response);
        self::assertStringNotContainsString('Войти по паролю', $response);
        self::assertStringNotContainsString('name="LoginForm[password]"', $response);
    }

    /**
     * Скрытие формы сопровождается серверным запретом старого POST endpoint.
     */
    public function testLoginPostIsRejectedWhenPasswordLoginIsDisabled(): void
    {
        Yii::$app->params['auth']['passwordLoginEnabled'] = false;
        $controller = $this->prepareController();
        $this->setPostRequest([
            'LoginForm' => [
                'username' => 'legacy-user',
                'password' => 'legacy-password',
                'rememberMe' => '0',
            ],
        ], [], '/site/login');

        $this->expectException(MethodNotAllowedHttpException::class);
        $controller->actionLogin();
    }

    /**
     * Выключение SSO скрывает только SSO и оставляет парольную форму.
     */
    public function testLoginGetHidesSsoWhenSsoLoginIsDisabled(): void
    {
        Yii::$app->params['auth']['ssoLoginEnabled'] = false;
        $controller = $this->prepareController();
        $this->setGetRequest([], '/site/login');

        $response = $controller->actionLogin();

        self::assertIsString($response);
        self::assertStringNotContainsString('Войти через Pyrda SSO', $response);
        self::assertStringNotContainsString('Используйте для входа учётную запись pyrda.ru.', $response);
        self::assertStringContainsString('Вход по паролю', $response);
        self::assertStringContainsString('name="LoginForm[password]"', $response);
    }

    /**
     * При выключении обоих способов страница не предлагает авторизацию.
     */
    public function testLoginGetShowsNoMethodsWhenAllLoginMethodsAreDisabled(): void
    {
        Yii::$app->params['auth']['passwordLoginEnabled'] = false;
        Yii::$app->params['auth']['ssoLoginEnabled'] = false;
        $controller = $this->prepareController();
        $this->setGetRequest([], '/site/login');

        $response = $controller->actionLogin();

        self::assertIsString($response);
        self::assertStringNotContainsString('Войти через Pyrda SSO', $response);
        self::assertStringNotContainsString('Вход по паролю', $response);
        self::assertStringNotContainsString('name="LoginForm[password]"', $response);
    }

    /**
     * Backend logger не должен добавлять globals или session ID к SSO-ошибкам.
     */
    public function testLogTargetDoesNotCaptureSensitiveRequestContext(): void
    {
        $dispatcher = Yii::$app->get('log');
        self::assertInstanceOf(Dispatcher::class, $dispatcher);

        $target = reset($dispatcher->targets);
        self::assertInstanceOf(FileTarget::class, $target);
        self::assertSame([], $target->logVars);
        self::assertIsCallable($target->prefix);
        self::assertSame('', ($target->prefix)([]));
    }

    /**
     * POST logout завершает пользовательскую сессию и редиректит на домашнюю страницу.
     */
    public function testLogoutPostLogsOutUserAndRedirectsHome(): void
    {
        $user = $this->createUser([
            'username' => 'logout_user',
        ]);
        $this->login($user);
        $controller = $this->prepareController();

        $this->setPostRequest([], [], '/site/logout');

        $response = $controller->actionLogout();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertTrue(Yii::$app->user->isGuest);
    }

    /**
     * Явный logout истекает старую remember-me cookie даже при выключенном auto-login.
     */
    public function testLogoutExpiresIdentityCookieWhenAutoLoginIsDisabled(): void
    {
        $user = $this->createUser([
            'username' => 'logout_cookie_user',
        ]);
        self::assertFalse(Yii::$app->user->enableAutoLogin);

        $this->login($user);
        $controller = $this->prepareController();
        $this->setPostRequest([], [], '/site/logout');

        $controller->actionLogout();

        $cookieName = Yii::$app->user->identityCookie['name'];
        $cookie = Yii::$app->response->cookies->get($cookieName);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame('', $cookie->value);
        self::assertSame(1, $cookie->expire);
        self::assertSame(Yii::$app->user->identityCookie['secure'], $cookie->secure);
        self::assertTrue($cookie->httpOnly);
        self::assertSame(Cookie::SAME_SITE_LAX, $cookie->sameSite);
    }

    /**
     * Создает SiteController для auth-сценариев.
     */
    private function prepareController(): SiteController
    {
        $controller = new SiteController('site', Yii::$app);
        Yii::$app->controller = $controller;

        return $controller;
    }
}
