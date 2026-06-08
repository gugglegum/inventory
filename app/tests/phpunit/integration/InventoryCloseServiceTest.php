<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\InventoryCloseService;
use common\components\ItemAccessValidator;
use common\models\Inventory;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

final class InventoryCloseServiceTest extends DbTestCase
{
    public function testCloseMovesConfirmedItemIntoContainerAndMarksMissingChildren(): void
    {
        $closedAt = 1_717_000_000;
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $container = $this->createItem($repo, $user, [
            'name' => 'Контейнер',
            'isContainer' => true,
        ]);
        $confirmed = $this->createItem($repo, $user, [
            'name' => 'Нашелся вне контейнера',
            'parentItemId' => null,
        ]);
        $missing = $this->createItem($repo, $user, [
            'name' => 'Остался неподтвержденным',
            'parentItemId' => $container->itemId,
        ]);
        $inventory = $this->createInventory($container, $user);
        $inventoryItem = $this->createInventoryItem($inventory, $confirmed, $user);

        (new InventoryCloseService())->close(
            $inventory,
            $container,
            Yii::$app->getUser(),
            new ItemAccessValidator()->setUser(Yii::$app->getUser()),
            $closedAt,
        );

        $inventory->refresh();
        $confirmed->refresh();
        $missing->refresh();

        self::assertSame(Inventory::STATUS_CLOSED, (int) $inventory->status);
        self::assertSame($closedAt, (int) $inventory->closed);
        self::assertSame($user->id, (int) $inventory->closedBy);

        self::assertSame((int) $container->itemId, (int) $confirmed->parentItemId);
        self::assertSame((int) $inventoryItem->created, (int) $confirmed->lastSeen);
        self::assertSame($user->id, (int) $confirmed->lastSeenBy);
        self::assertNull($confirmed->missingSince);
        self::assertNull($confirmed->missingSinceBy);

        self::assertSame($closedAt, (int) $missing->missingSince);
        self::assertSame($user->id, (int) $missing->missingSinceBy);
        self::assertNull($missing->lastSeen);
    }
}
