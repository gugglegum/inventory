<?php

namespace tests\phpunit\unit;

use backend\services\ItemImportService;
use tests\phpunit\TestCase;

final class ItemImportServiceTest extends TestCase
{
    public function testParseSupportsAliasesAndCombinesRepeatedProperties(): void
    {
        $result = (new ItemImportService())->parse(implode("\n", [
            'Коробка',
            '!Первая строка описания',
            '!Вторая строка описания',
            '#контейнер',
            '* теги: хранение, пластик',
            '* контейнер: 1',
        ]));

        self::assertFalse($result->hasError());
        self::assertCount(1, $result->items);
        self::assertSame('Коробка', $result->items[0]['name']);
        self::assertSame("Первая строка описания\nВторая строка описания", $result->items[0]['description']);
        self::assertSame('контейнер, хранение, пластик', $result->items[0]['tags']);
        self::assertSame('1', $result->items[0]['container']);
    }

    public function testParseReportsUnknownPropertyLine(): void
    {
        $result = (new ItemImportService())->parse(implode("\n", [
            'Предмет',
            '* unknown: value',
        ]));

        self::assertTrue($result->hasError());
        self::assertSame(2, $result->errorLine);
        self::assertSame('* unknown: value', $result->errorStr);
        self::assertSame('Unknown property "unknown"', $result->errorMsg);
    }
}
