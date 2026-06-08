<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\models\ItemTagsForm;
use backend\services\ItemFormAssetService;
use common\models\ItemPhoto;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

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
}
