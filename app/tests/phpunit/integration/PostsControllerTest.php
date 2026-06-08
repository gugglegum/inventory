<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\PostsController;
use common\models\Item;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
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

        $response = $controller->actionCreate($repo->id, $item->itemId);

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

        $response = $controller->actionUpdate($repo->id, $item->itemId, $post->id);

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

        $response = $controller->actionDelete($repo->id, $item->itemId, $post->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$item->itemId}",
            $response->headers->get('Location')
        );
        self::assertNull(Post::findOne($post->id));
    }

    /**
     * GET update рендерит кнопки управления фотографиями заметки с явным photoType=post.
     */
    public function testUpdateGetRendersPostPhotoTypeButtons(): void
    {
        [$controller, $repo, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user, [
            'title' => 'Заметка с фото',
        ]);
        $postPhoto = $this->createPostPhoto($post);

        $this->setGetRequest();

        $response = $controller->actionUpdate($repo->id, $item->itemId, $post->id);

        self::assertIsString($response);
        self::assertStringContainsString('data-id="' . $postPhoto->id . '"', $response);
        self::assertStringContainsString('data-photo-type="post"', $response);
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

    /**
     * Создает связь фотографии с заметкой.
     */
    private function createPostPhoto(Post $post): PostPhoto
    {
        $postPhoto = new PostPhoto([
            'postId' => $post->id,
            'photoId' => $this->createPhoto()->id,
        ]);
        $this->saveModel($postPhoto);

        return $postPhoto;
    }

    /**
     * Создает сохраненную фотографию из маленького JPEG.
     */
    private function createPhoto(): Photo
    {
        $uploadedFile = $this->createUploadedJpegFixture();

        $photo = new Photo();
        $photo->assignFile($uploadedFile);
        $this->saveModel($photo);
        @unlink($uploadedFile);

        return $photo;
    }

    /**
     * Создает маленький JPEG-файл, имитирующий загруженное фото.
     */
    private function createUploadedJpegFixture(): string
    {
        $file = tempnam(Yii::$app->params['photos']['storageTemp'], 'upload');
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 120, 160));
        imagejpeg($image, $file);
        imagedestroy($image);

        return $file;
    }
}
