<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;
use common\models\ItemQuery;
use common\models\ItemTag;
use common\models\Post;
use common\models\Repo;
use yii\db\Expression;
use yii\db\Query;

/**
 * Выполняет поиск предметов внутри репозитория и готовит данные для отображения результатов.
 *
 * Сервис понимает позитивные слова, негативные слова с префиксом `-`, прямой поиск по itemId
 * и ограничение результатов контекстом выбранного контейнера.
 */
final class ItemSearchService
{
    /**
     * Максимальное число найденных предметов, возвращаемых в один результат поиска.
     */
    private const int MAX_RESULTS = 2000;

    /**
     * Ищет предметы в репозитории по заполненным критериям.
     *
     * @param Repo $repo Репозиторий, внутри которого выполняется поиск.
     * @param ItemSearchCriteria $criteria Нормализованные критерии поиска.
     */
    public function search(Repo $repo, ItemSearchCriteria $criteria): ItemSearchResult
    {
        $queryWords = $criteria->query !== null ? $this->splitQuery($criteria->query) : [];
        $descriptionWords = $criteria->description !== null ? $this->splitQuery($criteria->description) : [];
        $noteWords = $criteria->notes !== null ? $this->splitQuery($criteria->notes) : [];

        $query = Item::find()->andWhere([Item::tableName() . '.repoId' => $repo->id]);
        if ($criteria->container !== null) {
            $this->restrictToSubtree($query, $criteria->container);
        }

        $hasPositiveCondition = false;

        if (count($queryWords) > 0) {
            $hasPositiveCondition = $this->applyDefaultWordConditions($query, $queryWords);
        }

        if (count($descriptionWords) > 0) {
            $hasPositiveCondition = $this->applyDescriptionConditions($query, $descriptionWords)
                || $hasPositiveCondition;
        }

        if (count($noteWords) > 0) {
            $hasPositiveCondition = $this->applyNoteConditions($query, $noteWords)
                || $hasPositiveCondition;
        }

        if ($criteria->itemId !== null && $criteria->itemId !== '') {
            if (is_int($criteria->itemId) || ctype_digit($criteria->itemId)) {
                $query->andWhere(Item::tableName() . '.itemId = :itemId', ['itemId' => $criteria->itemId]);
            } else {
                $query->andWhere('0=1');
            }
            $hasPositiveCondition = true;
        }

        if ($queryWords !== [] || $descriptionWords !== [] || $noteWords !== []) {
            $query->groupBy(Item::tableName() . '.id')
                ->orderBy(Item::tableName() . '.isContainer DESC, ' . Item::tableName() . '.id ASC');
        }

        if (!$hasPositiveCondition) {
            return new ItemSearchResult(null, [], $criteria->container, false);
        }

        $items = $query->limit(self::MAX_RESULTS + 1)->all();
        $isMoreThan = count($items) > self::MAX_RESULTS;
        if ($isMoreThan) {
            array_pop($items);
        }

        return new ItemSearchResult(
            $items,
            $this->getItemPathsForView($items, $repo),
            $criteria->container,
            $isMoreThan
        );
    }

    /**
     * Ищет контейнеры по строке запроса для выбора родительского предмета.
     *
     * @return Item[] Найденные предметы-контейнеры.
     */
    public function searchContainers(Repo $repo, string $queryString): array
    {
        $queryWords = $this->splitQuery($queryString);
        if (count($queryWords) === 0) {
            return [];
        }

        $query = Item::find()
            ->andWhere([Item::tableName() . '.repoId' => $repo->id])
            ->andWhere('isContainer != 0');

        $hasPositiveCondition = $this->applyDefaultWordConditions($query, $queryWords);
        $query->groupBy(Item::tableName() . '.id');

        return $hasPositiveCondition ? $query->all() : [];
    }

    /**
     * Разбивает пользовательскую строку поиска на непустые слова.
     *
     * @return string[] Слова запроса.
     */
    private function splitQuery(string $queryString): array
    {
        $queryWords = preg_split('/[\s,]+/', $queryString, -1, PREG_SPLIT_NO_EMPTY);
        if ($queryWords === false) {
            return [];
        }

        return array_values(array_filter($queryWords, static fn($value): bool => $value !== ''));
    }

