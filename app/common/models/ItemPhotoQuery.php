<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[ItemPhoto]].
 *
 * @see ItemPhoto
 */
class ItemPhotoQuery extends ActiveQuery
{
    /*public function active()
    {
        $this->andWhere('[[status]]=1');
        return $this;
    }*/

    /**
     * @inheritdoc
     * @return ItemPhoto[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return ItemPhoto|array|null
     */
    public function one($db = null): ItemPhoto|array|null
    {
        return parent::one($db);
    }
}
