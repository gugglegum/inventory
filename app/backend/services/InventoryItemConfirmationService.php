<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Inventory;
use common\models\InventoryItem;
use common\models\Item;
use yii\db\StaleObjectException;
use yii\web\User;

/**
 * Подтверждает и снимает подтверждение предметов внутри открытой инвентаризации.
 *
 * Сервис инкапсулирует мутации таблицы inventory_item, оставляя контроллеру только HTTP-обвязку,
 * загрузку форм и выбор редиректа или повторного render.
 */
final class InventoryItemConfirmationService
{
    /**
     * Создает отметку, что предмет найден в рамках инвентаризации.
     *
     * @param Inventory $inventory Инвентаризация, в которой подтверждается предмет.
     * @param Item $item Предмет, который пользователь отметил найденным.
     * @param User $user Текущий пользователь, записываемый в createdBy.
     */
    public function confirm(Inventory $inventory, Item $item, User $user): InventoryItemConfirmationResult
    {
        $inventoryItem = new InventoryItem([
            'inventoryId' => $inventory->id,
            'itemId' => $item->id,
            'createdBy' => $user->id,
        ]);

        if ($inventoryItem->save()) {
            return InventoryItemConfirmationResult::success($inventoryItem);
        }

        return InventoryItemConfirmationResult::failure(
            array_values($inventoryItem->getFirstErrors())[0] ?? 'Unknown error'
        );
    }

    /**
     * Удаляет отметку о том, что предмет найден в рамках инвентаризации.
     *
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function unconfirm(Inventory $inventory, Item $item): bool
    {
        $inventoryItem = $inventory->getInventoryItems()
            ->andWhere(['inventory_item.itemId' => $item->id])
            ->one();

        return $inventoryItem !== null && $inventoryItem->delete() !== false;
    }
}
