<?php

declare(strict_types=1);

namespace common\components;

use common\models\Repo;
use common\models\RepoUser;
use Yii;

final class ItemAccessValidator
{
    private \yii\web\User $user;

    public function getUser(): \yii\web\User
    {
        return $this->user ?? Yii::$app->getUser();
    }

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
        /** @var ?RepoUser $repoUser */
        if ($repoUser = $repo->getRepoUsers()->where(['userId' => $this->getUser()->id])->one()) {
            if (($repoUser->access & $accessType) === $accessType) {
                return true;
            }
        }
        return false;
    }

}
