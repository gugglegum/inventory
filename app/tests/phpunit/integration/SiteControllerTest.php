<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\SiteController;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

/**
 * Integration-тесты auth-сценариев SiteController.
 *
 * Проверяют успешный вход, неуспешный вход и выход пользователя.
 */
final class SiteControllerTest extends DbTestCase
{
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
        self::assertStringContainsString('Login', $response);
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
     * Создает SiteController для auth-сценариев.
     */
    private function prepareController(): SiteController
    {
        $controller = new SiteController('site', Yii::$app);
        Yii::$app->controller = $controller;

        return $controller;
    }
}
