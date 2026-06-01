<?php

declare(strict_types=1);

namespace common\components;

use common\models\User;

final class UserAccess
{
    public static function canManageUsers(): bool
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;
        return ($user->access & User::ACCESS_MANAGE_USERS) === User::ACCESS_MANAGE_USERS;
    }

    public static function canCreateRepo(): bool
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;
        return ($user->access & User::ACCESS_CREATE_REPO) === User::ACCESS_CREATE_REPO;
    }
}
