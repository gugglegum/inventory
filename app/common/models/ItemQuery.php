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
    /**
     * Добавляет условие выборки только неудаленных предметов.
     *
     * @return $this
     */
    public function notDeleted(): self
    {
        $this->andWhere(['deleted' => null]);

        return $this;
    }

    /**
     * Добавляет условие выборки только мягко удаленных предметов.
     *
     * @return $this
     */
    public function onlyDeleted(): self
    {
        $this->andWhere(['not', ['deleted' => null]]);

        return $this;
    }

}
