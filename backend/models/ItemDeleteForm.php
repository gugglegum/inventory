<?php

declare(strict_types=1);

namespace backend\models;

use common\models\Item;
use yii\base\Model;

class ItemDeleteForm extends Model
{
    public bool $hardDelete = false;

    private Item $item;

    public function getItem(): Item
    {
        return $this->item;
    }

    public function setItem(Item $item): static
    {
        $this->item = $item;
        return $this;
    }

    public function attributeLabels(): array
    {
        return [
            'hardDelete' => 'Полное удаление без возможности восстановления',
        ];
    }

    public function rules(): array
    {
        return [
            ['hardDelete', 'boolean'],
            ['hardDelete', 'default', 'value' => false],
        ];
    }

    /**
     * @return bool
     * @throws \yii\db\Exception
     * @throws \Throwable
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        if ($this->hardDelete) {
            if (($result = $this->item->delete()) === false) {
                $msg = $this->item->getFirstError('');
                $this->addError('', 'Ошибка при полном (жёстком) удалении предмета' . ($msg ? ': ' . $msg : ''));
            } else {
                $result = true;
            }
        } else {
            if (($result = $this->item->softDelete(\Yii::$app->getUser()->getId())) === false) {
                $msg = $this->item->getFirstError('');
                $this->addError('', 'Ошибка при мягком удалении предмета' . ($msg ? ': ' . $msg : ''));
            }
        }
        return $result;
    }
}
