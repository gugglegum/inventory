<?php

namespace backend\models;

use yii\base\Model;

class ItemTagsForm extends Model
{
    public $tags;

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
