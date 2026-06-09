<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

/**
 * Готовит read-model данные для просмотра и preview предмета.
 *
 * Сервис отделяет выборку соседних/дочерних предметов и построение path-данных от HTTP-логики ItemsController.
 */
final class ItemViewDataService
{
    /**
     * Готовит данные для страницы `items/view`.
     */
    public function prepare(Item $item): ItemViewData
    {
        $children = $item->getItems()
            ->orderBy([
                'priority' => SORT_DESC,
                'isContainer' => SORT_DESC,
                'id' => SORT_ASC,
            ])
            ->all();

        return new ItemViewData(
            $children,
            $this->findPrevItem($item),
            $this->findNextItem($item),
        );
    }

    /**
     * Готовит данные для JSON-preview одного предмета.
     */
    public function preparePreview(Item $item): ItemPreviewData
    {
        return new ItemPreviewData([
            $item->id => $this->buildItemPath($item),
        ]);
    }

    /**
     * Находит предыдущий предмет репозитория по repo-scoped itemId.
     */
    private function findPrevItem(Item $item): ?Item
    {
        return Item::find()
            ->where(['repoId' => $item->repoId])
            ->andWhere('itemId < :id', ['id' => $item->itemId])
            ->orderBy('itemId DESC')
            ->limit(1)
            ->one();
    }

    /**
     * Находит следующий предмет репозитория по repo-scoped itemId.
     */
    private function findNextItem(Item $item): ?Item
    {
        return Item::find()
            ->where(['repoId' => $item->repoId])
            ->andWhere('itemId > :id', ['id' => $item->itemId])
            ->orderBy('itemId ASC')
            ->limit(1)
            ->one();
    }

    /**
     * Строит путь от предмета вверх по родительским контейнерам.
     *
     * @return array<int, array{itemId:int, repoId:int, label:string, url:array{0:string, repoId:int, itemId:int}}>
     */
    private function buildItemPath(Item $item): array
    {
        $path = [];
        $tmpItem = $item;
        while ($tmpItem) {
            $path[] = [
                'itemId' => (int) $tmpItem->itemId,
                'repoId' => $tmpItem->repoId,
                'label' => $tmpItem->name,
                'url' => ['items/view', 'repoId' => $item->repoId, 'itemId' => (int) $tmpItem->itemId],
            ];
            $tmpItem = $tmpItem->parentItem;
        }

        return $path;
    }
}
