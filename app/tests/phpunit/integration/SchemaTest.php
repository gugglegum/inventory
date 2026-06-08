<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use tests\phpunit\TestCase;
use Yii;

final class SchemaTest extends TestCase
{
    public function testCoreTablesExistInTestDatabase(): void
    {
        $schema = Yii::$app->db->schema;

        foreach ([
            'user',
            'repo',
            'repo_user',
            'item',
            'item_tag',
            'photo',
            'item_photo',
            'post',
            'inventory',
            'inventory_item',
            'migration',
        ] as $tableName) {
            self::assertNotNull($schema->getTableSchema($tableName), "Table {$tableName} should exist.");
        }
    }
}
