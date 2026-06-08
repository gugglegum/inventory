<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\PhotoAttachmentService;
use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса управления связями фотографий.
 *
 * Проверяют сортировку и удаление фотографий предметов и заметок без HTTP-обвязки PhotoController.
 */
final class PhotoAttachmentServiceTest extends DbTestCase
{
    /**
     * sortUp() меняет порядок фотографий предмета.
     */
    public function testSortUpMovesItemPhotoWithinItemList(): void
    {
        [$item, $post] = $this->prepareFixture();
        $firstPhoto = $this->createItemPhoto($item);
        $secondPhoto = $this->createItemPhoto($item);

        $result = (new PhotoAttachmentService())->sortUp($secondPhoto->id, PhotoAttachmentService::TYPE_ITEM);

        $firstPhoto->refresh();
        $secondPhoto->refresh();

        self::assertTrue($result);
        self::assertSame(1, (int) $firstPhoto->sortIndex);
        self::assertSame(0, (int) $secondPhoto->sortIndex);
    }

    /**
     * sortDown() меняет порядок фотографий заметки.
     */
    public function testSortDownMovesPostPhotoWithinPostList(): void
    {
        [$item, $post] = $this->prepareFixture();
        $firstPhoto = $this->createPostPhoto($post);
        $secondPhoto = $this->createPostPhoto($post);

        $result = (new PhotoAttachmentService())->sortDown($firstPhoto->id, PhotoAttachmentService::TYPE_POST);

        $firstPhoto->refresh();
        $secondPhoto->refresh();

        self::assertTrue($result);
        self::assertSame(1, (int) $firstPhoto->sortIndex);
        self::assertSame(0, (int) $secondPhoto->sortIndex);
    }

    /**
     * delete() удаляет связь фотографии заметки.
     */
    public function testDeleteRemovesPostPhoto(): void
    {
        [$item, $post] = $this->prepareFixture();
        $postPhoto = $this->createPostPhoto($post);

        $result = (new PhotoAttachmentService())->delete($postPhoto->id, PhotoAttachmentService::TYPE_POST);

        self::assertTrue($result);
        self::assertNull(PostPhoto::findOne($postPhoto->id));
    }

    /**
     * Создает предмет и заметку для проверки связей фотографий.
     *
     * @return array{0:Item, 1:Post}
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

        return [$item, $post];
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
