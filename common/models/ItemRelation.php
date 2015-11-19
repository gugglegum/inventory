<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "items_relations".
 *
 * @property string $srcItemId
 * @property string $dstItemId
 * @property string $type
 * @property string $description
 * @property integer $created
 *
 * @property Item $srcItem
 * @property Item $dstItem
 */
class ItemRelation extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'items_relations';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['srcItemId', 'dstItemId', 'type', 'description', 'created'], 'required'],
            [['srcItemId', 'dstItemId', 'type', 'created'], 'integer'],
            [['description'], 'string']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'srcItemId' => 'Исходный предмет',
            'dstItemId' => 'Предмет назначения',
            'type' => 'Код типа связи',
            'description' => 'Описание отношения',
            'created' => 'Время создания связи',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSrcItem()
    {
        return $this->hasOne(Item::className(), ['id' => 'srcItemId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDstItem()
    {
        return $this->hasOne(Item::className(), ['id' => 'dstItemId']);
    }

    /**
     * @inheritdoc
     * @return ItemRelationQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ItemRelationQuery(get_called_class());
    }
}
