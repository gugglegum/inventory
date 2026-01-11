<?php

use yii\db\Migration;

class m260111_181720_create_inventory_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 1) inventory
        $this->createTable('{{%inventory}}', [
            'id' => $this->primaryKey()->unsigned()->comment('ID инвентаризации'),
            'containerId' => $this->integer()->unsigned()->notNull()->comment('ID контейнера'),
            'created' => $this->integer()->unsigned()->notNull()->comment('Время создания'),
            'updated' => $this->integer()->unsigned()->null()->comment('Время последнего изменения'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_inventory_containerId', '{{%inventory}}', 'containerId');
        $this->createIndex('idx_inventory_created', '{{%inventory}}', 'created');
        $this->createIndex('idx_inventory_updated', '{{%inventory}}', 'updated');

        $this->addForeignKey(
            'fk_inventory_containerId',
            '{{%inventory}}',
            'containerId',
            '{{%item}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // 2) inventory_item
        $this->createTable('{{%inventory_item}}', [
            'id' => $this->primaryKey()->unsigned()->comment('ID предмета в инвентаризации'),
            'inventoryId' => $this->integer()->unsigned()->notNull()->comment('ID инвентаризации'),
            'itemId' => $this->integer()->unsigned()->notNull()->comment('ID подтвержденного предмета'),
            'created' => $this->integer()->unsigned()->notNull()->comment('Время создания'),
            'updated' => $this->integer()->unsigned()->null()->comment('Время последнего изменения'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->createIndex('idx_inventory_item_inventoryId', '{{%inventory_item}}', 'inventoryId');
        $this->createIndex('idx_inventory_item_itemId', '{{%inventory_item}}', 'itemId');
        $this->createIndex('idx_inventory_item_created', '{{%inventory_item}}', 'created');
        $this->createIndex('idx_inventory_item_updated', '{{%inventory_item}}', 'updated');

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

        // 3) item: lastSeen, missingSince
        $this->addColumn('{{%item}}', 'lastSeen', $this->integer()->unsigned()->null()->comment('Подтверждено нахождение'));
        $this->addColumn('{{%item}}', 'missingSince', $this->integer()->unsigned()->null()->comment('Отсутствие обнаружено'));

        $this->createIndex('idx_item_lastSeen', '{{%item}}', 'lastSeen');
        $this->createIndex('idx_item_missingSince', '{{%item}}', 'missingSince');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // 3) item
        $this->dropIndex('idx_item_missingSince', '{{%item}}');
        $this->dropIndex('idx_item_lastSeen', '{{%item}}');
        $this->dropColumn('{{%item}}', 'missingSince');
        $this->dropColumn('{{%item}}', 'lastSeen');

        // 2) inventory_item
        $this->dropForeignKey('fk_inventory_item_itemId', '{{%inventory_item}}');
        $this->dropForeignKey('fk_inventory_item_inventoryId', '{{%inventory_item}}');

        $this->dropIndex('idx_inventory_item_updated', '{{%inventory_item}}');
        $this->dropIndex('idx_inventory_item_created', '{{%inventory_item}}');
        $this->dropIndex('idx_inventory_item_itemId', '{{%inventory_item}}');
        $this->dropIndex('idx_inventory_item_inventoryId', '{{%inventory_item}}');

        $this->dropTable('{{%inventory_item}}');

        // 1) inventory
        $this->dropForeignKey('fk_inventory_containerId', '{{%inventory}}');

        $this->dropIndex('idx_inventory_updated', '{{%inventory}}');
        $this->dropIndex('idx_inventory_created', '{{%inventory}}');
        $this->dropIndex('idx_inventory_containerId', '{{%inventory}}');

        $this->dropTable('{{%inventory}}');
    }
}
