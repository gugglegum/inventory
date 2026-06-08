<?php

declare(strict_types=1);

namespace backend\services;

use common\models\InventoryItem;

/**
 * Результат попытки подтвердить предмет в инвентаризации.
 *
 * Успешный результат содержит созданную запись inventory_item, неуспешный - текст ошибки,
 * который можно показать рядом с полем itemId в форме подтверждения.
 *
 * @property-read ?InventoryItem $inventoryItem Созданная запись подтверждения.
 * @property-read ?string $errorMessage Ошибка сохранения подтверждения.
 */
final readonly class InventoryItemConfirmationResult
{
    /**
     * @param ?InventoryItem $inventoryItem Созданная запись inventory_item.
     * @param ?string $errorMessage Текст ошибки, если подтверждение не удалось сохранить.
     */
    private function __construct(
        public ?InventoryItem $inventoryItem,
        public ?string $errorMessage,
    ) {
    }

    /**
     * Создает успешный результат подтверждения.
     */
    public static function success(InventoryItem $inventoryItem): self
    {
        return new self($inventoryItem, null);
    }

    /**
     * Создает неуспешный результат подтверждения с сообщением для формы.
     */
    public static function failure(string $errorMessage): self
    {
        return new self(null, $errorMessage);
    }

    /**
     * Возвращает true, если подтверждение не удалось сохранить.
     */
    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }
}
