<?php

declare(strict_types=1);

namespace backend\models;

final class InventoryItemConfirmForm extends InventoryItemAbstractForm
{
    public function formName(): string
    {
        return 'confirm';
    }
}
