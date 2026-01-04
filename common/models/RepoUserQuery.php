<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[RepoUser]].
 *
 * @see RepoUser
 */
class RepoUserQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     * @return RepoUser[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return RepoUser|array|null
     */
    public function one($db = null): RepoUser|array|null
    {
        return parent::one($db);
    }
}
