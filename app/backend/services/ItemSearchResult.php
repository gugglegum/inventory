<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

final readonly class ItemSearchResult
{
    /**
     * @param Item[]|null $items null means search was not executed
     * @param array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>> $paths
     */
    public function __construct(
        public ?array $items,
        public array $paths,
        public ?Item $container,
        public bool $isMoreThan,
    ) {
    }
}
