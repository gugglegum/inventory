<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;
use common\models\ItemQuery;
use common\models\ItemTag;
use common\models\Repo;

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
     * Ищет предметы в репозитории по текстовой строке и/или внутреннему ID предмета.
     *
     * @param Repo $repo Репозиторий, внутри которого выполняется поиск.
     * @param ?string $queryString Поисковая строка с позитивными и негативными словами.
     * @param ?Item $container Контейнер, которым нужно ограничить результаты поиска.
     * @param string|int|null $itemId Внутренний ID предмета в репозитории.
     */
    public function search(Repo $repo, ?string $queryString, ?Item $container, string|int|null $itemId): ItemSearchResult
    {
        $containerId = $container?->itemId;
        $queryWords = $queryString !== null ? $this->splitQuery($queryString) : [];

        $items = null;
        $query = Item::find()->where(['repoId' => $repo->id]);
        $hasPositiveCondition = false;

        if (count($queryWords) > 0) {
            $hasPositiveCondition = $this->applyWordConditions($query, $queryWords);
            $query->groupBy(Item::tableName() . '.id')
                ->orderBy(Item::tableName() . '.isContainer DESC, ' . Item::tableName() . '.id ASC');
        }

        if ($itemId !== null && $itemId !== '') {
            $query->andWhere(Item::tableName() . '.itemId = :itemId', ['itemId' => $itemId]);
            $hasPositiveCondition = true;
        }

        if ($hasPositiveCondition) {
            $items = $query->all();
        }

        $paths = [];
        $isMoreThan = false;
        if (is_array($items)) {
            $tmpItems = [];
            $i = 0;
            foreach ($items as $item) {
                if ($i >= self::MAX_RESULTS) {
                    $isMoreThan = true;
                    break;
                }

                $doSkipItem = $containerId !== null;
                $path = $this->getItemPathForView($item, $repo);
                if ($containerId) {
                    foreach ($path as $pathItem) {
                        if ($pathItem['itemId'] == $containerId) {
                            $doSkipItem = false;
                            break;
                        }
                    }
                }

                if (!$doSkipItem) {
                    $tmpItems[] = $item;
                    $paths[$item->id] = $path;
                    $i++;
                }
            }
            $items = $tmpItems;
        }

        return new ItemSearchResult($items, $paths, $container, $isMoreThan);
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
            ->where(['repoId' => $repo->id])
            ->andWhere('isContainer != 0');

        $hasPositiveCondition = $this->applyWordConditions($query, $queryWords);
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
    private function applyWordConditions(ItemQuery $query, array $queryWords): bool
    {
        $hasPositiveCondition = false;
        $i = 0;

        foreach ($queryWords as $queryWord) {
            if ($queryWord[0] !== '-') {
                $query->leftJoin(["t{$i}" => ItemTag::tableName()], "t{$i}.itemId = id");
                $query->andWhere(
                    "t{$i}.tag LIKE :tagMask{$i} OR name LIKE :tagMask{$i} OR description LIKE :tagMask{$i} OR id = :tag{$i}",
                    ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']
                );
                $hasPositiveCondition = true;
            } else {
                $query->leftJoin(["t{$i}" => ItemTag::tableName()], "t{$i}.itemId = id AND t{$i}.tag LIKE :tagMask{$i}");
                $queryWord = mb_substr($queryWord, 1);
                $query->andWhere(
                    "t{$i}.tag IS NULL AND name NOT LIKE :tagMask{$i} AND description NOT LIKE :tagMask{$i} AND id != :tag{$i}",
                    ["tag{$i}" => $queryWord, "tagMask{$i}" => '%' . $queryWord . '%']
                );
            }
            $i++;
        }

        return $hasPositiveCondition;
    }

    /**
     * Строит путь от предмета вверх по родительским контейнерам для хлебных крошек результата поиска.
     *
     * @return array<int, array{itemId:int, repoId:int, label:string, url:array}>
     */
    private function getItemPathForView(Item $item, Repo $repo): array
    {
        $path = [];
        $tmpItem = $item;
        while ($tmpItem) {
            $path[] = [
                'itemId' => $tmpItem->itemId,
                'repoId' => $tmpItem->repoId,
                'label' => $tmpItem->name,
                'url' => ['items/view', 'repoId' => $repo->id, 'itemId' => $tmpItem->itemId],
            ];
            $tmpItem = $tmpItem->parentItem;
        }
        return $path;
    }
}
