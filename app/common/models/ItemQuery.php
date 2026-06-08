<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[Item]].
 *
 * @template TModel of Item
 * @extends ActiveQuery<TModel>
 * @see Item
 */
class ItemQuery extends ActiveQuery
{
    public function notDeleted(): self
    {
        return $this->andWhere(['deleted' => null]);
    }

    public function onlyDeleted(): self
    {
        return $this->andWhere(['not', ['deleted' => null]]);
    }

}
