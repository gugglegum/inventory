<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\UsersController;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

/**
 * Integration-тесты HTTP-сценариев UsersController.
 *
 * Сохраняют regression-покрытие CRUD-обвязки управления пользователями.
 */
final class UsersControllerTest extends DbTestCase
{
    /**
     * GET index рендерит таблицу пользователей.
     */
    public function testIndexRendersUsersGrid(): void
    {
        $controller = $this->prepareManagerController();
        $user = $this->createUser([
            'username' => 'listed_user',
            'email' => 'listed-user@example.test',
        ]);

        $this->setGetRequest([], '/users');

        $response = $controller->actionIndex();

        self::assertIsString($response);
        self::assertStringContainsString('Users', $response);
        self::assertStringContainsString($user->username, $response);
        self::assertStringContainsString($user->email, $response);
    }

    /**
     * GET view рендерит карточку пользователя.
     */
    public function testViewRendersUserPage(): void
    {
        $controller = $this->prepareManagerController();
        $user = $this->createUser([
            'username' => 'viewed_user',
            'email' => 'viewed-user@example.test',
        ]);

        $this->setGetRequest([], "/users/view?id={$user->id}");

        $response = $controller->actionView($user->id);

        self::assertIsString($response);
        self::assertStringContainsString($user->username, $response);
        self::assertStringContainsString($user->email, $response);
        self::assertStringContainsString('ACTIVE', $response);
    }

    /**
     * POST create создает пользователя и редиректит на его страницу.
     */
    public function testCreatePostCreatesUserAndRedirectsToView(): void
    {
        $controller = $this->prepareManagerController();

        $this->setPostRequest([
            'UserForm' => [
                'username' => 'created_controller_user',
                'email' => 'created-controller-user@example.test',
                'password' => 'secret123',
                'status' => User::STATUS_ACTIVE,
            ],
        ], [], '/users/create');

        $response = $controller->actionCreate();

        $user = User::findOne(['username' => 'created_controller_user']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertNotNull($user);
        self::assertSame(
            Yii::$app->urlManager->createUrl(['users/view', 'id' => $user->id]),
            $response->headers->get('Location')
        );
        self::assertSame('created-controller-user@example.test', $user->email);
        self::assertSame(User::STATUS_ACTIVE, (int) $user->status);
        self::assertTrue($user->validatePassword('secret123'));
    }

    /**
     * POST update обновляет пользователя и не меняет пароль, если поле пароля пустое.
     */
    public function testUpdatePostUpdatesUserAndPreservesPasswordWhenBlank(): void
    {
        $controller = $this->prepareManagerController();
        $user = $this->createUser([
            'username' => 'old_controller_user',
            'email' => 'old-controller-user@example.test',
            'password' => 'oldSecret123',
        ]);
        $oldPasswordHash = $user->passwordHash;
        $oldAuthKey = $user->authKey;

        $this->setPostRequest([
            'UserForm' => [
                'username' => 'updated_controller_user',
                'email' => 'updated-controller-user@example.test',
                'password' => '',
                'status' => User::STATUS_DELETED,
            ],
        ], [], "/users/update?id={$user->id}");

        $response = $controller->actionUpdate($user->id);

        $user->refresh();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertSame(
            Yii::$app->urlManager->createUrl(['users/view', 'id' => $user->id]),
            $response->headers->get('Location')
        );
        self::assertSame('updated_controller_user', $user->username);
        self::assertSame('updated-controller-user@example.test', $user->email);
        self::assertSame(User::STATUS_DELETED, (int) $user->status);
        self::assertSame($oldPasswordHash, $user->passwordHash);
        self::assertSame($oldAuthKey, $user->authKey);
        self::assertTrue($user->validatePassword('oldSecret123'));
    }

    /**
     * POST delete удаляет пользователя и редиректит к списку.
     */
    public function testDeletePostDeletesUserAndRedirectsToIndex(): void
    {
        $controller = $this->prepareManagerController();
        $user = $this->createUser([
            'username' => 'deleted_controller_user',
            'email' => 'deleted-controller-user@example.test',
        ]);

        $this->setPostRequest([], [], "/users/delete?id={$user->id}");

        $response = $controller->actionDelete($user->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertSame(Yii::$app->urlManager->createUrl(['users/index']), $response->headers->get('Location'));
        self::assertNull(User::findOne($user->id));
    }

    /**
     * Создает UsersController от имени пользователя с правом управления пользователями.
     */
    private function prepareManagerController(): UsersController
    {
        $manager = $this->createUser([
            'access' => User::ACCESS_MANAGE_USERS,
        ]);
        $this->login($manager);

        $controller = new UsersController('users', Yii::$app);
        Yii::$app->controller = $controller;

        return $controller;
    }
}
