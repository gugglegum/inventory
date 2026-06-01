<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[ItemRelation]].
 *
 * @see ItemRelation
 */
class ItemRelationQuery extends ActiveQuery
{
    /*public function active()
    {
        $this->andWhere('[[status]]=1');
        return $this;
    }*/

    /**
     * @inheritdoc
     * @return ItemRelation[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return ItemRelation|array|null
     */
    public function one($db = null): ItemRelation|array|null
    {
        return parent::one($db);
    }
}
