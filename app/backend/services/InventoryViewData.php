<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

/**
 * Подготовленные данные для страницы просмотра инвентаризации.
 *
 * DTO хранит списки подтвержденных и неподтвержденных предметов, а также пути для отображения
 * предметов, которые находятся вне текущего контейнера.
 *
 * @property-read Item[] $confirmedItems Предметы, подтвержденные в рамках инвентаризации.
 * @property-read Item[] $notConfirmedItems Дочерние предметы контейнера, которые еще не подтверждены.
 * @property-read array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>> $paths Пути к предметам, индексированные по глобальному ID предмета.
 */
final readonly class InventoryViewData
{
    /**
     * @param Item[] $confirmedItems Предметы, подтвержденные в рамках инвентаризации.
     * @param Item[] $notConfirmedItems Дочерние предметы контейнера, которые еще не подтверждены.
     * @param array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>> $paths Пути к предметам для view-рендера.
     */
    public function __construct(
        public array $confirmedItems,
        public array $notConfirmedItems,
        public array $paths,
    ) {
    }
}
