<?php

namespace tests\phpunit;

use common\components\ItemAccessValidator;
use common\models\Inventory;
use common\models\InventoryItem;
use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Transaction;

abstract class DbTestCase extends TestCase
{
    private ?Transaction $transaction = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = Yii::$app->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->transaction !== null && $this->transaction->isActive) {
            $this->transaction->rollBack();
        }
        $this->transaction = null;
        parent::tearDown();
    }

    protected function createUser(array $attributes = []): User
    {
        $user = new User();
        $user->username = $attributes['username'] ?? 'user_' . bin2hex(random_bytes(4));
        $user->email = $attributes['email'] ?? $user->username . '@example.test';
        $user->access = $attributes['access'] ?? 0;
        $user->setPassword($attributes['password'] ?? 'password');
        $user->generateAuthKey();

        $this->saveModel($user);

        return $user;
    }

    protected function login(User $user): void
    {
        Yii::$app->user->login($user);
    }

    protected function createRepo(User $user, array $attributes = []): Repo
    {
        $this->login($user);

        $repo = new Repo([
            'name' => $attributes['name'] ?? 'Тестовый репозиторий',
            'description' => $attributes['description'] ?? null,
            'lastItemId' => $attributes['lastItemId'] ?? 0,
            'createdBy' => $user->id,
        ]);

        $this->saveModel($repo);

        return $repo;
    }

    protected function grantRepoAccess(Repo $repo, User $user, int $access): RepoUser
    {
        $repoUser = new RepoUser([
            'repoId' => $repo->id,
            'userId' => $user->id,
            'access' => $access,
            'priority' => 0,
        ]);

        $this->saveModel($repoUser);

        return $repoUser;
    }

    protected function createItem(Repo $repo, User $user, array $attributes = []): Item
    {
        $this->login($user);

        $item = new Item();
        $item->scenario = Item::SCENARIO_CREATE;
        $item->setItemAccessValidator(new ItemAccessValidator());
        $item->repoId = $repo->id;
        $item->parentItemId = $attributes['parentItemId'] ?? null;
        $item->name = $attributes['name'] ?? 'Тестовый предмет';
        $item->description = $attributes['description'] ?? null;
        $item->isContainer = (int) ($attributes['isContainer'] ?? 0);
        $item->priority = $attributes['priority'] ?? 0;
        $item->createdBy = $user->id;

        $this->saveModel($item);

        return $item;
    }

    protected function createInventory(Item $container, User $user, array $attributes = []): Inventory
    {
        $inventory = new Inventory([
            'containerId' => $container->id,
            'status' => $attributes['status'] ?? Inventory::STATUS_OPENED,
            'createdBy' => $user->id,
            'closedBy' => $attributes['closedBy'] ?? null,
            'closed' => $attributes['closed'] ?? null,
        ]);

        $this->saveModel($inventory);

        return $inventory;
    }

    protected function createInventoryItem(Inventory $inventory, Item $item, User $user): InventoryItem
    {
        $inventoryItem = new InventoryItem([
            'inventoryId' => $inventory->id,
            'itemId' => $item->id,
            'createdBy' => $user->id,
        ]);

        $this->saveModel($inventoryItem);

        return $inventoryItem;
    }

    protected function saveModel(ActiveRecord $model): void
    {
        self::assertTrue(
            $model->save(),
            get_class($model) . ' save failed: ' . json_encode($model->getErrors(), JSON_UNESCAPED_UNICODE)
        );
    }
}
