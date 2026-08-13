<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\PostsController;
use common\models\Item;
use common\models\Post;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

/**
 * Integration-тесты HTTP-сценариев PostsController.
 *
 * Сохраняют regression-покрытие create/update/delete после выноса бизнес-логики в сервисы.
 */
final class PostsControllerTest extends DbTestCase
{
    /**
     * GET view рендерит страницу существующей заметки.
     */
    public function testViewRendersPostPage(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user, [
            'title' => 'Просматриваемая заметка',
            'text' => 'Текст для просмотра',
        ]);
        $this->createPostPhoto($post);

        $this->setGetRequest();

        $response = $controller->actionView($repo->id, (int) $item->itemId, $post->id);

        self::assertIsString($response);
        self::assertStringContainsString('Просматриваемая заметка', $response);
        self::assertStringContainsString('Текст для просмотра', $response);
        self::assertStringContainsString('data-fancybox="post-photos"', $response);
        self::assertStringNotContainsString('rel="post-photos"', $response);
    }

    /**
     * POST create создает заметку и редиректит на ее страницу.
     */
    public function testCreatePostCreatesPostAndRedirectsToView(): void
    {
        [$controller, $repo, $item] = $this->prepareFixture();
        $_FILES = [];

        $this->setPostRequest([
            'Post' => [
                'datetimeText' => '01.06.2026 12:30',
                'title' => 'Новая заметка',
                'text' => 'Текст новой заметки',
            ],
        ]);

        $response = $controller->actionCreate($repo->id, (int) $item->itemId);

        $post = Post::findOne(['itemId' => $item->id, 'title' => 'Новая заметка']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertNotNull($post);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$item->itemId}/posts/{$post->id}",
            $response->headers->get('Location')
        );
        self::assertSame('Текст новой заметки', $post->text);
        self::assertSame((int) Yii::$app->user->id, (int) $post->createdBy);
    }

    /**
     * POST update обновляет заметку и редиректит на ее страницу.
     */
    public function testUpdatePostUpdatesPostAndRedirectsToView(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user, [
            'title' => 'Старая заметка',
            'text' => 'Старый текст',
        ]);
        $_FILES = [];

        $this->setPostRequest([
            'Post' => [
                'datetimeText' => '02.06.2026 08:15',
                'title' => 'Обновленная заметка',
                'text' => 'Новый текст',
            ],
        ]);

        $response = $controller->actionUpdate($repo->id, (int) $item->itemId, $post->id);

        $post->refresh();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$item->itemId}/posts/{$post->id}",
            $response->headers->get('Location')
        );
        self::assertSame('Обновленная заметка', $post->title);
        self::assertSame('Новый текст', $post->text);
        self::assertSame((int) Yii::$app->user->id, (int) $post->updatedBy);
    }

    /**
     * POST delete удаляет заметку и редиректит на страницу предмета.
     */
    public function testDeletePostDeletesPostAndRedirectsToItem(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user, [
            'title' => 'Удаляемая заметка',
        ]);

        $this->setPostRequest();

        $response = $controller->actionDelete($repo->id, (int) $item->itemId, $post->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$item->itemId}",
            $response->headers->get('Location')
        );
        self::assertNull(Post::findOne($post->id));
    }

    /**
     * GET update рендерит существующую фотографию в общем редакторе заметки.
     */
    public function testUpdateGetRendersPostPhotoInEditor(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user, [
            'title' => 'Заметка с фото',
        ]);
        $postPhoto = $this->createPostPhoto($post);

        $this->setGetRequest();

        $response = $controller->actionUpdate($repo->id, (int) $item->itemId, $post->id);

        self::assertIsString($response);
        self::assertStringContainsString('data-photo-editor', $response);
        self::assertStringContainsString('data-entry-type="existing"', $response);
        self::assertStringContainsString('data-entry-id="' . $postPhoto->id . '"', $response);
        self::assertStringContainsString('data-upload-context="post"', $response);
        self::assertMatchesRegularExpression('/data-fancybox="photo-editor-[0-9a-f]{12}"/', $response);
        self::assertStringContainsString('bi bi-calendar3 kv-dp-icon', $response);
        self::assertStringContainsString('form-label', $response);
        self::assertStringContainsString('form-control', $response);
        self::assertStringNotContainsString('fas fa-calendar-alt', $response);
        self::assertStringNotContainsString('glyphicon', $response);
    }

    /**
     * Создает контроллер, репозиторий и предмет для HTTP-сценариев заметок.
     *
     * @return array{0:PostsController, 1:Repo, 2:Item, 3:User}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'Предмет с заметками',
        ]);

        $controller = new PostsController('posts', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $item, $user];
    }
}
