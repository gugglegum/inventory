<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;
use common\models\Post;

/**
 * Подготовленные данные для страницы просмотра предмета.
 *
 * @property-read Item[] $children Дочерние предметы текущего контейнера.
 * @property-read ?Item $prevItem Предыдущий предмет репозитория по itemId.
 * @property-read ?Item $nextItem Следующий предмет репозитория по itemId.
 * @property-read Post[] $recentPosts Последние заметки предмета.
 * @property-read int $postCount Общее количество заметок предмета.
 */
final readonly class ItemViewData
{
    /**
     * @param Item[] $children Дочерние предметы текущего контейнера.
     * @param ?Item $prevItem Предыдущий предмет репозитория по itemId.
     * @param ?Item $nextItem Следующий предмет репозитория по itemId.
     * @param Post[] $recentPosts Последние заметки предмета.
     * @param int $postCount Общее количество заметок предмета.
     */
    public function __construct(
        public array $children,
        public ?Item $prevItem,
        public ?Item $nextItem,
        public array $recentPosts,
        public int $postCount,
    ) {
    }
}
