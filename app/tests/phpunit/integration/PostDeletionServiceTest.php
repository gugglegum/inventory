<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\PostDeletionService;
use common\models\Post;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты сервиса удаления заметок.
 *
 * Проверяют удаление Post без HTTP-обвязки PostsController.
 */
final class PostDeletionServiceTest extends DbTestCase
{
    /**
     * delete() удаляет заметку из базы.
     */
    public function testDeleteRemovesPostFromDatabase(): void
    {
        $post = $this->preparePostFixture();

        $result = (new PostDeletionService())->delete($post);

        self::assertTrue($result);
        self::assertNull(Post::findOne($post->id));
    }

    /**
     * Создает заметку к предмету.
     */
    private function preparePostFixture(): Post
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'Предмет с удаляемой заметкой',
        ]);

        return $this->createPost($item, $user, [
            'title' => 'Удаляемая заметка',
        ]);
    }
}
