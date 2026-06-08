<?php

declare(strict_types=1);

namespace backend\services;

/**
 * Результат попытки удалить предмет.
 *
 * Успешный результат означает, что предмет удален выбранным способом. Неуспешный содержит сообщение,
 * которое контроллер может добавить в форму удаления.
 *
 * @property-read ?string $errorMessage Ошибка удаления предмета.
 */
final readonly class ItemDeletionResult
{
    /**
     * @param ?string $errorMessage Текст ошибки, если удаление не удалось.
     */
    private function __construct(
        public ?string $errorMessage,
    ) {
    }

    /**
     * Создает успешный результат удаления.
     */
    public static function success(): self
    {
        return new self(null);
    }

    /**
     * Создает результат удаления с ошибкой.
     */
    public static function failure(string $errorMessage): self
    {
        return new self($errorMessage);
    }

    /**
     * Возвращает true, если удалить предмет не удалось.
     */
    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }
}
