<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\InventoryLifecycleService;
use common\models\Inventory;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса жизненного цикла инвентаризации.
 *
 * Проверяют открытие и удаление Inventory без HTTP-обвязки контроллера.
 */
final class InventoryLifecycleServiceTest extends DbTestCase
{
    /**
     * open() создает открытую инвентаризацию для контейнера от имени текущего пользователя.
     */
    public function testOpenCreatesOpenedInventoryForContainer(): void
    {
        [$container, $user] = $this->prepareContainerFixture();

        $inventory = (new InventoryLifecycleService())->open($container, Yii::$app->getUser());

        self::assertFalse($inventory->isNewRecord);
        self::assertSame((int) $container->id, (int) $inventory->containerId);
        self::assertSame(Inventory::STATUS_OPENED, (int) $inventory->status);
        self::assertSame((int) $user->id, (int) $inventory->createdBy);
    }

    /**
     * delete() удаляет существующую инвентаризацию.
     */
    public function testDeleteRemovesInventory(): void
    {
        [$container, $user] = $this->prepareContainerFixture();
        $inventory = $this->createInventory($container, $user);

        (new InventoryLifecycleService())->delete($inventory);

        self::assertNull(Inventory::findOne($inventory->id));
    }

    /**
     * Создает контейнер и пользователя для проверки lifecycle-сервиса.
     *
     * @return array{0:\common\models\Item, 1:User}
     */
    private function prepareContainerFixture(): array
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

        return [$container, $user];
    }
}
