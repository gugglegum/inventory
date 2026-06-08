<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\controllers\RepoAwareController;
use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\User as WebUser;

/**
 * Integration-тесты общего access/context-слоя repo-aware контроллеров.
 *
 * Фиксируют поведение защищенных helper-методов, которые используют Items/Posts/Inventory/Repo контроллеры.
 */
final class RepoAwareControllerTest extends DbTestCase
{
    /**
     * findRepo() проверяет права текущего пользователя и прикрепляет валидатор для последующего save().
     */
    public function testFindRepoChecksAccessAndAttachesValidator(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user, [
            'name' => 'Репозиторий до обновления',
        ]);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_EDIT_REPO);
        $controller = new RepoAwareControllerProbe('repo-aware-probe', Yii::$app);

        $resolvedRepo = $controller->publicFindRepo($repo->id, RepoUser::ACCESS_EDIT_REPO);
        $resolvedRepo->name = 'Репозиторий после обновления';

        $this->saveModel($resolvedRepo);

        $updatedRepo = Repo::findOne($repo->id);
        self::assertNotNull($updatedRepo);
        self::assertSame('Репозиторий после обновления', $updatedRepo->name);
    }

    /**
     * findItem() возвращает предмет с валидатором, достаточным для сохранения изменений.
     */
    public function testFindItemAttachesValidator(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);
        $item = $this->createItem($repo, $user, [
            'name' => 'Предмет до обновления',
        ]);
        $controller = new RepoAwareControllerProbe('repo-aware-probe', Yii::$app);

        $resolvedItem = $controller->publicFindItem($repo->id, $item->itemId);
        $resolvedItem->scenario = Item::SCENARIO_UPDATE;
        $resolvedItem->name = 'Предмет после обновления';

        $this->saveModel($resolvedItem);

        $updatedItem = Item::findOne($item->id);
        self::assertNotNull($updatedItem);
        self::assertSame('Предмет после обновления', $updatedItem->name);
    }

    /**
     * findRepo() запрещает доступ пользователю без строки repo_user.
     */
    public function testFindRepoThrowsForbiddenForUserWithoutAccessRow(): void
    {
        $owner = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($owner);
        $otherUser = $this->createUser();
        $this->login($otherUser);
        $controller = new RepoAwareControllerProbe('repo-aware-probe', Yii::$app);

        $this->expectException(ForbiddenHttpException::class);

        $controller->publicFindRepo($repo->id);
    }

    /**
     * findRepoUser() возвращает персональную связь repo_user текущего пользователя.
     */
    public function testFindRepoUserReturnsCurrentUserAccessRow(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $repoUser = $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS);
        $repoUser->priority = 7;
        $this->saveModel($repoUser);
        $controller = new RepoAwareControllerProbe('repo-aware-probe', Yii::$app);

        $resolvedRepoUser = $controller->publicFindRepoUser($repo);

        self::assertSame($repoUser->repoId, $resolvedRepoUser->repoId);
        self::assertSame($repoUser->userId, $resolvedRepoUser->userId);
        self::assertSame(7, (int) $resolvedRepoUser->priority);
    }

    /**
     * getLoggedUser() возвращает тот же Yii user-компонент, с которым работает приложение.
     */
    public function testGetLoggedUserReturnsYiiUserComponent(): void
    {
        $controller = new RepoAwareControllerProbe('repo-aware-probe', Yii::$app);

        self::assertSame(Yii::$app->getUser(), $controller->publicGetLoggedUser());
    }
}

/**
 * Тестовый адаптер для доступа к защищенным методам RepoAwareController.
 */
final class RepoAwareControllerProbe extends RepoAwareController
{
    public function publicFindRepo(int $repoId, int $accessType = 0): Repo
    {
        return $this->findRepo($repoId, $accessType);
    }

    public function publicFindItem(int $repoId, int $itemId): Item
    {
        return $this->findItem($repoId, $itemId);
    }

    public function publicFindRepoUser(Repo $repo): RepoUser
    {
        return $this->findRepoUser($repo);
    }

    public function publicGetLoggedUser(): WebUser
    {
        return $this->getLoggedUser();
    }
}
