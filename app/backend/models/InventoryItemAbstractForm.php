<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Item;
use yii\base\Model;

abstract class InventoryItemAbstractForm extends Model
{
    public ?int $repoId = null;
    public ?int $itemId = null;

    public function rules(): array
    {
        return [
            [['itemId', 'repoId'], 'required'],
            [['itemId', 'repoId'], 'integer'],
            ['itemId', 'exist', 'skipOnError' => true, 'targetClass' => Item::class, 'targetAttribute' => ['itemId' => 'itemId', 'repoId' => 'repoId']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'repoId' => 'Repo ID',
            'itemId' => 'Item ID',
        ];
    }
}
