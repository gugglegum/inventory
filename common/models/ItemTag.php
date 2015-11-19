<?php

namespace common\models;

use Yii;
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
    public static function tableName()
    {
        return 'items_tags';
    }

    /**
     * @inheritdoc
     */
    public function rules()
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
    public function attributeLabels()
    {
        return [
            'itemId' => 'ID предмета',
            'tag' => 'Метка',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItem()
    {
        return $this->hasOne(Item::className(), ['id' => 'itemId']);
    }

    /**
     * @inheritdoc
     * @return ItemTagQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ItemTagQuery(get_called_class());
    }

}
