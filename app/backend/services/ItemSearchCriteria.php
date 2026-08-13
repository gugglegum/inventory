<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

/**
 * Нормализованные критерии поиска предметов, независимые от способа ввода в HTTP-форме.
 */
final readonly class ItemSearchCriteria
{
    /**
     * @param ?string $query Обычный запрос по itemId, названию и тегам.
     * @param ?string $description Запрос только по описанию предмета.
     * @param ?string $notes Запрос по заголовку и тексту заметок.
     * @param ?Item $container Контейнер, поддеревом которого ограничен поиск.
     * @param string|int|null $itemId Точный itemId из отдельной формы перехода к предмету.
     */
    public function __construct(
        public ?string $query = null,
        public ?string $description = null,
        public ?string $notes = null,
        public ?Item $container = null,
        public string|int|null $itemId = null,
    ) {
    }
}
