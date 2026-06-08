<?php

namespace common\models;

use yii\db\ActiveQuery;
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
     * @return ActiveQuery<Item>
     */
    public function getSrcItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'srcItemId']);
    }

    /**
     * @return ActiveQuery<Item>
     */
    public function getDstItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'dstItemId']);
    }

    /**
     * @inheritdoc
     * @return ItemRelationQuery<static> the active query used by this AR class.
     */
    public static function find(): ItemRelationQuery
    {
        /** @var ItemRelationQuery<static> $query */
        $query = new ItemRelationQuery(static::class);
        return $query;
    }
}
