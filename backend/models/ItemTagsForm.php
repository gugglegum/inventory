<?php

namespace backend\models;

use yii\base\Model;

class ItemTagsForm extends Model
{
    public $tags;

    public function rules()
    {
        return [
            ['tags', 'string'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'tags' => 'Метки',
        ];
    }

}
