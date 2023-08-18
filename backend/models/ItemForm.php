<?php

namespace backend\models;

use yii\base\Model;

class ItemForm extends Model
{
    public $id;
    public $parentId;
    public $name;
    public $description;
    public $priority;
    public $isContainer;
    public $created;
    public $updated;

    public function rules(): array
    {
        return [
            [['parentId', 'name', 'isContainer'], 'required'],
        ];
    }
}