    /**
     * Добавляет в ActiveQuery условия по позитивным и негативным словам поиска.
     *
     * @param ItemQuery $query Запрос предметов, который будет изменен на месте.
     * @param string[] $queryWords Слова запроса; слова с префиксом `-` исключают совпадения.
     *
     * @return bool True, если в запросе было хотя бы одно позитивное условие.
     */
    private function applyDefaultWordConditions(ItemQuery $query, array $queryWords): bool
    {
        $hasPositiveCondition = false;
        $itemTable = Item::tableName();
        $i = 0;

        foreach ($queryWords as $queryWord) {
            if ($queryWord[0] !== '-') {
                $query->leftJoin(["t{$i}" => ItemTag::tableName()], "t{$i}.itemId = {$itemTable}.id");
                $isItemIdWord = ctype_digit($queryWord);
                $itemIdCondition = $isItemIdWord
                    ? " OR {$itemTable}.itemId = :tag{$i}"
                    : '';
                $params = ["tagMask{$i}" => '%' . $queryWord . '%'];
                if ($isItemIdWord) {
                    $params["tag{$i}"] = $queryWord;
                }
                $query->andWhere(
                    "t{$i}.tag LIKE :tagMask{$i}"
                    . " OR {$itemTable}.name LIKE :tagMask{$i}"
                    . $itemIdCondition,
                    $params
                );
                $hasPositiveCondition = true;
            } else {
                $query->leftJoin(
                    ["t{$i}" => ItemTag::tableName()],
                    "t{$i}.itemId = {$itemTable}.id AND t{$i}.tag LIKE :tagMask{$i}"
                );
                $queryWord = mb_substr($queryWord, 1);
                $isItemIdWord = ctype_digit($queryWord);
                $itemIdCondition = $isItemIdWord
                    ? " AND {$itemTable}.itemId != :tag{$i}"
                    : '';
                $params = ["tagMask{$i}" => '%' . $queryWord . '%'];
                if ($isItemIdWord) {
                    $params["tag{$i}"] = $queryWord;
                }
                $query->andWhere(
                    "t{$i}.tag IS NULL"
                    . " AND {$itemTable}.name NOT LIKE :tagMask{$i}"
                    . $itemIdCondition,
                    $params
                );
            }
            $i++;
        }

        return $hasPositiveCondition;
    }

    /**
     * Добавляет условия, применяемые только к описанию предмета.
     *
     * @param ItemQuery $query Запрос предметов, который будет изменен на месте.
     * @param string[] $queryWords Позитивные и негативные слова запроса по описанию.
     *
     * @return bool True, если задано хотя бы одно позитивное слово.
     */
    private function applyDescriptionConditions(ItemQuery $query, array $queryWords): bool
    {
        $hasPositiveCondition = false;
        $descriptionColumn = 'COALESCE(' . Item::tableName() . ".description, '')";

        foreach ($queryWords as $i => $queryWord) {
            if ($queryWord[0] !== '-') {
                $query->andWhere(
                    "{$descriptionColumn} LIKE :descriptionMask{$i}",
                    ["descriptionMask{$i}" => '%' . $queryWord . '%']
                );
                $hasPositiveCondition = true;
                continue;
            }

            $queryWord = mb_substr($queryWord, 1);
            $query->andWhere(
                "{$descriptionColumn} NOT LIKE :descriptionMask{$i}",
                ["descriptionMask{$i}" => '%' . $queryWord . '%']
            );
        }

        return $hasPositiveCondition;
    }

    /**
     * Добавляет поиск по заголовку и тексту заметок.
     *
     * Все позитивные слова должны встретиться в одной заметке. Каждое негативное слово исключает
     * предмет, если оно встречается хотя бы в одной из его заметок.
     *
     * @param ItemQuery $query Запрос предметов, который будет изменен на месте.
     * @param string[] $queryWords Позитивные и негативные слова запроса по заметкам.
     *
     * @return bool True, если задано хотя бы одно позитивное слово.
     */
    private function applyNoteConditions(ItemQuery $query, array $queryWords): bool
    {
        $positiveWords = [];
        $negativeWords = [];
        foreach ($queryWords as $queryWord) {
            if ($queryWord[0] === '-') {
                $negativeWords[] = mb_substr($queryWord, 1);
            } else {
                $positiveWords[] = $queryWord;
            }
        }

        if ($positiveWords !== []) {
            $positiveNoteQuery = $this->createNoteQuery();
            foreach ($positiveWords as $i => $queryWord) {
                $positiveNoteQuery->andWhere(
                    "searchPost.title LIKE :positiveNoteMask{$i}"
                    . " OR searchPost.text LIKE :positiveNoteMask{$i}",
                    ["positiveNoteMask{$i}" => '%' . $queryWord . '%']
                );
            }
            $query->andWhere(['exists', $positiveNoteQuery]);
        }

        foreach ($negativeWords as $i => $queryWord) {
            $negativeNoteQuery = $this->createNoteQuery()->andWhere(
                "searchPost.title LIKE :negativeNoteMask{$i}"
                . " OR searchPost.text LIKE :negativeNoteMask{$i}",
                ["negativeNoteMask{$i}" => '%' . $queryWord . '%']
            );
            $query->andWhere(['not exists', $negativeNoteQuery]);
        }

        return $positiveWords !== [];
    }

