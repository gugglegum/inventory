<?php

declare(strict_types=1);

namespace backend\services;

use common\components\ItemAccessValidator;
use common\helpers\ValidateErrorsFormatter;
use common\models\Inventory;
use common\models\Item;
use yii\base\Exception;
use yii\web\User;

final class InventoryCloseService
{
    /**
     * @throws Exception
     * @throws \yii\db\Exception
     * @throws \Throwable
     */
    public function close(
        Inventory $inventory,
        Item $container,
        User $user,
        ItemAccessValidator $itemAccessValidator,
        ?int $closedAt = null,
    ): void {
        $closedAt ??= time();
        $transaction = Inventory::getDb()->beginTransaction();

        try {
            $this->closeInTransaction($inventory, $container, $user, $itemAccessValidator, $closedAt);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     * @throws \yii\db\Exception
     */
    private function closeInTransaction(
        Inventory $inventory,
        Item $container,
        User $user,
        ItemAccessValidator $itemAccessValidator,
        int $closedAt,
    ): void {
        /** @var int[] $confirmedItemIds */
        $confirmedItemIds = [];

        foreach ($inventory->inventoryItems as $inventoryItem) {
            $item = $inventoryItem->item;
            $confirmedItemIds[] = $item->id;

            $item->setItemAccessValidator($itemAccessValidator);
            $item->scenario = Item::SCENARIO_UPDATE;
            $item->lastSeen = $inventoryItem->created;
            $item->lastSeenBy = $inventoryItem->createdBy;
            $item->missingSince = null;
            $item->parentItemId = $container->itemId;

            $this->saveItem($item);
        }

        $missingItemsQuery = $container->getItems();
        if ($confirmedItemIds !== []) {
            $missingItemsQuery->andWhere(['not in', Item::tableName() . '.id', $confirmedItemIds]);
        }

        /** @var Item[] $missingItems */
        $missingItems = $missingItemsQuery->all();
        foreach ($missingItems as $item) {
            $item->setItemAccessValidator($itemAccessValidator);
            $item->scenario = Item::SCENARIO_UPDATE;
            $item->missingSince = $closedAt;
            $item->missingSinceBy = (int) $user->id;

            $this->saveItem($item);
        }

        $inventory->status = Inventory::STATUS_CLOSED;
        $inventory->closed = $closedAt;
        $inventory->closedBy = (int) $user->id;
        if (!$inventory->save()) {
            throw new Exception(ValidateErrorsFormatter::getMessage($inventory));
        }
    }

    /**
     * @throws Exception
     * @throws \yii\db\Exception
     */
    private function saveItem(Item $item): void
    {
        if (!$item->save()) {
            throw new Exception(ValidateErrorsFormatter::getMessage($item));
        }
    }
}
