<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[Item]].
 *
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

    /**
     * @inheritdoc
     * @return Item[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Item|array|null
     */
    public function one($db = null): array|Item|null
    {
        return parent::one($db);
    }
}
