<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\models\RepoForm;
use backend\services\RepoFormService;
use common\components\ItemAccessValidator;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;

/**
 * Integration-тесты сервиса подготовки и сохранения формы репозитория.
 *
 * Проверяют create/update сценарии RepoForm без HTTP-обвязки RepoController.
 */
final class RepoFormServiceTest extends DbTestCase
{
    /**
     * prepareForCreate() готовит форму, а save() создает репозиторий и owner-доступ.
     */
    public function testPrepareForCreateAndSaveCreatesRepoWithOwnerAccess(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $this->login($user);
        $service = new RepoFormService();

        $repoForm = $service->prepareForCreate(new ItemAccessValidator()->setUser(Yii::$app->getUser()));
        $result = $service->save($repoForm, [
            'RepoForm' => [
                'name' => 'Новый репозиторий',
                'description' => 'Описание репозитория',
                'priority' => '7',
            ],
        ]);

        $repo = Repo::findOne(['name' => 'Новый репозиторий']);
        $repoUser = $repo !== null ? RepoUser::findOne(['repoId' => $repo->id, 'userId' => $user->id]) : null;

        self::assertTrue($result);
        self::assertSame(RepoForm::SCENARIO_CREATE, $repoForm->scenario);
        self::assertNotNull($repo);
        self::assertSame('Описание репозитория', $repo->description);
        self::assertSame(0, (int) $repo->lastItemId);
        self::assertSame((int) $user->id, (int) $repo->createdBy);
        self::assertNotNull($repoUser);
        self::assertSame(7, (int) $repoUser->priority);
        self::assertSame(
            RepoUser::ACCESS_CREATE_ITEMS
                | RepoUser::ACCESS_EDIT_ITEMS
                | RepoUser::ACCESS_DELETE_ITEMS
                | RepoUser::ACCESS_EDIT_REPO
                | RepoUser::ACCESS_DELETE_REPO,
            (int) $repoUser->access
        );
    }

    /**
     * prepareForUpdate() заполняет форму текущими значениями, а save() обновляет репозиторий и priority.
     */
    public function testPrepareForUpdateAndSaveUpdatesRepoAndPriority(): void
    {
        [$repo, $repoUser] = $this->prepareUpdateFixture();
        $repo->setItemAccessValidator(new ItemAccessValidator()->setUser(Yii::$app->getUser()));
        $service = new RepoFormService();

        $repoForm = $service->prepareForUpdate($repo, $repoUser);

        self::assertSame('Редактируемый репозиторий', $repoForm->name);
        self::assertSame('Старое описание', $repoForm->description);
        self::assertSame(3, (int) $repoForm->priority);

        $result = $service->save($repoForm, [
            'RepoForm' => [
                'name' => 'Обновленный репозиторий',
                'description' => 'Новое описание',
                'lastItemId' => '42',
                'priority' => '9',
            ],
        ]);

        $repo->refresh();
        $repoUser->refresh();

        self::assertTrue($result);
        self::assertSame(RepoForm::SCENARIO_UPDATE, $repoForm->scenario);
        self::assertSame('Обновленный репозиторий', $repo->name);
        self::assertSame('Новое описание', $repo->description);
        self::assertSame(42, (int) $repo->lastItemId);
        self::assertSame((int) Yii::$app->user->id, (int) $repo->updatedBy);
        self::assertSame(9, (int) $repoUser->priority);
    }

    /**
     * Создает репозиторий с правами на редактирование.
     *
     * @return array{0:Repo, 1:RepoUser}
     */
    private function prepareUpdateFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user, [
            'name' => 'Редактируемый репозиторий',
            'description' => 'Старое описание',
            'lastItemId' => 5,
        ]);
        $repoUser = $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_EDIT_REPO
        );
        $repoUser->priority = 3;
        $this->saveModel($repoUser);

        return [$repo, $repoUser];
    }
}
