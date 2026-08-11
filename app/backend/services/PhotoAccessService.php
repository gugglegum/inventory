<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\RepoUser;
use yii\db\Query;

/**
 * Проверяет право пользователя читать фотографию через связанный репозиторий.
 */
final class PhotoAccessService
{
    public function canView(Photo $photo, int $userId): bool
    {
        return $this->canViewItemPhoto((int) $photo->id, $userId)
            || $this->canViewPostPhoto((int) $photo->id, $userId);
    }

    private function canViewItemPhoto(int $photoId, int $userId): bool
    {
        return (new Query())
            ->from(['itemPhoto' => ItemPhoto::tableName()])
            ->innerJoin(['item' => Item::tableName()], '[[item.id]] = [[itemPhoto.itemId]]')
            ->innerJoin(['repoUser' => RepoUser::tableName()], '[[repoUser.repoId]] = [[item.repoId]]')
            ->where([
                'itemPhoto.photoId' => $photoId,
                'item.deleted' => null,
                'repoUser.userId' => $userId,
            ])
            ->exists();
    }

    private function canViewPostPhoto(int $photoId, int $userId): bool
    {
        return (new Query())
            ->from(['postPhoto' => PostPhoto::tableName()])
            ->innerJoin(['post' => Post::tableName()], '[[post.id]] = [[postPhoto.postId]]')
            ->innerJoin(['item' => Item::tableName()], '[[item.id]] = [[post.itemId]]')
            ->innerJoin(['repoUser' => RepoUser::tableName()], '[[repoUser.repoId]] = [[item.repoId]]')
            ->where([
                'postPhoto.photoId' => $photoId,
                'item.deleted' => null,
                'repoUser.userId' => $userId,
            ])
            ->exists();
    }
}
