<?php

declare(strict_types=1);

namespace backend\services;

final readonly class ItemImportResult
{
    public function __construct(
        public string $text,
        public array $items,
        public ?int $errorLine,
        public ?string $errorStr,
        public ?string $errorMsg,
        public ?string $firstItemAnchor = null,
    ) {
    }

    public function hasError(): bool
    {
        return $this->errorLine !== null;
    }
}
