<?php

declare(strict_types=1);

namespace backend\services;

/**
 * Результат разбора или выполнения текстового импорта предметов.
 *
 * Используется и для preview-режима, и после подтвержденного импорта: хранит распознанные строки,
 * сведения об ошибке парсинга и якорь первого созданного предмета для редиректа.
 *
 * @property-read string $text Исходный текст импорта.
 * @property-read list<array{name:string, description?:string, tags?:string, container?:string}> $items Распознанные предметы.
 * @property-read ?int $errorLine Номер строки с ошибкой парсинга.
 * @property-read ?string $errorStr Исходная строка с ошибкой.
 * @property-read ?string $errorMsg Описание ошибки парсинга.
 * @property-read ?string $firstItemAnchor HTML-якорь первого созданного предмета.
 */
final readonly class ItemImportResult
{
    /**
     * @param string $text Исходный текст импорта.
     * @param list<array{name:string, description?:string, tags?:string, container?:string}> $items Распознанные предметы.
     * @param ?int $errorLine Номер строки с ошибкой парсинга, если ошибка была найдена.
     * @param ?string $errorStr Исходная строка, на которой возникла ошибка.
     * @param ?string $errorMsg Человекочитаемое описание ошибки.
     * @param ?string $firstItemAnchor HTML-якорь первого созданного предмета после подтвержденного импорта.
     */
    public function __construct(
        public string $text,
        public array $items,
        public ?int $errorLine,
        public ?string $errorStr,
        public ?string $errorMsg,
        public ?string $firstItemAnchor = null,
    ) {
    }

    /**
     * Возвращает true, если текст импорта разобран с ошибкой.
     */
    public function hasError(): bool
    {
        return $this->errorLine !== null;
    }
}
