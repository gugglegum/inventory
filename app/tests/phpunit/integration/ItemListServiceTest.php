<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\ItemListService;
use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты read-side сервиса списков предметов и выбора контейнера.
 *
 * Проверяют порядок корневых предметов, данные container picker и поиск контейнеров.
 */
final class ItemListServiceTest extends DbTestCase
{
    /**
     * findRootItems() возвращает только корневые предметы в порядке отображения.
     */
    public function testFindRootItemsReturnsOnlyRootItemsInDisplayOrder(): void
    {
        [$repo, $rootContainer, $rootItem, $lowPriorityRoot] = $this->prepareListFixture();

        $rootItems = (new ItemListService())->findRootItems($repo);

        self::assertSame(
            [(int) $rootContainer->id, (int) $rootItem->id, (int) $lowPriorityRoot->id],
            array_map(static fn(Item $item): int => (int) $item->id, $rootItems)
        );
    }

    /**
     * prepareContainerPicker() с itemId=0 открывает корень и показывает только корневые контейнеры.
     */
    public function testPrepareContainerPickerReturnsRootContainersWhenParentIsZero(): void
    {
        [$repo, $rootContainer] = $this->prepareListFixture();

        $pickerData = (new ItemListService())->prepareContainerPicker($repo, '0');

        self::assertSame('0', $pickerData->parentContainerItemId);
        self::assertNull($pickerData->parentContainer);
        self::assertSame([(int) $rootContainer->id], array_map(static fn(Item $item): int => (int) $item->id, $pickerData->containers));
    }

    /**
     * prepareContainerPicker() для выбранного контейнера возвращает его дочерние контейнеры.
     */
    public function testPrepareContainerPickerReturnsSelectedContainerAndChildContainers(): void
    {
        [$repo, $rootContainer, , , $firstChildContainer, $secondChildContainer, $matchingContainer] = $this->prepareListFixture();

        $pickerData = (new ItemListService())->prepareContainerPicker($repo, (string) $rootContainer->itemId);

        self::assertNotNull($pickerData->parentContainer);
        self::assertSame((int) $rootContainer->id, (int) $pickerData->parentContainer->id);
        self::assertSame(
            [(int) $secondChildContainer->id, (int) $firstChildContainer->id, (int) $matchingContainer->id],
            array_map(static fn(Item $item): int => (int) $item->id, $pickerData->containers)
        );
    }

    /**
     * searchContainers() ищет только предметы-контейнеры.
     */
    public function testSearchContainersReturnsOnlyMatchingContainers(): void
    {
        [$repo, , , , , , $matchingContainer] = $this->prepareListFixture();

        $containers = (new ItemListService())->searchContainers($repo, 'кабельная');

        self::assertSame([(int) $matchingContainer->id], array_map(static fn(Item $item): int => (int) $item->id, $containers));
    }

    /**
     * Создает репозиторий с корневыми предметами и вложенными контейнерами.
     *
     * @return array{0:Repo, 1:Item, 2:Item, 3:Item, 4:Item, 5:Item, 6:Item}
     */
    private function prepareListFixture(): array
    {
        $user = $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess($repo, $user, RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS);

        $rootItem = $this->createItem($repo, $user, [
            'name' => 'Корневой предмет',
            'priority' => 10,
        ]);
        $rootContainer = $this->createItem($repo, $user, [
            'name' => 'Корневой контейнер',
            'isContainer' => true,
            'priority' => 10,
        ]);
        $lowPriorityRoot = $this->createItem($repo, $user, [
            'name' => 'Низкоприоритетный предмет',
            'priority' => 1,
        ]);
        $firstChildContainer = $this->createItem($repo, $user, [
            'name' => 'Первый дочерний контейнер',
            'parentItemId' => $rootContainer->itemId,
            'isContainer' => true,
            'priority' => 2,
        ]);
        $secondChildContainer = $this->createItem($repo, $user, [
            'name' => 'Второй дочерний контейнер',
            'parentItemId' => $rootContainer->itemId,
            'isContainer' => true,
            'priority' => 5,
        ]);
        $matchingContainer = $this->createItem($repo, $user, [
            'name' => 'Кабельная коробка',
            'parentItemId' => $rootContainer->itemId,
            'isContainer' => true,
        ]);
        $this->createItem($repo, $user, [
            'name' => 'Кабельный не контейнер',
            'parentItemId' => $rootContainer->itemId,
            'isContainer' => false,
        ]);

        return [$repo, $rootContainer, $rootItem, $lowPriorityRoot, $firstChildContainer, $secondChildContainer, $matchingContainer];
    }
}
