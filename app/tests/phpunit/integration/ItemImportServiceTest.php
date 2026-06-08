<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\ItemImportService;
use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;
use Yii;
use yii\base\Exception;

/**
 * Integration-тесты сервиса текстового импорта предметов.
 *
 * Проверяют сохранение подтвержденного импорта на уровне сервиса, без HTTP-обвязки ItemsController.
 */
final class ItemImportServiceTest extends DbTestCase
{
    /**
     * Ошибка сохранения одного предмета откатывает всю пачку импорта.
     */
    public function testConfirmedImportRollsBackAllCreatedItemsOnSaveError(): void
    {
        [$repo, $parent] = $this->prepareFixture();
        $repo->refresh();
        $lastItemIdBeforeImport = (int) $repo->lastItemId;

        try {
            (new ItemImportService())->import(
                $repo,
                $parent,
                implode("\n", [
                    'Первый импортируемый предмет',
                    '#rollback, проверка',
                    str_repeat('x', 201),
                ]),
                true,
                Yii::$app->getUser(),
                new ItemAccessValidator()->setUser(Yii::$app->getUser()),
            );
            self::fail('Импорт с невалидным предметом должен завершиться исключением.');
        } catch (Exception $exception) {
            self::assertStringContainsString('name', $exception->getMessage());
        }

        self::assertSame(
            0,
            (int) Item::find()->where([
                'repoId' => $repo->id,
                'parentItemId' => $parent->itemId,
            ])->count()
        );

        $repo->refresh();
        self::assertSame($lastItemIdBeforeImport, (int) $repo->lastItemId);
    }

    /**
     * Создает контейнер с правами на создание предметов для импорта.
     *
     * @return array{0:\common\models\Repo, 1:Item}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $parent = $this->createItem($repo, $user, [
            'name' => 'Контейнер импорта',
            'isContainer' => true,
        ]);

        return [$repo, $parent];
    }
}
