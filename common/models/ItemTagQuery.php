<?php

namespace common\models;
use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[ItemTag]].
 *
 * @see ItemTag
 */
class ItemTagQuery extends ActiveQuery
{
    /*public function active()
    {
        $this->andWhere('[[status]]=1');
        return $this;
    }*/

    /**
     * @inheritdoc
     * @return ItemTag[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return ItemTag|array|null
     */
    public function one($db = null): ItemTag|array|null
    {
        return parent::one($db);
    }
}
