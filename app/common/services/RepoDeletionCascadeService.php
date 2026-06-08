<?php

declare(strict_types=1);

namespace common\services;

use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use yii\db\StaleObjectException;

/**
 * Выполняет каскадные операции удаления репозитория.
 *
 * Сервис отделяет обход корневых предметов от ActiveRecord hook модели Repo, сохраняя прежнюю
 * точку входа через Repo::delete().
 */
final class RepoDeletionCascadeService
{
    /**
     * Проверяет права и удаляет корневые предметы репозитория перед удалением самого Repo.
     *
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function beforeDelete(Repo $repo, ItemAccessValidator $itemAccessValidator): bool
    {
        if (!$itemAccessValidator->hasUserAccessToRepoById($repo->id, RepoUser::ACCESS_DELETE_REPO)) {
            $repo->addError('', 'Недостаточно прав для удаления репозитория.');
            return false;
        }

        /** @var Item $item */
        foreach ($repo->getItems()->where(['parentItemId' => null])->each() as $item) {
            $item->setItemAccessValidator($itemAccessValidator);
            $item->delete();
        }

        return true;
    }
}
