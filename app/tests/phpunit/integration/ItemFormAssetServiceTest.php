<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\models\ItemTagsForm;
use backend\services\ItemFormAssetService;
use common\models\ItemPhoto;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса обработки связанных данных формы предмета.
 *
 * Проверяют сохранение тегов и прикрепление новых фотографий без HTTP-обвязки ItemsController.
 */
final class ItemFormAssetServiceTest extends DbTestCase
{
    /**
     * save() сохраняет теги и прикрепляет загруженный JPEG к предмету.
     */
    public function testSaveStoresTagsAndAttachesUploadedPhoto(): void
    {
        $this->ensurePhotoRuntimeDirectories();
        $uploadedFile = $this->createUploadedJpegFixture();
        $item = $this->prepareItemFixture();
        $tagsForm = new ItemTagsForm();

        (new ItemFormAssetService())->save(
            $item,
            $tagsForm,
            [
                'ItemTagsForm' => [
                    'tags' => 'фото, проверка',
                ],
            ],
            [
                'photos' => [
                    'tmp_name' => [
                        $uploadedFile,
                        '',
                    ],
                ],
            ],
        );

        @unlink($uploadedFile);

        /** @var ItemPhoto|null $itemPhoto */
        $itemPhoto = $item->getItemPhotos()->one();

        self::assertEqualsCanonicalizing(['фото', 'проверка'], $item->fetchTags());
        self::assertNotNull($itemPhoto);
        self::assertNotNull($itemPhoto->photo);
        self::assertFileExists($itemPhoto->photo->getFile());
        self::assertSame(0, (int) $itemPhoto->sortIndex);
    }

    /**
     * Создает предмет с правами на редактирование.
     */
    private function prepareItemFixture(): \common\models\Item
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        return $this->createItem($repo, $user, [
            'name' => 'Предмет с фотографией',
        ]);
    }

    /**
     * Создает runtime-каталоги, нужные Photo::assignFile().
     */
    private function ensurePhotoRuntimeDirectories(): void
    {
        foreach ([
            Yii::$app->params['photos']['storagePath'],
            Yii::$app->params['photos']['storageTemp'],
            Yii::$app->params['photos']['thumbnailPath'],
            Yii::$app->params['photos']['thumbnailTemp'],
        ] as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }
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
