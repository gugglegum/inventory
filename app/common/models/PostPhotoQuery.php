<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[PostPhoto]].
 *
 * @see PostPhoto
 */
class PostPhotoQuery extends ActiveQuery
{
    /*public function active()
    {
        $this->andWhere('[[status]]=1');
        return $this;
    }*/

    /**
     * @inheritdoc
     * @return PostPhoto[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return PostPhoto|array|null
     */
    public function one($db = null): PostPhoto|array|null
    {
        return parent::one($db);
    }
}
