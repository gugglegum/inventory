<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[User]].
 *
 * @template TModel of User
 * @extends ActiveQuery<TModel>
 * @see User
 */
class UserQuery extends ActiveQuery
{
    public function active(): static
    {
        $this->andWhere('[[status]] = :status', ['status' => User::STATUS_ACTIVE]);
        return $this;
    }

}
