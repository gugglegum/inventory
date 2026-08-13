<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\InventoryController;
use backend\models\InventoryItemConfirmForm;
use backend\models\InventoryItemUnconfirmForm;
use common\models\Inventory;
use common\models\InventoryItem;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\Response;

/**
 * Integration-тесты инвентаризаций через формы и контроллер.
 *
 * Оставляют regression-покрытие HTTP-слоя поверх сервисов инвентаризации.
 */
final class InventoryTest extends DbTestCase
{
    /**
     * Формы подтверждения/снятия подтверждения проверяют itemId в рамках текущего репозитория.
     */
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
        $confirmForm->repoId = (string) $repo->id;
        $confirmForm->itemId = (string) $item->itemId;
        self::assertTrue($confirmForm->validate());

        $unconfirmForm = new InventoryItemUnconfirmForm();
        $unconfirmForm->repoId = (string) $repo->id;
        $unconfirmForm->itemId = '999999';
        self::assertFalse($unconfirmForm->validate());
        self::assertArrayHasKey('itemId', $unconfirmForm->getErrors());
    }

    /**
     * POST create открывает новую инвентаризацию контейнера и возвращает redirect на ее страницу.
     */
    public function testCreatePostOpensInventoryAndRedirectsToView(): void
    {
        [$controller, $repo, $container] = $this->prepareInventoryContainerFixture();

        $this->setPostRequest();

        $response = $controller->actionCreate($repo->id, (int) $container->itemId);

        $inventory = Inventory::findOne(['containerId' => $container->id]);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertNotNull($inventory);
        self::assertSame(Inventory::STATUS_OPENED, (int) $inventory->status);
        self::assertSame((int) Yii::$app->user->id, (int) $inventory->createdBy);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$container->itemId}/inventory/{$inventory->id}",
            $response->headers->get('Location')
        );
    }

    /**
     * POST delete удаляет инвентаризацию и возвращает redirect к списку инвентаризаций контейнера.
     */
    public function testDeletePostRemovesInventoryAndRedirectsToIndex(): void
    {
        [$controller, $repo, $container, $item, $inventory] = $this->prepareOpenedInventoryFixture();

        $this->setPostRequest();

        $response = $controller->actionDelete($repo->id, (int) $container->itemId, $inventory->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$container->itemId}/inventory",
            $response->headers->get('Location')
        );
        self::assertNull(Inventory::findOne($inventory->id));
    }

    /**
     * POST confirm на странице инвентаризации создает отметку о найденном предмете и возвращает redirect.
     */
    public function testInventoryViewConfirmPostCreatesInventoryItem(): void
    {
        [$controller, $repo, $container, $item, $inventory] = $this->prepareOpenedInventoryFixture();

        $this->setPostRequest([
            'confirm' => [
                'itemId' => $item->itemId,
            ],
        ]);

        $response = $controller->actionView($repo->id, (int) $container->itemId, $inventory->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$container->itemId}/inventory/{$inventory->id}",
            $response->headers->get('Location')
        );

        $inventoryItem = InventoryItem::findOne([
            'inventoryId' => $inventory->id,
            'itemId' => $item->id,
        ]);
        self::assertNotNull($inventoryItem);
        self::assertSame($item->id, (int) $inventoryItem->itemId);
    }

    /**
     * POST unconfirm на странице инвентаризации удаляет отметку о найденном предмете и возвращает redirect.
     */
    public function testInventoryViewUnconfirmPostDeletesInventoryItem(): void
    {
        [$controller, $repo, $container, $item, $inventory, $user] = $this->prepareOpenedInventoryFixture();
        $this->createInventoryItem($inventory, $item, $user);

        $this->setPostRequest([
            'unconfirm' => [
                'itemId' => $item->itemId,
            ],
        ]);

        $response = $controller->actionView($repo->id, (int) $container->itemId, $inventory->id);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(302, $response->statusCode);
        self::assertStringContainsString(
            "/repo/{$repo->id}/items/{$container->itemId}/inventory/{$inventory->id}",
            $response->headers->get('Location')
        );
        self::assertSame(
            0,
            (int) InventoryItem::find()->where(['inventoryId' => $inventory->id, 'itemId' => $item->id])->count()
        );
    }

    /**
     * GET view использует Bootstrap 5 разметку быстрых форм инвентаризации.
     */
    public function testInventoryViewGetRendersBootstrapFiveForms(): void
    {
        [$controller, $repo, $container, , $inventory] = $this->prepareOpenedInventoryFixture();

        $this->setGetRequest();

        $response = $controller->actionView($repo->id, (int) $container->itemId, $inventory->id);

        self::assertIsString($response);
        self::assertStringContainsString('inventory-by-item-id d-flex flex-wrap align-items-end gap-2', $response);
        self::assertStringContainsString('class="input-group-text"', $response);
        self::assertStringContainsString('form-control', $response);
        self::assertStringNotContainsString('form-inline', $response);
        self::assertStringNotContainsString('input-group-addon', $response);
        self::assertStringNotContainsString('glyphicon', $response);
    }

    /**
     * Контроллерный сценарий закрытия обновляет найденные и отсутствующие предметы и возвращает redirect.
     */
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

        $response = $controller->actionClose($repo->id, (int) $container->itemId, $inventory->id);

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

    /**
     * Создает контейнер и готовый контроллер для сценариев создания инвентаризации.
     *
     * @return array{0:InventoryController, 1:\common\models\Repo, 2:\common\models\Item, 3:User}
     */
    private function prepareInventoryContainerFixture(): array
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
        $controller = new InventoryController('inventory', Yii::$app);
        Yii::$app->controller = $controller;

        return [$controller, $repo, $container, $user];
    }

    /**
     * Создает открытую инвентаризацию с одним неподтвержденным предметом и готовым контроллером.
     *
     * @return array{0:InventoryController, 1:\common\models\Repo, 2:\common\models\Item, 3:\common\models\Item, 4:Inventory, 5:User}
     */
    private function prepareOpenedInventoryFixture(): array
    {
        [$controller, $repo, $container, $user] = $this->prepareInventoryContainerFixture();

        $item = $this->createItem($repo, $user, [
            'name' => 'Проверяемый предмет',
            'parentItemId' => $container->itemId,
        ]);
        $inventory = $this->createInventory($container, $user);

        return [$controller, $repo, $container, $item, $inventory, $user];
    }
}
