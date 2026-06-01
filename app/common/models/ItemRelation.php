<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "item_relation".
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
    public static function tableName(): string
    {
        return 'item_relation';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
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
    public function attributeLabels(): array
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
    public function getSrcItem(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'srcItemId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDstItem(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'dstItemId']);
    }

    /**
     * @inheritdoc
     * @return ItemRelationQuery the active query used by this AR class.
     */
    public static function find(): ItemRelationQuery
    {
        return new ItemRelationQuery(get_called_class());
    }
}
