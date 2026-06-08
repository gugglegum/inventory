<?php

declare(strict_types=1);

namespace backend\services;

use common\components\ItemAccessValidator;
use common\helpers\ValidateErrorsFormatter;
use common\models\Inventory;
use common\models\Item;
use common\models\User as Identity;
use yii\base\Exception;
use yii\web\User;

/**
 * Закрывает инвентаризацию контейнера и переносит результаты проверки в состояние предметов.
 *
 * Подтвержденные предметы отмечаются как найденные и привязываются к контейнеру инвентаризации.
 * Неподтвержденные дочерние предметы контейнера отмечаются как отсутствующие. Вся операция выполняется в транзакции.
 */
final class InventoryCloseService
{
    /**
     * Закрывает инвентаризацию как одну атомарную бизнес-операцию.
     *
     * @param Inventory $inventory Закрываемая инвентаризация.
     * @param Item $container Контейнер, внутри которого проводилась инвентаризация.
     * @param User<Identity> $user Пользователь, закрывающий инвентаризацию.
     * @param ItemAccessValidator $itemAccessValidator Валидатор прав, который будет передан изменяемым предметам.
     * @param ?int $closedAt Фиксированное время закрытия; полезно для тестов. Если не задано, используется текущее время.
     *
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
     * Выполняет изменения предметов и инвентаризации внутри уже открытой транзакции.
     *
     * @param User<Identity> $user Пользователь, закрывающий инвентаризацию.
     *
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
     * Сохраняет предмет и превращает ошибки валидации в информативное исключение.
     *
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
