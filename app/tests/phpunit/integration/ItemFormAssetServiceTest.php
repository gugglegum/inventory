<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\models\ItemTagsForm;
use backend\services\ItemFormAssetService;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты сервиса обработки связанных данных формы предмета.
 *
 * Проверяют сохранение тегов без HTTP-обвязки ItemsController.
 */
final class ItemFormAssetServiceTest extends DbTestCase
{
    /**
     * save() сохраняет теги предмета.
     */
    public function testSaveStoresTags(): void
    {
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
        );

        self::assertEqualsCanonicalizing(['фото', 'проверка'], $item->fetchTags());
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
