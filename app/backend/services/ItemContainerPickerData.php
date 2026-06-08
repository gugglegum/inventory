<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

/**
 * Подготовленные данные для модального выбора родительского контейнера.
 *
 * @property-read string|int|null $parentContainerItemId Выбранный repo-scoped ID родительского контейнера.
 * @property-read ?Item $parentContainer Найденный родительский контейнер, если он был выбран.
 * @property-read Item[] $containers Контейнеры для отображения в текущем уровне picker-дерева.
 */
final readonly class ItemContainerPickerData
{
    /**
     * @param string|int|null $parentContainerItemId Выбранный repo-scoped ID родительского контейнера.
     * @param ?Item $parentContainer Найденный родительский контейнер, если он был выбран.
     * @param Item[] $containers Контейнеры для отображения в текущем уровне picker-дерева.
     */
    public function __construct(
        public string|int|null $parentContainerItemId,
        public ?Item $parentContainer,
        public array $containers,
    ) {
    }
}
