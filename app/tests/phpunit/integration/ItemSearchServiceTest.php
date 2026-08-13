<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\ItemSearchService;
use backend\services\ItemSearchCriteria;
use common\models\Item;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use tests\phpunit\DbTestCase;

/**
 * Integration-тесты поиска предметов и ограничения результатов поддеревом.
 */
final class ItemSearchServiceTest extends DbTestCase
{
    /**
     * Буквенно-цифровое слово не приводится MariaDB к числу и не совпадает с числовым ID предмета.
     */
    public function testSearchDoesNotTreatAlphanumericWordAsItemId(): void
    {
        [$firstRepo, $user] = $this->createRepoFixture();
        $this->createItem($firstRepo, $user);
        [$repo] = $this->createRepoFixture($user);
        $item = $this->createItem($repo, $user, [
            'name' => 'Предмет без совпадения с запросом',
            'description' => 'Контрольное описание',
        ]);
        $service = new ItemSearchService();
        self::assertNotSame((int) $item->id, (int) $item->itemId);

        $alphanumericResult = $service->search(
            $repo,
            new ItemSearchCriteria(query: (string) $item->itemId . 'mai')
        );

        self::assertNotNull($alphanumericResult->items);
        self::assertSame([], $alphanumericResult->items);

        $alphanumericDirectIdResult = $service->search(
            $repo,
            new ItemSearchCriteria(itemId: (string) $item->itemId . 'mai')
        );

        self::assertNotNull($alphanumericDirectIdResult->items);
        self::assertSame([], $alphanumericDirectIdResult->items);

        $numericResult = $service->search(
            $repo,
            new ItemSearchCriteria(query: (string) $item->itemId)
        );

        self::assertNotNull($numericResult->items);
        self::assertSame(
            [(int) $item->id],
            array_map(static fn(Item $foundItem): int => (int) $foundItem->id, $numericResult->items)
        );

        $globalIdResult = $service->search(
            $repo,
            new ItemSearchCriteria(query: (string) $item->id)
        );

        self::assertNotNull($globalIdResult->items);
        self::assertSame([], $globalIdResult->items);

        $negativeResult = $service->search(
            $repo,
            new ItemSearchCriteria(query: 'предмет -' . (string) $item->itemId . 'mai')
        );

        self::assertNotNull($negativeResult->items);
        self::assertSame(
            [(int) $item->id],
            array_map(static fn(Item $foundItem): int => (int) $foundItem->id, $negativeResult->items)
        );
    }

    /**
     * Обычный запрос не ищет по описанию, а отдельный критерий описания поддерживает включения и исключения.
     */
    public function testDescriptionIsSearchedOnlyByAdvancedCriterion(): void
    {
        [$repo, $user] = $this->createRepoFixture();
        $matchingItem = $this->createItem($repo, $user, [
            'name' => 'Первый предмет',
            'description' => 'description-marker исправный автомобильный адаптер',
        ]);
        $this->createItem($repo, $user, [
            'name' => 'Второй предмет',
            'description' => 'description-marker неисправный автомобильный адаптер',
        ]);
        $this->createItem($repo, $user, [
            'name' => 'Предмет без описания',
        ]);
        $service = new ItemSearchService();

        $defaultResult = $service->search(
            $repo,
            new ItemSearchCriteria(query: 'description-marker')
        );
        $descriptionResult = $service->search(
            $repo,
            new ItemSearchCriteria(description: 'description-marker -неисправный')
        );

        self::assertNotNull($defaultResult->items);
        self::assertSame([], $defaultResult->items);
        self::assertNotNull($descriptionResult->items);
        self::assertSame(
            [(int) $matchingItem->id],
            array_map(static fn(Item $item): int => (int) $item->id, $descriptionResult->items)
        );
    }

