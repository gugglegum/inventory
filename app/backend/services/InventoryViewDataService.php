<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Inventory;
use common\models\Item;
use common\models\Repo;
use yii\helpers\ArrayHelper;

/**
 * Готовит read-model для страницы просмотра инвентаризации.
 *
 * Сервис отделяет выборку подтвержденных/неподтвержденных предметов и построение путей от HTTP-логики
 * InventoryController::actionView().
 */
final class InventoryViewDataService
{
    /**
     * Собирает списки предметов и пути, необходимые шаблону `inventory/view`.
     */
    public function prepare(Repo $repo, Item $container, Inventory $inventory): InventoryViewData
    {
        /** @var Item[] $confirmedItems */
        $confirmedItems = $repo->getItems()
            ->innerJoinWith('inventoryItems')
            ->where(['inventory_item.inventoryId' => $inventory->id])
            ->orderBy([
                'inventory_item.created' => SORT_DESC,
                Item::tableName() . '.isContainer' => SORT_DESC,
                Item::tableName() . '.id' => SORT_ASC,
            ])
            ->all();

        /** @var Item[] $notConfirmedItems */
        $notConfirmedItems = $container->getItems()
            ->andWhere(['not in', Item::tableName() . '.id', ArrayHelper::getColumn($confirmedItems, 'id')])
            ->orderBy([
                Item::tableName() . '.priority' => SORT_DESC,
                Item::tableName() . '.isContainer' => SORT_DESC,
                Item::tableName() . '.id' => SORT_ASC,
            ])
            ->all();

        return new InventoryViewData(
            $confirmedItems,
            $notConfirmedItems,
            $this->buildPaths($confirmedItems, $notConfirmedItems, $repo, $container),
        );
    }

    /**
     * Строит пути для обоих списков предметов.
     *
     * @param Item[] $confirmedItems
     * @param Item[] $notConfirmedItems
     *
     * @return array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>>
     */
    private function buildPaths(array $confirmedItems, array $notConfirmedItems, Repo $repo, Item $container): array
    {
        $paths = [];
        foreach (array_merge($confirmedItems, $notConfirmedItems) as $item) {
            $paths[$item->id] = $this->getItemPathForView($item, $repo, $container);
        }

        return $paths;
    }

    /**
     * Строит путь от предмета вверх по родительским контейнерам.
     *
     * Для предметов, которые уже лежат непосредственно в текущем контейнере, путь не нужен.
     *
     * @return array<int, array{itemId:int, repoId:int, label:string, url:array}>
     */
    private function getItemPathForView(Item $item, Repo $repo, Item $container): array
    {
        if ($item->parentItemId === $container->itemId) {
            return [];
        }

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
