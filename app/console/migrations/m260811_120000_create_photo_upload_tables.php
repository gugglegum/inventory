<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Добавляет явные сессии для асинхронной временной загрузки фотографий.
 */
final class m260811_120000_create_photo_upload_tables extends Migration
{
    public function safeUp(): void
    {
        $db = $this->getDb();

        // Исторический индекс ошибочно делал уникальной пару (photoId, sortIndex),
        // хотя порядок задается внутри конкретной заметки.
        $this->dropIndex('idx_post_photo_photoId_SortIndex', '{{%post_photo}}');
        $postPhotos = $db->createCommand(
            'SELECT [[id]], [[postId]] FROM {{%post_photo}} ORDER BY [[postId]], [[sortIndex]], [[id]]'
        )->queryAll();
        $currentPostId = null;
        $sortIndex = 0;
        foreach ($postPhotos as $postPhoto) {
            $postId = (int) $postPhoto['postId'];
            if ($currentPostId !== $postId) {
                $currentPostId = $postId;
                $sortIndex = 0;
            }
            $this->update(
                '{{%post_photo}}',
                ['sortIndex' => $sortIndex++],
                ['id' => (int) $postPhoto['id']]
            );
        }
        $this->createIndex(
            'ux_post_photo_postId_sortIndex',
            '{{%post_photo}}',
            ['postId', 'sortIndex'],
            true
        );

        $this->createTable('{{%photo_upload_session}}', [
            'id' => $this->primaryKey()->unsigned(),
            'token' => $this->char(64)->notNull(),
            'userId' => $this->integer()->unsigned()->notNull(),
            'repoId' => $this->integer()->unsigned()->notNull(),
            'context' => $this->string(32)->notNull(),
            'expiresAt' => $this->integer()->unsigned()->notNull(),
            'consumedAt' => $this->integer()->unsigned()->null(),
            'created' => $this->integer()->unsigned()->notNull(),
            'updated' => $this->integer()->unsigned()->null(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Сессии временной загрузки фотографий"');

        // Токен является opaque identifier и должен сравниваться побайтно.
        $this->execute(
            'ALTER TABLE {{%photo_upload_session}} '
            . 'MODIFY [[token]] CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
        );

        $this->createIndex('uq_photo_upload_session_token', '{{%photo_upload_session}}', 'token', true);
        $this->createIndex(
            'idx_photo_upload_session_owner_expiry',
            '{{%photo_upload_session}}',
            ['userId', 'expiresAt']
        );
        $this->createIndex('idx_photo_upload_session_expiry', '{{%photo_upload_session}}', 'expiresAt');

        $this->addForeignKey(
            'fk_photo_upload_session_user',
            '{{%photo_upload_session}}',
            'userId',
            '{{%user}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_photo_upload_session_repo',
            '{{%photo_upload_session}}',
            'repoId',
            '{{%repo}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->createTable('{{%photo_upload_file}}', [
            'id' => $this->primaryKey()->unsigned(),
            'sessionId' => $this->integer()->unsigned()->notNull(),
            'photoId' => $this->integer()->unsigned()->notNull(),
            'originalName' => $this->string(255)->notNull(),
            'created' => $this->integer()->unsigned()->notNull(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Временные фотографии асинхронных загрузок"');

        $this->createIndex(
            'idx_photo_upload_file_session',
            '{{%photo_upload_file}}',
            ['sessionId', 'id']
        );
        $this->createIndex('uq_photo_upload_file_photo', '{{%photo_upload_file}}', 'photoId', true);

        $this->addForeignKey(
            'fk_photo_upload_file_session',
            '{{%photo_upload_file}}',
            'sessionId',
            '{{%photo_upload_session}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_photo_upload_file_photo',
            '{{%photo_upload_file}}',
            'photoId',
            '{{%photo}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%photo_deletion_queue}}', [
            'id' => $this->primaryKey()->unsigned(),
            'photoId' => $this->integer()->unsigned()->notNull(),
            'created' => $this->integer()->unsigned()->notNull(),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Очередь удаления отсоединенных фотографий"');

        $this->createIndex('uq_photo_deletion_queue_photo', '{{%photo_deletion_queue}}', 'photoId', true);
        $this->addForeignKey(
            'fk_photo_deletion_queue_photo',
            '{{%photo_deletion_queue}}',
            'photoId',
            '{{%photo}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $db = $this->getDb();

        // Позволяет откатить промежуточную локальную версию этой еще не
        // опубликованной миграции, в которой таблицы очереди не было.
        if ($db->schema->getTableSchema('photo_deletion_queue', true) !== null) {
            $this->dropTable('{{%photo_deletion_queue}}');
        }
        $this->dropTable('{{%photo_upload_file}}');
        $this->dropTable('{{%photo_upload_session}}');

        $this->dropIndex('ux_post_photo_postId_sortIndex', '{{%post_photo}}');
        $this->createIndex(
            'idx_post_photo_photoId_SortIndex',
            '{{%post_photo}}',
            ['photoId', 'sortIndex'],
            true
        );
    }
}
