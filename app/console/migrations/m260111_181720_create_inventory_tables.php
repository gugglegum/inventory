<?php

use yii\db\Migration;

class m260111_181720_create_inventory_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        // 1) inventory
        $this->createTable('{{%inventory}}', [
            'id' => $this->primaryKey()->unsigned()->comment('ID инвентаризации'),
            'containerId' => $this->integer()->unsigned()->notNull()->comment('ID контейнера'),
            'status' => $this->integer()->unsigned()->notNull()->comment('Статус инвентаризации'),
            'createdBy' => $this->integer()->unsigned()->notNull()->comment('ID начавшего инвентаризацию пользователя'),
            'closedBy' => $this->integer()->unsigned()->null()->comment('ID закрывшего инвентаризацию пользователя'),
            'created' => $this->integer()->unsigned()->notNull()->comment('Время начала инвентаризации'),
            'closed' => $this->integer()->unsigned()->null()->comment('Время закрытия инвентаризации'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_inventory_containerId', '{{%inventory}}', 'containerId');
        $this->createIndex('idx_inventory_status', '{{%inventory}}', 'status');
        $this->createIndex('idx_inventory_createdBy', '{{%inventory}}', 'createdBy');
        $this->createIndex('idx_inventory_closedBy', '{{%inventory}}', 'closedBy');
        $this->createIndex('idx_inventory_created', '{{%inventory}}', 'created');
        $this->createIndex('idx_inventory_closed', '{{%inventory}}', 'closed');

        $this->addForeignKey(
            'fk_inventory_containerId',
            '{{%inventory}}',
            'containerId',
            '{{%item}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // FK на user
        $this->addForeignKey(
            'fk_inventory_createdBy',
            '{{%inventory}}',
            'createdBy',
            '{{%user}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_inventory_closedBy',
            '{{%inventory}}',
            'closedBy',
            '{{%user}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        // 2) inventory_item
        $this->createTable('{{%inventory_item}}', [
            'id' => $this->primaryKey()->unsigned()->comment('ID предмета в инвентаризации'),
            'inventoryId' => $this->integer()->unsigned()->notNull()->comment('ID инвентаризации'),
            'itemId' => $this->integer()->unsigned()->notNull()->comment('ID подтвержденного предмета'),
            'createdBy' => $this->integer()->unsigned()->notNull()->comment('ID создавшего запись пользователя'),
            'created' => $this->integer()->unsigned()->notNull()->comment('Время создания'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_inventory_item_inventoryId', '{{%inventory_item}}', 'inventoryId');
        $this->createIndex('idx_inventory_item_itemId', '{{%inventory_item}}', 'itemId');
        $this->createIndex('idx_inventory_item_createdBy', '{{%inventory_item}}', 'createdBy');
        $this->createIndex('idx_inventory_item_created', '{{%inventory_item}}', 'created');
        $this->createIndex('idx_inventory_item_inventoryId_itemId', '{{%inventory_item}}', ['inventoryId', 'itemId'], true);

        $this->addForeignKey(
            'fk_inventory_item_inventoryId',
            '{{%inventory_item}}',
            'inventoryId',
            '{{%inventory}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_inventory_item_itemId',
            '{{%inventory_item}}',
            'itemId',
            '{{%item}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        // FK на user
        $this->addForeignKey(
            'fk_inventory_item_createdBy',
            '{{%inventory_item}}',
            'createdBy',
            '{{%user}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        // 3) item: lastSeen, missingSince
        $this->addColumn('{{%item}}', 'lastSeenBy', $this->integer()->unsigned()->null()->after('priority')->comment('Кем подтверждено нахождение'));
        $this->addColumn('{{%item}}', 'missingSinceBy', $this->integer()->unsigned()->null()->after('lastSeenBy')->comment('Кем обнаружено отсутствие'));
        $this->addColumn('{{%item}}', 'lastSeen', $this->integer()->unsigned()->null()->after('updatedBy')->comment('Когда подтверждено нахождение'));
        $this->addColumn('{{%item}}', 'missingSince', $this->integer()->unsigned()->null()->after('lastSeen')->comment('Когда обнаружено отсутствие'));

        $this->createIndex('idx_item_lastSeenBy', '{{%item}}', 'lastSeenBy');
        $this->createIndex('idx_item_missingSinceBy', '{{%item}}', 'missingSinceBy');
        $this->createIndex('idx_item_lastSeen', '{{%item}}', 'lastSeen');
        $this->createIndex('idx_item_missingSince', '{{%item}}', 'missingSince');

        // FK на user
        $this->addForeignKey(
            'fk_item_lastSeenBy',
            '{{%item}}',
            'lastSeenBy',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_item_missingSinceBy',
            '{{%item}}',
            'missingSinceBy',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        // 3) item
        $this->dropForeignKey('fk_item_missingSinceBy', '{{%item}}');
        $this->dropForeignKey('fk_item_lastSeenBy', '{{%item}}');

        $this->dropIndex('idx_item_missingSince', '{{%item}}');
        $this->dropIndex('idx_item_lastSeen', '{{%item}}');
        $this->dropIndex('idx_item_missingSinceBy', '{{%item}}');
        $this->dropIndex('idx_item_lastSeenBy', '{{%item}}');
        $this->dropColumn('{{%item}}', 'missingSince');
        $this->dropColumn('{{%item}}', 'lastSeen');
        $this->dropColumn('{{%item}}', 'missingSinceBy');
        $this->dropColumn('{{%item}}', 'lastSeenBy');

        // 2) inventory_item
        $this->dropForeignKey('fk_inventory_item_createdBy', '{{%inventory_item}}');
        $this->dropForeignKey('fk_inventory_item_itemId', '{{%inventory_item}}');
        $this->dropForeignKey('fk_inventory_item_inventoryId', '{{%inventory_item}}');

        $this->dropIndex('idx_inventory_item_inventoryId_itemId', '{{%inventory_item}}');
        $this->dropIndex('idx_inventory_item_created', '{{%inventory_item}}');
        $this->dropIndex('idx_inventory_item_createdBy', '{{%inventory_item}}');
        $this->dropIndex('idx_inventory_item_itemId', '{{%inventory_item}}');
        $this->dropIndex('idx_inventory_item_inventoryId', '{{%inventory_item}}');

        $this->dropTable('{{%inventory_item}}');

        // 1) inventory
        $this->dropForeignKey('fk_inventory_containerId', '{{%inventory}}');

        $this->dropForeignKey('fk_inventory_closedBy', '{{%inventory}}');
        $this->dropForeignKey('fk_inventory_createdBy', '{{%inventory}}');

        $this->dropIndex('idx_inventory_closed', '{{%inventory}}');
        $this->dropIndex('idx_inventory_created', '{{%inventory}}');
        $this->dropIndex('idx_inventory_closedBy', '{{%inventory}}');
        $this->dropIndex('idx_inventory_createdBy', '{{%inventory}}');
        $this->dropIndex('idx_inventory_status', '{{%inventory}}');
        $this->dropIndex('idx_inventory_containerId', '{{%inventory}}');

        $this->dropTable('{{%inventory}}');
    }
}
