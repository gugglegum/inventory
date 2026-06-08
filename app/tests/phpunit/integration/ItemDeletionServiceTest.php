<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\ItemDeletionService;
use common\models\Item;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса удаления предметов.
 *
 * Проверяют мягкое и жесткое удаление без HTTP-обвязки ItemsController и формы подтверждения.
 */
final class ItemDeletionServiceTest extends DbTestCase
{
    /**
     * Мягкое удаление скрывает предмет из Item::find() и заполняет deleted/deletedBy.
     */
    public function testSoftDeleteMarksItemAsDeleted(): void
    {
        $item = $this->prepareItemFixture();

        $result = (new ItemDeletionService())->delete($item, false, Yii::$app->getUser());

        self::assertFalse($result->hasError());
        self::assertNull($result->errorMessage);
        self::assertNull(Item::findOne($item->id));

        $deletedItem = Item::findWithDeleted()->where(['id' => $item->id])->one();
        self::assertNotNull($deletedItem);
        self::assertNotNull($deletedItem->deleted);
        self::assertSame((int) Yii::$app->user->id, (int) $deletedItem->deletedBy);
    }

    /**
     * Жесткое удаление полностью удаляет предмет из базы.
     */
    public function testHardDeleteRemovesItemFromDatabase(): void
    {
        $item = $this->prepareItemFixture();

        $result = (new ItemDeletionService())->delete($item, true, Yii::$app->getUser());

        self::assertFalse($result->hasError());
        self::assertNull($result->errorMessage);
        self::assertNull(Item::findWithDeleted()->where(['id' => $item->id])->one());
    }

    /**
     * Создает предмет с правами на удаление в текущем тестовом пользователе.
     */
    private function prepareItemFixture(): Item
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS
        );

        return $this->createItem($repo, $user, [
            'name' => 'Удаляемый предмет',
        ]);
    }
}
