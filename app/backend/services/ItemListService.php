<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;
use common\models\ItemQuery;
use common\models\Repo;

/**
 * Готовит read-model данные для списков предметов и выбора контейнера.
 *
 * Сервис отделяет запросы root items и container picker от HTTP-слоя ItemsController.
 */
final class ItemListService
{
    /**
     * Возвращает корневые предметы репозитория в порядке отображения.
     *
     * @return Item[] Корневые предметы и контейнеры.
     */
    public function findRootItems(Repo $repo): array
    {
        return Item::find()
            ->andWhere([
                'repoId' => $repo->id,
                'parentItemId' => null,
            ])
            ->orderBy(['priority' => SORT_DESC, 'isContainer' => SORT_DESC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * Готовит данные для просмотра текущего уровня дерева контейнеров.
     */
    public function prepareContainerPicker(Repo $repo, string|int|null $parentContainerItemId): ItemContainerPickerData
    {
        $containerQuery = $this->createContainerQuery($repo);
        $hasSelectedParent = $this->hasSelectedParent($parentContainerItemId);

        $parentContainer = $hasSelectedParent
            ? (clone $containerQuery)->andWhere('itemId = :containerId', ['containerId' => $parentContainerItemId])->one()
            : null;

        $containers = $hasSelectedParent
            ? (clone $containerQuery)->andWhere('parentItemId = :containerId', ['containerId' => $parentContainerItemId])->all()
            : (clone $containerQuery)->andWhere('parentItemId IS NULL')->all();

        return new ItemContainerPickerData($parentContainerItemId, $parentContainer, $containers);
    }

    /**
     * Ищет контейнеры для модального выбора родителя.
     *
     * @return Item[] Найденные предметы-контейнеры.
     */
    public function searchContainers(Repo $repo, string $queryString): array
    {
        return (new ItemSearchService())->searchContainers($repo, $queryString);
    }

    /**
     * Создает базовый запрос контейнеров репозитория в порядке отображения picker-дерева.
     */
    private function createContainerQuery(Repo $repo): ItemQuery
    {
        return Item::find()
            ->andWhere([Item::tableName() . '.repoId' => $repo->id])
            ->andWhere('isContainer != 0')
            ->orderBy(['priority' => SORT_DESC, 'id' => SORT_ASC]);
    }

    /**
     * Повторяет прежнюю трактовку пустого itemId: null, пустая строка и "0" означают корень.
     */
    private function hasSelectedParent(string|int|null $parentContainerItemId): bool
    {
        return (bool) $parentContainerItemId;
    }
}
