<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Post;
use yii\db\StaleObjectException;

/**
 * Выполняет удаление заметки к предмету.
 *
 * Сервис оставляет PostsController только выбор HTTP-ответа после подтверждения удаления.
 */
final class PostDeletionService
{
    /**
     * Удаляет заметку вместе со связанными PostPhoto через модельные hooks.
     *
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function delete(Post $post): bool
    {
        return $post->delete() !== false;
    }
}
