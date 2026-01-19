<?php

declare(strict_types=1);

namespace backend\models;

use common\models\InventoryItem;
use yii\db\StaleObjectException;

final class InventoryItemUnconfirmForm extends InventoryItem
{
    public function formName(): string
    {
        return 'unconfirm';
    }

    public function init(): void
    {
        parent::init();
        $this->scenario = self::SCENARIO_UNCONFIRM;
    }

    /**
     * @inheritdoc
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function save($runValidation = true, $attributeNames = null): bool
    {
        $inventoryItem = $this->inventory->getInventoryItems()->andWhere(['inventory_item.itemId' => $this->itemId])->one();
        return (bool) $inventoryItem->delete();
    }
}
