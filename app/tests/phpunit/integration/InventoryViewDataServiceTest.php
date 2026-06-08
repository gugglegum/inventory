<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\InventoryViewDataService;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты подготовки данных для страницы просмотра инвентаризации.
 *
 * Проверяют разделение предметов на подтвержденные/неподтвержденные и построение путей для view.
 */
final class InventoryViewDataServiceTest extends DbTestCase
{
    /**
     * Сервис разделяет подтвержденные и неподтвержденные предметы и строит путь для предмета из другого контейнера.
     */
    public function testPrepareSplitsItemsAndBuildsPathForMovedConfirmedItem(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $container = $this->createItem($repo, $user, [
            'name' => 'Проверяемый контейнер',
            'isContainer' => true,
        ]);
        $otherContainer = $this->createItem($repo, $user, [
            'name' => 'Другой контейнер',
            'isContainer' => true,
        ]);
        $confirmedInside = $this->createItem($repo, $user, [
            'name' => 'Подтвержденный внутри',
            'parentItemId' => $container->itemId,
        ]);
        $confirmedOutside = $this->createItem($repo, $user, [
            'name' => 'Подтвержденный снаружи',
            'parentItemId' => $otherContainer->itemId,
        ]);
        $notConfirmed = $this->createItem($repo, $user, [
            'name' => 'Неподтвержденный внутри',
            'parentItemId' => $container->itemId,
        ]);
        $inventory = $this->createInventory($container, $user);
        $this->createInventoryItem($inventory, $confirmedInside, $user);
        $this->createInventoryItem($inventory, $confirmedOutside, $user);

        $viewData = (new InventoryViewDataService())->prepare($repo, $container, $inventory);

        self::assertEqualsCanonicalizing(
            [$confirmedInside->id, $confirmedOutside->id],
            array_map(static fn($item): int => (int) $item->id, $viewData->confirmedItems)
        );
        self::assertSame([$notConfirmed->id], array_map(static fn($item): int => (int) $item->id, $viewData->notConfirmedItems));
        self::assertSame([], $viewData->paths[$confirmedInside->id]);
        self::assertSame([], $viewData->paths[$notConfirmed->id]);
        self::assertSame(
            [(int) $confirmedOutside->itemId, (int) $otherContainer->itemId],
            array_map(static fn(array $pathItem): int => (int) $pathItem['itemId'], $viewData->paths[$confirmedOutside->id])
        );
    }

    /**
     * Если подтверждений еще нет, все дочерние предметы контейнера попадают в неподтвержденный список.
     */
    public function testPrepareReturnsContainerChildrenWhenNothingConfirmed(): void
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $container = $this->createItem($repo, $user, [
            'name' => 'Пустая проверка',
            'isContainer' => true,
        ]);
        $firstItem = $this->createItem($repo, $user, [
            'name' => 'Первый предмет',
            'parentItemId' => $container->itemId,
        ]);
        $secondItem = $this->createItem($repo, $user, [
            'name' => 'Второй предмет',
            'parentItemId' => $container->itemId,
        ]);
        $inventory = $this->createInventory($container, $user);

        $viewData = (new InventoryViewDataService())->prepare($repo, $container, $inventory);

        self::assertSame([], $viewData->confirmedItems);
        self::assertEqualsCanonicalizing(
            [$firstItem->id, $secondItem->id],
            array_map(static fn($item): int => (int) $item->id, $viewData->notConfirmedItems)
        );
    }
}
