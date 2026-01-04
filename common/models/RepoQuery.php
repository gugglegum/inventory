<?php

namespace common\models;

use yii\db\ActiveQuery;

/**
 * This is the ActiveQuery class for [[Repo]].
 *
 * @see Repo
 */
class RepoQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     * @return Repo[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * @inheritdoc
     * @return Repo|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}
