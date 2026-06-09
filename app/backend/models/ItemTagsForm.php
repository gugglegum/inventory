<?php

namespace backend\models;

use yii\base\Model;

class ItemTagsForm extends Model
{
    /**
     * Строка пользовательских тегов через запятую.
     */
    public string $tags = '';

    public function rules(): array
    {
        return [
            ['tags', 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'tags' => 'Метки',
        ];
    }

}
