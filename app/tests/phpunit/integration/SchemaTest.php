<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use tests\phpunit\TestCase;
use Yii;

/**
 * Smoke-тест миграций и тестовой базы данных.
 *
 * Проверяет, что основные таблицы приложения существуют после применения миграций на stockhub_test.
 */
final class SchemaTest extends TestCase
{
    /**
     * Файловые операции тестов не должны попадать в рабочие photos/assets.
     */
    public function testFileStorageUsesPhpunitRuntime(): void
    {
        $runtime = Yii::getAlias('@phpunitRuntime');

        self::assertSame($runtime . '/photos', Yii::$app->params['photos']['storagePath']);
        self::assertSame($runtime . '/photos/temp', Yii::$app->params['photos']['storageTemp']);
        self::assertSame($runtime . '/thumbnails', Yii::$app->params['photos']['thumbnailPath']);
        self::assertSame($runtime . '/thumbnails/temp', Yii::$app->params['photos']['thumbnailTemp']);
        self::assertSame($runtime . '/assets', Yii::$app->assetManager->basePath);
    }

    /**
     * Основные таблицы проекта присутствуют в схеме тестовой БД.
     */
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
