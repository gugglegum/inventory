<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\Post;
use common\models\PostPhoto;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use common\services\ItemDeletionCascadeService;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты каскадного удаления предметов из common-сервиса.
 *
 * Проверяют поведение, которое раньше находилось непосредственно в Item::softDelete()/beforeDelete().
 */
final class ItemDeletionCascadeServiceTest extends DbTestCase
{
    /**
     * softDelete() мягко удаляет контейнер и его дочерние предметы.
     */
    public function testSoftDeleteMarksNestedItemsAsDeleted(): void
    {
        [$repo, $user, $container, $child] = $this->prepareNestedItemsFixture(RepoUser::ACCESS_DELETE_ITEMS);
        $itemAccessValidator = new ItemAccessValidator()->setUser(Yii::$app->getUser());

        $result = (new ItemDeletionCascadeService())->softDelete($container, (int) $user->id, $itemAccessValidator);

        $deletedContainer = Item::findWithDeleted()->where(['id' => $container->id])->one();
        $deletedChild = Item::findWithDeleted()->where(['id' => $child->id])->one();

        self::assertTrue($result);
        self::assertNull(Item::findOne($container->id));
        self::assertNull(Item::findOne($child->id));
        self::assertNotNull($deletedContainer);
        self::assertNotNull($deletedChild);
        self::assertSame((int) $user->id, (int) $deletedContainer->deletedBy);
        self::assertSame((int) $user->id, (int) $deletedChild->deletedBy);
        self::assertSame((int) $repo->id, (int) $deletedContainer->repoId);
    }

    /**
     * softDelete() без права удаления оставляет предмет видимым и добавляет ошибку модели.
     */
    public function testSoftDeleteWithoutDeleteAccessLeavesItemVisibleAndAddsError(): void
    {
        [, , $container] = $this->prepareNestedItemsFixture(0);
        $itemAccessValidator = new ItemAccessValidator()->setUser(Yii::$app->getUser());

        $result = (new ItemDeletionCascadeService())->softDelete($container, (int) Yii::$app->user->id, $itemAccessValidator);

        self::assertFalse($result);
        self::assertSame('Недостаточно прав для удаления предмета.', $container->getFirstError(''));
        self::assertNotNull(Item::findOne($container->id));
    }

    /**
     * Item::delete() через делегирующий hook удаляет дочерние предметы, заметки и файлы фотографий.
     */
    public function testHardDeleteCascadesNestedItemsPostsAndPhotoFiles(): void
    {
        [, $user, $container, $child] = $this->prepareNestedItemsFixture(RepoUser::ACCESS_DELETE_ITEMS);
        $itemPhoto = $this->createItemPhoto($container);
        $post = $this->createPost($child, $user, [
            'title' => 'Удаляемая заметка',
        ]);
        $postPhoto = $this->createPostPhoto($post);
        $itemPhotoFile = $itemPhoto->photo->getFile();
        $postPhotoFile = $postPhoto->photo->getFile();

        self::assertTrue($container->delete() !== false);

        self::assertNull(Item::findWithDeleted()->where(['id' => $container->id])->one());
        self::assertNull(Item::findWithDeleted()->where(['id' => $child->id])->one());
        self::assertNull(Post::findOne($post->id));
        self::assertNull(ItemPhoto::findOne($itemPhoto->id));
        self::assertNull(PostPhoto::findOne($postPhoto->id));
        self::assertNull(Photo::findOne($itemPhoto->photoId));
        self::assertNull(Photo::findOne($postPhoto->photoId));
        self::assertFileDoesNotExist($itemPhotoFile);
        self::assertFileDoesNotExist($postPhotoFile);
    }

    /**
     * Создает контейнер с дочерним предметом и заданным набором прав.
     *
     * @return array{0:Repo, 1:User, 2:Item, 3:Item}
     */
    private function prepareNestedItemsFixture(int $extraAccess): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | $extraAccess
        );

        $container = $this->createItem($repo, $user, [
            'name' => 'Удаляемый контейнер',
            'isContainer' => true,
        ]);
        $child = $this->createItem($repo, $user, [
            'name' => 'Удаляемый дочерний предмет',
            'parentItemId' => $container->itemId,
        ]);

        return [$repo, $user, $container, $child];
    }
}
