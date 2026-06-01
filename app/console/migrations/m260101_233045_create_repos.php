<?php

use yii\db\Migration;

/**
 * Добавляет мульти-репозиторность и шаринг репозиториев между пользователями
 */
class m260101_233045_create_repos extends Migration
{
    /**
     * @throws \yii\console\Exception
     * @throws \yii\db\Exception
     */
    public function safeUp(): void
    {
        $itemsCount = $this->getDb()->createCommand('SELECT COUNT(*) FROM `item`')->queryScalar();
        $userIds = $this->getDb()->createCommand('SELECT `id` FROM `user` ORDER BY `id`')->queryColumn();
        if ($itemsCount > 0 && count($userIds) == 0) {
            throw new \yii\console\Exception('Can\'t apply this migration due to current DB contains items without users');
        }

        $this->createTable('repo', [
            'id' => "int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID репозитория'",
            'name' => "varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Название репозитория'",
            'description' => "text COLLATE utf8mb4_unicode_ci COMMENT 'Описание репозитория'",
            'priority' => "INT DEFAULT 0 NOT NULL COMMENT 'Приоритет сортировки'",
            'lastItemId' => "INT DEFAULT 0 NOT NULL COMMENT 'ID последнего созданного предмета'",
            'createdBy' => "int(10) UNSIGNED NOT NULL COMMENT 'ID создавшего репозиторий пользователя'",
            'updatedBy' => "int(10) UNSIGNED NULL COMMENT 'ID последнего изменившего репозиторий пользователя'",
            'created' => "int(11) NOT NULL COMMENT 'Время создания'",
            'updated' => "int(11) NULL COMMENT 'Время последнего изменения'",
            'PRIMARY KEY (`id`)',
            'KEY `priority` (`priority`)',
            'KEY `createdBy` (`createdBy`)',
            'KEY `updatedBy` (`updatedBy`)',
        ], "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Репозитории'");
        $this->addForeignKey('repo_createdBy', 'repo', ['createdBy'], 'user', ['id']);
        $this->addForeignKey('repo_updatedBy', 'repo', ['updatedBy'], 'user', ['id']);

        $this->createTable('repo_user', [
            'repoId' => "int(10) unsigned NOT NULL COMMENT 'ID репозитория'",
            'userId' => "int(10) UNSIGNED NOT NULL COMMENT 'ID пользователя'",
            'access' => "int(10) UNSIGNED NOT NULL COMMENT 'Тип доступа'",
            'PRIMARY KEY (`repoId`, `userId`)',
            'KEY `userId` (`userId`)',
        ]);
        $this->addForeignKey('repo_user_repoId', 'repo_user', ['repoId'], 'repo', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey('repo_user_userId', 'repo_user', ['userId'], 'user', ['id']);

        $this->addColumn('item', 'itemId', "INT UNSIGNED NOT NULL COMMENT 'ID предмета (внутри репозитория)' AFTER `id`");
        $this->addColumn('item', 'repoId', "INT UNSIGNED NOT NULL COMMENT 'ID репозитория' AFTER `parentId`");
        $this->addColumn('item', 'createdBy', "INT UNSIGNED COMMENT 'ID создавшего запись пользователя' AFTER `priority`");
        $this->addColumn('item', 'updatedBy', "INT UNSIGNED COMMENT 'ID последнего изменившего запись пользователя' AFTER `createdBy`");

        $this->dropForeignKey('items_parentId', 'item');
        $this->renameColumn('item', 'parentId', 'parentItemId');

        if ($itemsCount > 0 && count($userIds) > 0) {
            $repoId = 1;
            $userId = $userIds[0];
            $this->insert('repo', [
                'id' => $repoId,
                'name' => 'Репозиторий по умолчанию',
                'description' => '',
                'lastItemId' => $this->getDb()->createCommand('SELECT `id` FROM `item` ORDER BY `id` DESC LIMIT 1')->queryScalar(),
                'createdBy' => $userId,
                'created' => time(),
            ]);
            $this->update('item', [
                'repoId' => $repoId,
            ]);
            foreach ($userIds as $userId) {
                $this->insert('repo_user', [
                    'repoId' => $repoId,
                    'userId' => $userId,
                    'access' => 1 + 2 + 4 + 16384 + 32768,
                ]);
            }
        }
        $this->update('item', ['itemId' => new \yii\db\Expression('id')]);

        $this->createIndex('itemIdRepoId', 'item', ['repoId', 'itemId'], true);
        $this->createIndex('parentItemIdRepoId', 'item', ['repoId', 'parentItemId']);
        $this->addForeignKey('item_parentItemId', 'item', ['repoId', 'parentItemId'], 'item', ['repoId', 'itemId'], 'RESTRICT', 'CASCADE');

        $this->createIndex('repoId', 'item', ['repoId']);
        $this->createIndex('createdBy', 'item', ['createdBy']);
        $this->createIndex('updatedBy', 'item', ['updatedBy']);

        $this->addForeignKey('item_repoId', 'item', ['repoId'], 'repo', ['id']);
        $this->addForeignKey('item_createdBy', 'item', ['createdBy'], 'user', ['id']);
        $this->addForeignKey('item_updatedBy', 'item', ['updatedBy'], 'user', ['id']);
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('item_updatedBy', 'item');
        $this->dropForeignKey('item_createdBy', 'item');
        $this->dropForeignKey('item_repoId', 'item');
        $this->dropIndex('updatedBy', 'item');
        $this->dropIndex('createdBy', 'item');
        $this->dropIndex('repoId', 'item');

        $this->dropForeignKey('item_parentItemId', 'item');
        $this->dropIndex('parentItemIdRepoId', 'item');
        $this->dropIndex('itemIdRepoId', 'item');
        $this->renameColumn('item', 'parentItemId', 'parentId');

        $this->execute("UPDATE item c
            JOIN item p ON p.repoId = c.repoId
            AND p.itemId = c.parentId
            SET c.parentId = p.id
            WHERE c.parentId IS NOT NULL");

        $this->addForeignKey('items_parentId', 'item', ['parentId'], 'item', ['id']);

        $this->dropColumn('item', 'updatedBy');
        $this->dropColumn('item', 'createdBy');
        $this->dropColumn('item', 'repoId');
        $this->dropColumn('item', 'itemId');

        $this->dropForeignKey('repo_user_userId', 'repo_user');
        $this->dropForeignKey('repo_user_repoId', 'repo_user');
        $this->dropTable('repo_user');

        $this->dropForeignKey('repo_updatedBy', 'repo');
        $this->dropForeignKey('repo_createdBy', 'repo');
        $this->dropTable('repo');
    }
}
