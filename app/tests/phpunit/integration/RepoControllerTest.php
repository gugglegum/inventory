<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\RepoController;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

/**
 * Integration-тесты HTTP-сценариев RepoController.
 *
 * Сохраняют regression-покрытие create/update/delete после выноса логики подготовки в сервисы.
 */
final class RepoControllerTest extends DbTestCase
{
    /**
     * POST create создает репозиторий и редиректит к списку репозиториев.
     */
    public function testCreatePostCreatesRepoAndRedirectsToIndex(): void
    {
        $controller = $this->prepareControllerWithCreateUser();

        $this->setPostRequest([
            'RepoForm' => [
                'name' => 'Новый репозиторий',
                'description' => 'Описание репозитория',
                'priority' => '4',
            ],
        ]);

        $response = $controller->actionCreate();

        $repo = Repo::findOne(['name' => 'Новый репозиторий']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertNotNull($repo);
        self::assertSame('Описание репозитория', $repo->description);
    }

    /**
     * POST update обновляет репозиторий и редиректит на его страницу.
     */
    public function testUpdatePostUpdatesRepoAndRedirectsToView(): void
    {
        [$controller, $repo, $repoUser] = $this->prepareRepoFixture();

        $this->setPostRequest([
            'RepoForm' => [
                'name' => 'Обновленный репозиторий',
                'description' => 'Новое описание',
                'lastItemId' => '15',
                'priority' => '6',
            ],
        ]);

        $response = $controller->actionUpdate($repo->id);

        $repo->refresh();
        $repoUser->refresh();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString("/repo/{$repo->id}", $response->headers->get('Location'));
        self::assertSame('Обновленный репозиторий', $repo->name);
        self::assertSame('Новое описание', $repo->description);
        self::assertSame(15, (int) $repo->lastItemId);
        self::assertSame(6, (int) $repoUser->priority);
    }

    /**
     * POST delete удаляет репозиторий и редиректит к списку репозиториев.
     */
    public function testDeletePostDeletesRepoAndRedirectsToIndex(): void
    {
        [$controller, $repo] = $this->prepareRepoFixture();

        $this->setPostRequest();

        $response = $controller->actionDelete($repo->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertNotEmpty($response->headers->get('Location'));
        self::assertNull(Repo::findOne($repo->id));
    }

    /**
     * Создает controller с пользователем, которому разрешено создавать репозитории.
     */
    private function prepareControllerWithCreateUser(): RepoController
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $this->login($user);

        $controller = new RepoController('repo', Yii::$app);
        Yii::$app->controller = $controller;

        return $controller;
    }

    /**
     * Создает controller и репозиторий с правами на редактирование и удаление.
     *
     * @return array{0:RepoController, 1:Repo, 2:RepoUser}
     */
    private function prepareRepoFixture(): array
    {
        $controller = $this->prepareControllerWithCreateUser();
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $repo = $this->createRepo($user, [
            'name' => 'Редактируемый репозиторий',
            'description' => 'Старое описание',
            'lastItemId' => 5,
        ]);
        $repoUser = $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS
                | RepoUser::ACCESS_EDIT_ITEMS
                | RepoUser::ACCESS_DELETE_ITEMS
                | RepoUser::ACCESS_EDIT_REPO
                | RepoUser::ACCESS_DELETE_REPO
        );

        return [$controller, $repo, $repoUser];
    }
}
