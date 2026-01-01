<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "items_tags".
 *
 * @property string $itemId
 * @property string $tag
 *
 * @property Item $item
 */
class ItemTag extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'item_tag';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['itemId', 'tag'], 'required'],
            [['itemId'], 'integer'],
            [['tag'], 'string', 'max' => 40]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'itemId' => 'ID предмета',
            'tag' => 'Метка',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItem(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'itemId']);
    }

    /**
     * @inheritdoc
     * @return ItemTagQuery the active query used by this AR class.
     */
    public static function find(): ItemTagQuery
    {
        return new ItemTagQuery(get_called_class());
    }

}
