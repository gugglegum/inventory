<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\PhotoController;
use backend\services\PhotoAttachmentService;
use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты HTTP-сценариев PhotoController.
 *
 * Проверяют управление фотографиями предметов и заметок через POST endpoints.
 */
final class PhotoControllerTest extends DbTestCase
{
    /**
     * sort-up без photoType сохраняет старое поведение для фотографий предметов.
     */
    public function testSortUpDefaultsToItemPhotoType(): void
    {
        [$controller, $item, $post] = $this->prepareFixture();
        $firstPhoto = $this->createItemPhoto($item);
        $secondPhoto = $this->createItemPhoto($item);

        $this->setPostRequest([
            'id' => $secondPhoto->id,
        ]);

        $controller->actionSortUp();

        $firstPhoto->refresh();
        $secondPhoto->refresh();

        self::assertSame(1, (int) $firstPhoto->sortIndex);
        self::assertSame(0, (int) $secondPhoto->sortIndex);
    }

    /**
     * sort-down с photoType=post меняет порядок фотографий заметки.
     */
    public function testSortDownSupportsPostPhotoType(): void
    {
        [$controller, $item, $post] = $this->prepareFixture();
        $firstPhoto = $this->createPostPhoto($post);
        $secondPhoto = $this->createPostPhoto($post);

        $this->setPostRequest([
            'id' => $firstPhoto->id,
            'photoType' => PhotoAttachmentService::TYPE_POST,
        ]);

        $controller->actionSortDown();

        $firstPhoto->refresh();
        $secondPhoto->refresh();

        self::assertSame(1, (int) $firstPhoto->sortIndex);
        self::assertSame(0, (int) $secondPhoto->sortIndex);
    }

    /**
     * delete с photoType=post удаляет фотографию заметки.
     */
    public function testDeleteSupportsPostPhotoType(): void
    {
        [$controller, $item, $post] = $this->prepareFixture();
        $postPhoto = $this->createPostPhoto($post);

        $this->setPostRequest([
            'id' => $postPhoto->id,
            'photoType' => PhotoAttachmentService::TYPE_POST,
        ]);

        $controller->actionDelete();

        self::assertNull(PostPhoto::findOne($postPhoto->id));
    }

    /**
     * Создает контроллер, предмет и заметку для HTTP-сценариев фотографий.
     *
     * @return array{0:PhotoController, 1:Item, 2:Post}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'Предмет с фотографиями',
        ]);
        $post = $this->createPost($item, $user, [
            'title' => 'Заметка с фотографиями',
        ]);

        $controller = new PhotoController('photo', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $item, $post];
    }

    /**
     * Создает связь фотографии с предметом.
     */
    private function createItemPhoto(Item $item): ItemPhoto
    {
        $itemPhoto = new ItemPhoto([
            'itemId' => $item->id,
            'photoId' => $this->createPhoto()->id,
        ]);
        $this->saveModel($itemPhoto);

        return $itemPhoto;
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
