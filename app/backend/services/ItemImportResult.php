<?php

declare(strict_types=1);

namespace backend\services;

final class ItemImportResult
{
    public function __construct(
        public readonly string $text,
        public readonly array $items,
        public readonly ?int $errorLine,
        public readonly ?string $errorStr,
        public readonly ?string $errorMsg,
        public readonly ?string $firstItemAnchor = null,
    ) {
    }

    public function hasError(): bool
    {
        return $this->errorLine !== null;
    }
}