    /**
     * Расширенные критерии объединяются через AND, а все позитивные слова заметок ищутся в одной заметке.
     */
    public function testAdvancedCriteriaSearchNotesAndCombineAllFields(): void
    {
        [$repo, $user] = $this->createRepoFixture();
        $matchingItem = $this->createItem($repo, $user, [
            'name' => 'notes-marker видеорегистратор',
            'description' => 'Установлен в автомобиле',
        ]);
        $this->createPost($matchingItem, $user, [
            'title' => 'Плановое обслуживание',
            'text' => 'Литиевая батарея успешно заменена',
        ]);

        $splitNotesItem = $this->createItem($repo, $user, [
            'name' => 'notes-marker запасной регистратор',
            'description' => 'Хранится в автомобиле',
        ]);
        $this->createPost($splitNotesItem, $user, [
            'title' => 'Плановое обслуживание',
            'text' => 'Выполнена очистка корпуса',
        ]);
        $this->createPost($splitNotesItem, $user, [
            'title' => 'Замена комплектующих',
            'text' => 'Установлена литиевая батарея',
        ]);

        $excludedItem = $this->createItem($repo, $user, [
            'name' => 'notes-marker основной регистратор',
            'description' => 'Установлен в автомобиле',
        ]);
        $this->createPost($excludedItem, $user, [
            'title' => 'Плановое обслуживание',
            'text' => 'Литиевая батарея заменена',
        ]);
        $this->createPost($excludedItem, $user, [
            'title' => 'Неудачная проверка',
            'text' => 'Обнаружен дефект питания',
        ]);

        [$otherRepo] = $this->createRepoFixture($user);
        $otherRepoItem = $this->createItem($otherRepo, $user, [
            'name' => 'notes-marker чужой регистратор',
            'description' => 'Установлен в автомобиле',
        ]);
        $this->createPost($otherRepoItem, $user, [
            'title' => 'Плановое обслуживание',
            'text' => 'Литиевая батарея успешно заменена',
        ]);

        $result = (new ItemSearchService())->search(
            $repo,
            new ItemSearchCriteria(
                query: 'notes-marker',
                description: 'автомобиле',
                notes: 'обслуживание батарея -дефект'
            )
        );

        self::assertNotNull($result->items);
        self::assertSame(
            [(int) $matchingItem->id],
            array_map(static fn(Item $item): int => (int) $item->id, $result->items)
        );
    }

    /**
     * Поиск внутри контейнера включает сам контейнер и потомков любой глубины, но не соседние ветки.
     */
    public function testSearchInsideReturnsInclusiveSubtreeAndBuildsPaths(): void
    {
        [$repo, $user] = $this->createRepoFixture();
        $container = $this->createItem($repo, $user, [
            'name' => 'tree-marker корневой контейнер',
            'isContainer' => true,
        ]);
        $intermediateItem = $this->createItem($repo, $user, [
            'name' => 'Промежуточный предмет с дочерней записью',
            'parentItemId' => $container->itemId,
        ]);
        $deepMatch = $this->createItem($repo, $user, [
            'name' => 'tree-marker глубоко внутри',
            'parentItemId' => $intermediateItem->itemId,
        ]);
        $outsideMatch = $this->createItem($repo, $user, [
            'name' => 'tree-marker в соседней ветке',
        ]);

        [$otherRepo] = $this->createRepoFixture($user);
        $otherRepoMatch = $this->createItem($otherRepo, $user, [
            'name' => 'tree-marker в другом репозитории',
        ]);

        $result = (new ItemSearchService())->search(
            $repo,
            new ItemSearchCriteria(query: 'tree-marker', container: $container)
        );

        self::assertNotNull($result->items);
        self::assertSame(
            [(int) $container->id, (int) $deepMatch->id],
            array_map(static fn(Item $item): int => (int) $item->id, $result->items)
        );
        self::assertFalse($result->isMoreThan);
        self::assertSame($container, $result->container);
        self::assertArrayNotHasKey($outsideMatch->id, $result->paths);
        self::assertArrayNotHasKey($otherRepoMatch->id, $result->paths);
        self::assertSame(
            [(int) $deepMatch->itemId, (int) $intermediateItem->itemId, (int) $container->itemId],
            array_column($result->paths[$deepMatch->id], 'itemId')
        );
        self::assertSame(
            [(int) $container->itemId],
            array_column($result->paths[$container->id], 'itemId')
        );

        $directInsideResult = (new ItemSearchService())->search(
            $repo,
            new ItemSearchCriteria(container: $container, itemId: $deepMatch->itemId)
        );
        self::assertNotNull($directInsideResult->items);
        self::assertSame([(int) $deepMatch->id], array_map(
            static fn(Item $item): int => (int) $item->id,
            $directInsideResult->items
        ));

        $directOutsideResult = (new ItemSearchService())->search(
            $repo,
            new ItemSearchCriteria(container: $container, itemId: $outsideMatch->itemId)
        );
        self::assertSame([], $directOutsideResult->items);
    }

