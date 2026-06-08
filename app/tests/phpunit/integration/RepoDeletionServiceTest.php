<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\RepoDeletionService;
use common\components\ItemAccessValidator;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса удаления репозитория.
 *
 * Проверяют список затронутых пользователей и удаление без HTTP-обвязки RepoController.
 */
final class RepoDeletionServiceTest extends DbTestCase
{
    /**
     * getAffectedUsers() возвращает только активных пользователей, кроме текущего.
     */
    public function testGetAffectedUsersReturnsActiveUsersExceptCurrentUser(): void
    {
        [$repo, $owner, $activeUser, $inactiveUser] = $this->prepareAffectedUsersFixture();
        $service = new RepoDeletionService();

        $affectedUsers = $service->getAffectedUsers($repo, Yii::$app->getUser());

        self::assertSame([(int) $activeUser->id], array_map(static fn(User $user): int => (int) $user->id, $affectedUsers));
    }

    /**
     * delete() удаляет репозиторий через модельные hooks.
     */
    public function testDeleteRemovesRepoFromDatabase(): void
    {
        [$repo] = $this->prepareDeleteFixture();
        $repo->setItemAccessValidator(new ItemAccessValidator()->setUser(Yii::$app->getUser()));

        $result = (new RepoDeletionService())->delete($repo);

        self::assertTrue($result);
        self::assertNull(Repo::findOne($repo->id));
    }

    /**
     * Создает репозиторий с несколькими пользователями доступа.
     *
     * @return array{0:Repo, 1:User, 2:User, 3:User}
     */
    private function prepareAffectedUsersFixture(): array
    {
        [$repo, $owner] = $this->prepareDeleteFixture();
        $activeUser = $this->createUser();
        $inactiveUser = $this->createUser();
        $inactiveUser->status = User::STATUS_DELETED;
        $this->saveModel($inactiveUser);

        $this->grantRepoAccess($repo, $activeUser, RepoUser::ACCESS_READONLY);
        $this->grantRepoAccess($repo, $inactiveUser, RepoUser::ACCESS_READONLY);
        $this->login($owner);

        return [$repo, $owner, $activeUser, $inactiveUser];
    }

    /**
     * Создает репозиторий с правом удаления у текущего пользователя.
     *
     * @return array{0:Repo, 1:User}
     */
    private function prepareDeleteFixture(): array
    {
        $owner = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($owner, [
            'name' => 'Удаляемый репозиторий',
        ]);
        $this->grantRepoAccess(
            $repo,
            $owner,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS | RepoUser::ACCESS_DELETE_REPO
        );

        return [$repo, $owner];
    }
}
