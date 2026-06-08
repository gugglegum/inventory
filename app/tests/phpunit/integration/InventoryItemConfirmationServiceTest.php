<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\InventoryItemConfirmationService;
use common\models\InventoryItem;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса подтверждения предметов в инвентаризации.
 *
 * Проверяют прямые мутации inventory_item без HTTP-обвязки InventoryController.
 */
final class InventoryItemConfirmationServiceTest extends DbTestCase
{
    /**
     * confirm() создает запись inventory_item с текущим пользователем в createdBy.
     */
    public function testConfirmCreatesInventoryItem(): void
    {
        [$inventory, $item, $user] = $this->prepareFixture();

        $result = (new InventoryItemConfirmationService())->confirm($inventory, $item, Yii::$app->getUser());

        self::assertFalse($result->hasError());
        self::assertNotNull($result->inventoryItem);
        self::assertNull($result->errorMessage);
        self::assertSame((int) $inventory->id, (int) $result->inventoryItem->inventoryId);
        self::assertSame((int) $item->id, (int) $result->inventoryItem->itemId);
        self::assertSame((int) $user->id, (int) $result->inventoryItem->createdBy);
    }

    /**
     * unconfirm() удаляет существующую запись inventory_item.
     */
    public function testUnconfirmDeletesInventoryItem(): void
    {
        [$inventory, $item, $user] = $this->prepareFixture();
        $this->createInventoryItem($inventory, $item, $user);

        $result = (new InventoryItemConfirmationService())->unconfirm($inventory, $item);

        self::assertTrue($result);
        self::assertSame(
            0,
            (int) InventoryItem::find()->where(['inventoryId' => $inventory->id, 'itemId' => $item->id])->count()
        );
    }

    /**
     * Создает контейнер, предмет и открытую инвентаризацию для проверки сервиса.
     *
     * @return array{0:\common\models\Inventory, 1:\common\models\Item, 2:User}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $container = $this->createItem($repo, $user, [
            'name' => 'Контейнер',
            'isContainer' => true,
        ]);
        $item = $this->createItem($repo, $user, [
            'name' => 'Проверяемый предмет',
            'parentItemId' => $container->itemId,
        ]);
        $inventory = $this->createInventory($container, $user);

        return [$inventory, $item, $user];
    }
}
