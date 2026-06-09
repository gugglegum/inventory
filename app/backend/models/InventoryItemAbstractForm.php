<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Item;
use yii\base\Model;

abstract class InventoryItemAbstractForm extends Model
{
    /**
     * ID репозитория из серверного контекста формы.
     */
    public string $repoId = '';

    /**
     * Введенный пользователем itemId внутри репозитория.
     */
    public string $itemId = '';

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

    /**
     * Возвращает itemId как число после успешной валидации.
     */
    public function getItemId(): int
    {
        return (int) $this->itemId;
    }
}
