<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;

final class ItemSearchResult
{
    /**
     * @param Item[]|null $items null means search was not executed
     * @param array<int, array<int, array{itemId:int, repoId:int, label:string, url:array}>> $paths
     */
    public function __construct(
        public readonly ?array $items,
        public readonly array $paths,
        public readonly ?Item $container,
        public readonly bool $isMoreThan,
    ) {
    }
}
