<?php

declare(strict_types=1);

namespace backend\services;

use common\components\ItemAccessValidator;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use yii\db\StaleObjectException;
use yii\web\User as WebUser;

/**
 * Готовит данные для удаления репозитория и выполняет само удаление.
 *
 * Сервис выносит из RepoController список затронутых пользователей и вызов модельного удаления.
 */
final class RepoDeletionService
{
    /**
     * Возвращает активных пользователей, кроме текущего, которые потеряют доступ при удалении репозитория.
     *
     * @param WebUser<User> $currentUser Текущий пользователь, который сохраняет доступ к удаляемому репозиторию.
     * @return User[]
     */
    public function getAffectedUsers(Repo $repo, WebUser $currentUser): array
    {
        $affectedUsers = [];
        foreach ($repo->getRepoUsers()->innerJoinWith('user')->where(['user.status' => User::STATUS_ACTIVE])->each() as $repoUser) {
            /** @var RepoUser $repoUser */
            if ((int) $repoUser->userId !== (int) $currentUser->id) {
                $affectedUsers[] = $repoUser->user;
            }
        }

        return $affectedUsers;
    }

    /**
     * Удаляет репозиторий через модельные hooks.
     *
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function delete(Repo $repo): bool
    {
        if (!(new ItemAccessValidator())->hasUserAccessToRepo($repo, RepoUser::ACCESS_DELETE_REPO)) {
            $repo->addError('', 'Недостаточно прав для удаления репозитория.');
            return false;
        }

        (new PhotoUploadService())->discardSessionsForRepo((int) $repo->id);

        return $repo->delete() !== false;
    }
}