    /**
     * Создает коррелированный подзапрос заметок текущего предмета.
     */
    private function createNoteQuery(): Query
    {
        return (new Query())
            ->select(new Expression('1'))
            ->from(['searchPost' => Post::tableName()])
            ->where('searchPost.itemId = ' . Item::tableName() . '.id');
    }

    /**
     * Ограничивает поисковый запрос самим контейнером и всеми его потомками.
     *
     * В CTE намеренно используется UNION DISTINCT только по идентификаторам узлов. Помимо удаления
     * дублей это не дает поврежденному дереву с циклом выполнять рекурсию до системного лимита MariaDB.
     */
    private function restrictToSubtree(ItemQuery $query, Item $container): void
    {
        $cteName = 'item_subtree';
        $rootQuery = (new Query())
            ->select([
                'id' => 'root.id',
                'repoId' => 'root.repoId',
                'itemId' => 'root.itemId',
            ])
            ->from(['root' => Item::tableName()])
            ->where([
                'root.id' => $container->id,
                'root.repoId' => $container->repoId,
                'root.deleted' => null,
            ]);

        $descendantsQuery = (new Query())
            ->select([
                'id' => 'child.id',
                'repoId' => 'child.repoId',
                'itemId' => 'child.itemId',
            ])
            ->from(['child' => Item::tableName()])
            ->innerJoin(
                ['parent' => $cteName],
                'child.repoId = parent.repoId AND child.parentItemId = parent.itemId'
            )
            ->where(['child.deleted' => null]);

        $rootQuery->union($descendantsQuery);
        $query->withQuery($rootQuery, $cteName, true)
            ->innerJoin($cteName, $cteName . '.id = ' . Item::tableName() . '.id');
    }

    /**
     * Строит пути от найденных предметов вверх по родительским контейнерам одним рекурсивным запросом.
     *
     * @param Item[] $items Найденные предметы.
     * @return array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>>
     */
    private function getItemPathsForView(array $items, Repo $repo): array
    {
        if ($items === []) {
            return [];
        }

        $cteName = 'item_ancestors';
        $columns = [
            'id' => 'node.id',
            'repoId' => 'node.repoId',
            'itemId' => 'node.itemId',
            'parentItemId' => 'node.parentItemId',
            'name' => 'node.name',
        ];
        $globalItemIds = array_map(static fn(Item $item): int => (int) $item->id, $items);

        $itemsQuery = (new Query())
            ->select($columns)
            ->from(['node' => Item::tableName()])
            ->where([
                'node.repoId' => $repo->id,
                'node.id' => $globalItemIds,
                'node.deleted' => null,
            ]);

        $parentsQuery = (new Query())
            ->select($columns)
            ->from(['node' => Item::tableName()])
            ->innerJoin(
                ['child' => $cteName],
                'node.repoId = child.repoId AND node.itemId = child.parentItemId'
            )
            ->where(['node.deleted' => null]);

        $itemsQuery->union($parentsQuery);
        $rows = (new Query())
            ->withQuery($itemsQuery, $cteName, true)
            ->from($cteName)
            ->all(Item::getDb());

        /** @var array<int, array{id:int, repoId:int, itemId:int, parentItemId:?int, name:string}> $nodesByItemId */
        $nodesByItemId = [];
        foreach ($rows as $row) {
            $nodesByItemId[(int) $row['itemId']] = [
                'id' => (int) $row['id'],
                'repoId' => (int) $row['repoId'],
                'itemId' => (int) $row['itemId'],
                'parentItemId' => $row['parentItemId'] !== null ? (int) $row['parentItemId'] : null,
                'name' => (string) $row['name'],
            ];
        }

        $paths = [];
        foreach ($items as $item) {
            $path = [];
            $currentItemId = (int) $item->itemId;
            $visitedItemIds = [];

            while (isset($nodesByItemId[$currentItemId]) && !isset($visitedItemIds[$currentItemId])) {
                $visitedItemIds[$currentItemId] = true;
                $node = $nodesByItemId[$currentItemId];
                $path[] = [
                    'itemId' => $node['itemId'],
                    'repoId' => $node['repoId'],
                    'label' => $node['name'],
                    'url' => ['items/view', 'repoId' => $repo->id, 'itemId' => $node['itemId']],
                ];

                if ($node['parentItemId'] === null) {
                    break;
                }
                $currentItemId = $node['parentItemId'];
            }

            $paths[$item->id] = $path;
        }

        return $paths;
    }
}
