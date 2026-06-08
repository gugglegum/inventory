<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[Photo]].
 *
 * @template TModel of Photo
 * @extends ActiveQuery<TModel>
 * @see Photo
 */
class PhotoQuery extends ActiveQuery
{
    /*public function active()
    {
        $this->andWhere('[[status]]=1');
        return $this;
    }*/

}
