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
 * Сохраняют regression-покрытие журнала, modal view и create/update/delete заметок.
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
     * AJAX GET view с modal-флагом рендерит только содержимое модального окна.
     */
    public function testViewRendersModalPartialForAjaxRequest(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user, [
            'title' => 'Заметка в модальном окне',
            'text' => 'Полный текст модальной заметки',
        ]);
        $this->createPostPhoto($post);

        $this->setGetRequest(['modal' => '1']);
        Yii::$app->request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $controller->actionView($repo->id, (int) $item->itemId, $post->id);

        self::assertIsString($response);
        self::assertStringContainsString('class="modal-header"', $response);
        self::assertStringContainsString('Заметка в модальном окне', $response);
        self::assertStringContainsString('Полный текст модальной заметки', $response);
        self::assertStringContainsString('data-fancybox="post-modal-photos-' . $post->id . '"', $response);
        self::assertStringNotContainsString('<html', $response);
    }

    /**
     * GET index показывает 20 последних заметок и пагинацию полного журнала.
     */
    public function testIndexRendersPaginatedPostJournal(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        for ($index = 1; $index <= 21; $index++) {
            $this->createPost($item, $user, [
                'datetime' => 1_700_000_000 + $index,
                'title' => sprintf('Запись журнала %02d', $index),
            ]);
        }

        $this->setGetRequest();

        $response = $controller->actionIndex($repo->id, (int) $item->itemId);

        self::assertIsString($response);
        self::assertStringContainsString('Всего заметок: 21', $response);
        self::assertStringContainsString('class="post-index__meta"', $response);
        self::assertStringContainsString('Добавить заметку</a>', $response);
        self::assertStringNotContainsString('btn btn-primary', $response);
        self::assertStringContainsString('Запись журнала 21', $response);
        self::assertStringContainsString('Запись журнала 02', $response);
        self::assertStringNotContainsString('Запись журнала 01', $response);
        self::assertStringContainsString('page=2', $response);
        self::assertSame(20, substr_count($response, 'class="post-card post-card--clickable"'));
    }

    /**
     * POST quick-create создает короткую заметку и возвращает к ее карточке.
     */
    public function testQuickCreateCreatesTitleOnlyPostAndRedirectsToCard(): void
    {
        [$controller, $repo, $item] = $this->prepareFixture();
        $before = time() - 60;
        $this->setPostRequest([
            'Post' => [
                'title' => 'Быстрая запись',
            ],
        ]);

        $response = $controller->actionQuickCreate($repo->id, (int) $item->itemId);
        $post = Post::findOne(['itemId' => $item->id, 'title' => 'Быстрая запись']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertNotNull($post);
        self::assertSame('', $post->text);
        self::assertGreaterThanOrEqual($before, (int) $post->datetime);
        self::assertLessThanOrEqual(time() + 60, (int) $post->datetime);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$item->itemId}#post-{$post->id}",
            $response->headers->get('Location')
        );
    }

    /**
     * POST create создает заметку и редиректит к ее карточке на странице предмета.
     */
    public function testCreatePostCreatesPostAndRedirectsToCard(): void
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
            "/repo/{$repo->id}/items/{$item->itemId}#post-{$post->id}",
            $response->headers->get('Location')
        );
        self::assertSame('Текст новой заметки', $post->text);
        self::assertSame((int) Yii::$app->user->id, (int) $post->createdBy);
    }

    /**
     * POST update обновляет заметку и редиректит к ее карточке на странице предмета.
     */
    public function testUpdatePostUpdatesPostAndRedirectsToCard(): void
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
            "/repo/{$repo->id}/items/{$item->itemId}#post-{$post->id}",
            $response->headers->get('Location')
        );
        self::assertSame('Обновленная заметка', $post->title);
        self::assertSame('Новый текст', $post->text);
        self::assertSame((int) Yii::$app->user->id, (int) $post->updatedBy);
    }

    /**
     * POST update старой заметки возвращает к ней в полном журнале.
     */
    public function testUpdateOldPostRedirectsToJournalAnchor(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        $oldPost = $this->createPost($item, $user, [
            'datetime' => 1_700_000_000,
            'title' => 'Старая запись',
        ]);
        for ($index = 1; $index <= 5; $index++) {
            $this->createPost($item, $user, [
                'datetime' => 1_700_000_000 + $index,
                'title' => 'Новая запись ' . $index,
            ]);
        }
        $_FILES = [];
        $this->setPostRequest([
            'Post' => [
                'datetimeText' => '14.11.2023 22:13',
                'title' => 'Обновленная старая запись',
                'text' => '',
            ],
        ]);

        $response = $controller->actionUpdate($repo->id, (int) $item->itemId, $oldPost->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$item->itemId}/posts#post-{$oldPost->id}",
            $response->headers->get('Location')
        );
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
        self::assertStringContainsString('class="photo-editor photo-editor--has-cards"', $response);
        self::assertStringContainsString('class="photo-editor__drop-slot"', $response);
        self::assertStringContainsString(
            'class="photo-editor__droparea photo-editor__droparea--compact"',
            $response
        );
        self::assertStringNotContainsString('photo-editor__drop-slot--wide', $response);
        self::assertMatchesRegularExpression('/data-fancybox="photo-editor-[0-9a-f]{12}"/', $response);
        self::assertStringContainsString('bi bi-calendar3 kv-dp-icon', $response);
        self::assertStringContainsString('form-label', $response);
        self::assertStringContainsString('form-control', $response);
        self::assertStringContainsString('id="post-title"', $response);
        self::assertStringContainsString('autocomplete="off"', $response);
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
