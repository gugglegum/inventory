<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[Post]].
 *
 * @template TModel of Post
 * @extends ActiveQuery<TModel>
 * @see Post
 */
class PostQuery extends ActiveQuery
{
    /*public function active()
    {
        $this->andWhere('[[status]]=1');
        return $this;
    }*/

}
