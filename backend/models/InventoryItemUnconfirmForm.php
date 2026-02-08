<?php

declare(strict_types=1);

namespace backend\models;

final class InventoryItemUnconfirmForm extends InventoryItemAbstractForm
{
    public function formName(): string
    {
        return 'unconfirm';
    }
}
