<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

/**
 * Результат поиска предметов для страниц и AJAX-действий контроллера.
 *
 * Хранит найденные предметы, подготовленные пути до них, контекст контейнера и признак усечения выдачи.
 *
 * @property-read Item[]|null $items Найденные предметы или null, если поиск не выполнялся.
 * @property-read array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>> $paths Пути к найденным предметам.
 * @property-read ?Item $container Контейнер, которым был ограничен поиск.
 * @property-read bool $isMoreThan Признак того, что результатов больше установленного лимита.
 */
final readonly class ItemSearchResult
{
    /**
     * @param Item[]|null $items Найденные предметы; null означает, что поиск не выполнялся из-за отсутствия позитивного условия.
     * @param array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>> $paths Пути к найденным предметам, индексированные по глобальному ID предмета.
     * @param ?Item $container Контейнер, внутри которого нужно фильтровать результат, если он задан.
     * @param bool $isMoreThan True, если выдача была ограничена максимальным числом результатов.
     */
    public function __construct(
        public ?array $items,
        public array $paths,
        public ?Item $container,
        public bool $isMoreThan,
    ) {
    }
}