    /**
     * Основной поиск и поиск контейнеров не возвращают мягко удаленные записи.
     */
    public function testSearchExcludesSoftDeletedItemsAndContainers(): void
    {
        [$repo, $user] = $this->createRepoFixture();
        $activeMatch = $this->createItem($repo, $user, [
            'name' => 'deleted-marker активный предмет',
        ]);
        $deletedMatch = $this->createItem($repo, $user, [
            'name' => 'deleted-marker удаленный предмет',
        ]);
        $deletedContainer = $this->createItem($repo, $user, [
            'name' => 'deleted-marker удаленный контейнер',
            'isContainer' => true,
        ]);
        $deletedMatch->updateAttributes(['deleted' => time(), 'deletedBy' => $user->id]);
        $deletedContainer->updateAttributes(['deleted' => time(), 'deletedBy' => $user->id]);

        $service = new ItemSearchService();
        $result = $service->search($repo, new ItemSearchCriteria(query: 'deleted-marker'));

        self::assertNotNull($result->items);
        self::assertSame(
            [(int) $activeMatch->id],
            array_map(static fn(Item $item): int => (int) $item->id, $result->items)
        );
        self::assertSame([], $service->searchContainers($repo, 'deleted-marker'));
    }

    /**
     * Поврежденная циклическая цепочка не зацикливает ни обход поддерева, ни построение путей.
     */
    public function testSearchTerminatesOnCyclicTreeData(): void
    {
        [$repo, $user] = $this->createRepoFixture();
        $firstItem = $this->createItem($repo, $user, [
            'name' => 'cycle-marker первый предмет',
        ]);
        $secondItem = $this->createItem($repo, $user, [
            'name' => 'cycle-marker второй предмет',
            'parentItemId' => $firstItem->itemId,
        ]);
        $firstItem->updateAttributes(['parentItemId' => $secondItem->itemId]);

        $result = (new ItemSearchService())->search(
            $repo,
            new ItemSearchCriteria(query: 'cycle-marker', container: $firstItem)
        );

        self::assertNotNull($result->items);
        self::assertSame(
            [(int) $firstItem->id, (int) $secondItem->id],
            array_map(static fn(Item $item): int => (int) $item->id, $result->items)
        );
        self::assertSame(
            [(int) $firstItem->itemId, (int) $secondItem->itemId],
            array_column($result->paths[$firstItem->id], 'itemId')
        );
        self::assertSame(
            [(int) $secondItem->itemId, (int) $firstItem->itemId],
            array_column($result->paths[$secondItem->id], 'itemId')
        );
    }

    /**
     * Лимит и признак усечения применяются после ограничения результатов выбранным поддеревом.
     */
    public function testSearchInsideLimitsResultAndSetsMoreFlagFromSubtreeMatches(): void
    {
        [$repo, $user] = $this->createRepoFixture();
        $container = $this->createItem($repo, $user, [
            'name' => 'Контейнер для проверки лимита',
            'isContainer' => true,
        ]);
        $rows = [];
        $createdAt = time();
        for ($itemId = 2; $itemId <= 2001; $itemId++) {
            $rows[] = [
                $itemId,
                $container->itemId,
                $repo->id,
                'limit-marker предмет ' . $itemId,
                null,
                0,
                0,
                $user->id,
                $createdAt,
            ];
        }
        $rows[] = [
            2002,
            null,
            $repo->id,
            'limit-marker предмет вне поддерева',
            null,
            0,
            0,
            $user->id,
            $createdAt,
        ];
        Item::getDb()->createCommand()->batchInsert(
            Item::tableName(),
            ['itemId', 'parentItemId', 'repoId', 'name', 'description', 'isContainer', 'priority', 'createdBy', 'created'],
            $rows
        )->execute();

        $service = new ItemSearchService();
        $result = $service->search(
            $repo,
            new ItemSearchCriteria(query: 'limit-marker', container: $container)
        );

        self::assertNotNull($result->items);
        self::assertCount(2000, $result->items);
        self::assertCount(2000, $result->paths);
        self::assertFalse($result->isMoreThan);

        Item::getDb()->createCommand()->insert(Item::tableName(), [
            'itemId' => 2003,
            'parentItemId' => $container->itemId,
            'repoId' => $repo->id,
            'name' => 'limit-marker 2001-й предмет внутри',
            'isContainer' => 0,
            'priority' => 0,
            'createdBy' => $user->id,
            'created' => $createdAt,
        ])->execute();

        $resultWithExtraItem = $service->search(
            $repo,
            new ItemSearchCriteria(query: 'limit-marker', container: $container)
        );
        self::assertNotNull($resultWithExtraItem->items);
        self::assertCount(2000, $resultWithExtraItem->items);
        self::assertTrue($resultWithExtraItem->isMoreThan);
    }

    /**
     * Создает репозиторий и необходимые для изменения предметов права.
     *
     * @return array{0:Repo, 1:User}
     */
    private function createRepoFixture(?User $user = null): array
    {
        $user ??= $this->createUser([
            'access' => User::ACCESS_CREATE_REPO,
        ]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS | RepoUser::ACCESS_DELETE_ITEMS
        );

        return [$repo, $user];
    }
}
