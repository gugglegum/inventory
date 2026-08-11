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
     * Тестовые console-команды, включая photo-uploads/prune, не используют рабочие хранилища.
     */
    public function testConsoleFileStorageUsesPhpunitRuntime(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__) . '/config/console.php';
        $runtime = Yii::getAlias('@phpunitRuntime');

        self::assertSame('@phpunitRuntime/console', $config['runtimePath']);
        self::assertSame($runtime . '/photos', $config['params']['photos']['storagePath']);
        self::assertSame($runtime . '/photos/temp', $config['params']['photos']['storageTemp']);
        self::assertSame($runtime . '/thumbnails', $config['params']['photos']['thumbnailPath']);
        self::assertSame($runtime . '/thumbnails/temp', $config['params']['photos']['thumbnailTemp']);
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
            'photo_upload_session',
            'photo_upload_file',
            'photo_deletion_queue',
            'item_photo',
            'post',
            'inventory',
            'inventory_item',
            'migration',
        ] as $tableName) {
            self::assertNotNull($schema->getTableSchema($tableName), "Table {$tableName} should exist.");
        }
    }

    public function testPostPhotoOrderIsUniqueInsidePost(): void
    {
        $schema = Yii::$app->db->schema;
        self::assertInstanceOf(\yii\db\mysql\Schema::class, $schema);

        $table = $schema->getTableSchema('post_photo');
        self::assertNotNull($table);

        $indexes = $schema->getTableIndexes('post_photo');
        $orderIndex = null;
        foreach ($indexes as $index) {
            if ($index->name === 'ux_post_photo_postId_sortIndex') {
                $orderIndex = $index;
                break;
            }
        }

        self::assertNotNull($orderIndex);
        self::assertTrue($orderIndex->isUnique);
        self::assertSame(['postId', 'sortIndex'], $orderIndex->columnNames);
    }
}
