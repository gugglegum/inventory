<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use common\services\RepoDeletionCascadeService;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты каскадного удаления репозитория из common-сервиса.
 *
 * Проверяют поведение, которое раньше находилось непосредственно в Repo::beforeDelete().
 */
final class RepoDeletionCascadeServiceTest extends DbTestCase
{
    /**
     * Repo::delete() через делегирующий hook удаляет корневые и вложенные предметы.
     */
    public function testRepoDeleteCascadesRootAndNestedItems(): void
    {
        [$repo, $rootItem, $childItem] = $this->prepareRepoFixture(
            RepoUser::ACCESS_CREATE_ITEMS
            | RepoUser::ACCESS_EDIT_ITEMS
            | RepoUser::ACCESS_DELETE_ITEMS
            | RepoUser::ACCESS_DELETE_REPO
        );
        $repo->setItemAccessValidator(new ItemAccessValidator()->setUser(Yii::$app->getUser()));

        self::assertTrue($repo->delete() !== false);

        self::assertNull(Repo::findOne($repo->id));
        self::assertNull(Item::findWithDeleted()->where(['id' => $rootItem->id])->one());
        self::assertNull(Item::findWithDeleted()->where(['id' => $childItem->id])->one());
    }

    /**
     * beforeDelete() без права удаления репозитория возвращает false и добавляет ошибку.
     */
    public function testBeforeDeleteWithoutRepoDeleteAccessAddsError(): void
    {
        [$repo, $rootItem] = $this->prepareRepoFixture(
            RepoUser::ACCESS_CREATE_ITEMS
            | RepoUser::ACCESS_EDIT_ITEMS
            | RepoUser::ACCESS_DELETE_ITEMS
        );
        $itemAccessValidator = new ItemAccessValidator()->setUser(Yii::$app->getUser());

        $result = (new RepoDeletionCascadeService())->beforeDelete($repo, $itemAccessValidator);

        self::assertFalse($result);
        self::assertSame('Недостаточно прав для удаления репозитория.', $repo->getFirstError(''));
        self::assertNotNull(Repo::findOne($repo->id));
        self::assertNotNull(Item::findOne($rootItem->id));
    }

    /**
     * Создает репозиторий с корневым контейнером и дочерним предметом.
     *
     * @return array{0:Repo, 1:Item, 2:Item}
     */
    private function prepareRepoFixture(int $access): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, $access);

        $rootItem = $this->createItem($repo, $user, [
            'name' => 'Корневой контейнер репозитория',
            'isContainer' => true,
        ]);
        $childItem = $this->createItem($repo, $user, [
            'name' => 'Дочерний предмет репозитория',
            'parentItemId' => $rootItem->itemId,
        ]);

        return [$repo, $rootItem, $childItem];
    }
}
