<?php

declare(strict_types=1);

namespace backend\services;

/**
 * Подготовленные данные для JSON-preview одного предмета.
 *
 * @property-read array<int, array<int, array{itemId:int, repoId:int, label:string, url:array{0:string, repoId:int, itemId:int}}>> $paths Пути к предмету, индексированные по глобальному ID.
 */
final readonly class ItemPreviewData
{
    /**
     * @param array<int, array<int, array{itemId:int, repoId:int, label:string, url:array{0:string, repoId:int, itemId:int}}>> $paths Пути к предмету для partial `_items`.
     */
    public function __construct(
        public array $paths,
    ) {
    }
}
