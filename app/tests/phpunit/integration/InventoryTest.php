<?php

namespace tests\phpunit\integration;

use backend\controllers\InventoryController;
use backend\models\InventoryItemConfirmForm;
use backend\models\InventoryItemUnconfirmForm;
use common\models\Inventory;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

final class InventoryTest extends DbTestCase
{
    public function testInventoryItemFormsValidateRepoScopedItemId(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'Проверяемый предмет',
        ]);

        $confirmForm = new InventoryItemConfirmForm();
        $confirmForm->repoId = $repo->id;
        $confirmForm->itemId = $item->itemId;
        self::assertTrue($confirmForm->validate());

        $unconfirmForm = new InventoryItemUnconfirmForm();
        $unconfirmForm->repoId = $repo->id;
        $unconfirmForm->itemId = 999999;
        self::assertFalse($unconfirmForm->validate());
        self::assertArrayHasKey('itemId', $unconfirmForm->getErrors());
    }

    public function testClosingInventoryMarksConfirmedAndMissingItems(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS
        );
        $container = $this->createItem($repo, $user, [
            'name' => 'Контейнер',
            'isContainer' => true,
        ]);
        $confirmed = $this->createItem($repo, $user, [
            'name' => 'Найденный предмет',
            'parentItemId' => $container->itemId,
        ]);
        $missing = $this->createItem($repo, $user, [
            'name' => 'Ненайденный предмет',
            'parentItemId' => $container->itemId,
        ]);
        $inventory = $this->createInventory($container, $user);
        $inventoryItem = $this->createInventoryItem($inventory, $confirmed, $user);
        $controller = new InventoryController('inventory', Yii::$app);
        Yii::$app->controller = $controller;

        $this->setPostRequest();

        $response = $controller->actionClose($repo->id, $container->itemId, $inventory->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);

        $inventory->refresh();
        $confirmed->refresh();
        $missing->refresh();

        self::assertSame(Inventory::STATUS_CLOSED, (int) $inventory->status);
        self::assertSame($user->id, (int) $inventory->closedBy);
        self::assertNotNull($inventory->closed);

        self::assertSame((int) $inventoryItem->created, (int) $confirmed->lastSeen);
        self::assertSame($user->id, (int) $confirmed->lastSeenBy);
        self::assertNull($confirmed->missingSince);
        self::assertNull($confirmed->missingSinceBy);

        self::assertNotNull($missing->missingSince);
        self::assertSame($user->id, (int) $missing->missingSinceBy);
        self::assertNull($missing->lastSeen);
    }
}
