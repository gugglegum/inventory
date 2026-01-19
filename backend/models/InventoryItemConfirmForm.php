<?php

declare(strict_types=1);

namespace backend\models;

use common\models\InventoryItem;

final class InventoryItemConfirmForm extends InventoryItem
{
    public function formName(): string
    {
        return 'confirm';
    }

    public function init(): void
    {
        parent::init();
        $this->scenario = self::SCENARIO_CONFIRM;
    }
}
