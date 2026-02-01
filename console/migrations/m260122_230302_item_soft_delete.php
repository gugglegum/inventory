<?php

use yii\db\Migration;

class m260122_230302_item_soft_delete extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->addColumn('{{%item}}', 'deleted', $this->integer()->unsigned()->null()->comment('Время удаления'));
        $this->addColumn('{{%item}}', 'deletedBy', $this->integer()->unsigned()->null()->comment('ID удалившего пользователя')->after('updatedBy'));

        $this->createIndex('idx_item_deleted', '{{%item}}', 'deleted');
        $this->createIndex('idx_item_deletedBy', '{{%item}}', 'deletedBy');

        $this->addForeignKey(
            'fk_item_deletedBy',
            '{{%item}}',
            'deletedBy',
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
        $this->dropForeignKey('fk_item_deletedBy', '{{%item}}');

        $this->dropIndex('idx_item_deletedBy', '{{%item}}');
        $this->dropIndex('idx_item_deleted', '{{%item}}');

        $this->dropColumn('{{%item}}', 'deletedBy');
        $this->dropColumn('{{%item}}', 'deleted');
    }
}
