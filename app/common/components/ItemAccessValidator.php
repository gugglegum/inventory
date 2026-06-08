<?php

declare(strict_types=1);

namespace common\components;

use common\models\Repo;
use common\models\RepoUser;
use Yii;

final class ItemAccessValidator
{
    /**
     * Компонент авторизации текущего пользователя приложения.
     *
     * @var \yii\web\User<\common\models\User>
     */
    private \yii\web\User $user;

    /**
     * Возвращает компонент авторизации пользователя, заданный явно или взятый из приложения.
     *
     * @return \yii\web\User<\common\models\User>
     */
    public function getUser(): \yii\web\User
    {
        return $this->user ?? Yii::$app->getUser();
    }

    /**
     * Задает компонент авторизации пользователя для проверки прав.
     *
     * @param \yii\web\User<\common\models\User> $user
     */
    public function setUser(\yii\web\User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function hasUserAccessToRepoById(int $repoId, int $accessType): bool
    {
        if (($repo = Repo::findOne($repoId)) !== null) {
            return $this->hasUserAccessToRepo($repo, $accessType);
        }
        return false;
    }

    public function hasUserAccessToRepo(Repo $repo, int $accessType): bool
    {
        /** @var RepoUser|null $repoUser */
        $repoUser = $repo->getRepoUsers()->where(['userId' => $this->getUser()->id])->one();
        if ($repoUser !== null) {
            if (($repoUser->access & $accessType) === $accessType) {
                return true;
            }
        }
        return false;
    }

}
