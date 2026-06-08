<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;
use common\models\ItemTag;
use common\models\Repo;

final class ItemSearchService
{
    private const int MAX_RESULTS = 2000;

    /**
     * @param string|int|null $itemId
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
     * @return Item[]
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
     * @return string[]
     */
    private function splitQuery(string $queryString): array
    {
        return array_filter(
            preg_split('/[\s,]+/', $queryString, -1, PREG_SPLIT_NO_EMPTY),
            static fn($value): bool => $value !== ''
        );
    }

    /**
     * @param string[] $queryWords
     */
    private function applyWordConditions(\common\models\ItemQuery $query, array $queryWords): bool
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
