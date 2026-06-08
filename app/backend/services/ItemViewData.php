<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

/**
 * Подготовленные данные для страницы просмотра предмета.
 *
 * @property-read Item[] $children Дочерние предметы текущего контейнера.
 * @property-read ?Item $prevItem Предыдущий предмет репозитория по itemId.
 * @property-read ?Item $nextItem Следующий предмет репозитория по itemId.
 */
final readonly class ItemViewData
{
    /**
     * @param Item[] $children Дочерние предметы текущего контейнера.
     * @param ?Item $prevItem Предыдущий предмет репозитория по itemId.
     * @param ?Item $nextItem Следующий предмет репозитория по itemId.
     */
    public function __construct(
        public array $children,
        public ?Item $prevItem,
        public ?Item $nextItem,
    ) {
    }
}
