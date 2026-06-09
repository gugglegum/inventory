<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\ItemFormService;
use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса подготовки и сохранения формы предмета.
 *
 * Проверяют create/update сценарии Item без HTTP-обвязки ItemsController.
 */
final class ItemFormServiceTest extends DbTestCase
{
    /**
     * prepareForCreate() выставляет служебные поля, а save() создает предмет в репозитории.
     */
    public function testPrepareForCreateAndSaveCreatesItem(): void
    {
        [$repo, $parent] = $this->prepareFixture();
        $service = new ItemFormService();

        $itemForm = $service->prepareForCreate(
            $repo,
            $parent,
            Yii::$app->getUser(),
            new ItemAccessValidator()->setUser(Yii::$app->getUser()),
            true,
        );
        $item = $itemForm->getItem();

        $result = $service->save($itemForm, [
            'Item' => [
                'name' => 'Созданный сервисом предмет',
                'description' => 'Описание',
                'parentItemId' => $parent->itemId,
                'isContainer' => '1',
                'priority' => '9',
            ],
        ]);

        self::assertTrue($result);
        self::assertFalse($item->isNewRecord);
        self::assertSame(Item::SCENARIO_CREATE, $item->scenario);
        self::assertSame((int) $repo->id, (int) $item->repoId);
        self::assertSame((int) $parent->itemId, (int) $item->parentItemId);
        self::assertSame((int) Yii::$app->user->id, (int) $item->createdBy);
        self::assertSame(1, (int) $item->isContainer);
        self::assertSame(9, (int) $item->priority);
    }

    /**
     * prepareForUpdate() выставляет update-сценарий и updatedBy, а форма тегов получает текущие теги.
     */
    public function testPrepareForUpdateAndCreateTagsFormUseExistingItemState(): void
    {
        [$repo, $parent, $item] = $this->prepareFixture();
        $item->saveTagsFromString('старый, тег');
        $service = new ItemFormService();

        $itemForm = $service->prepareForUpdate(
            $item,
            Yii::$app->getUser(),
            new ItemAccessValidator()->setUser(Yii::$app->getUser()),
        );
        $preparedItem = $itemForm->getItem();
        $tagsForm = $service->createTagsForm($preparedItem);

        $result = $service->save($itemForm, [
            'Item' => [
                'name' => 'Обновленный сервисом предмет',
                'description' => 'Новое описание',
                'parentItemId' => $parent->itemId,
                'isContainer' => '0',
                'priority' => '4',
                'itemId' => $item->itemId,
            ],
        ]);

        self::assertTrue($result);
        self::assertSame(Item::SCENARIO_UPDATE, $preparedItem->scenario);
        self::assertSame((int) Yii::$app->user->id, (int) $preparedItem->updatedBy);
        self::assertSame('старый, тег', $tagsForm->tags);
        self::assertSame('Обновленный сервисом предмет', $preparedItem->name);
        self::assertSame('Новое описание', $preparedItem->description);
        self::assertSame(4, (int) $preparedItem->priority);
    }

    /**
     * Создает репозиторий, контейнер и предмет для form-service сценариев.
     *
     * @return array{0:\common\models\Repo, 1:Item, 2:Item}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $parent = $this->createItem($repo, $user, [
            'name' => 'Контейнер',
            'isContainer' => true,
        ]);
        $item = $this->createItem($repo, $user, [
            'name' => 'Редактируемый предмет',
            'parentItemId' => $parent->itemId,
        ]);

        return [$repo, $parent, $item];
    }
}
