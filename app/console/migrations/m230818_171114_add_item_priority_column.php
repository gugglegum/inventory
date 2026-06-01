<?php

use yii\db\Migration;

/**
 * Class m230818_171114_add_item_priority_column
 */
class m230818_171114_add_item_priority_column extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->addColumn('items', 'priority', "INT DEFAULT 0 COMMENT 'Приоритет сортировки' AFTER `isContainer`");
        $this->createIndex('priority', 'items', 'priority');
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->dropColumn('items', 'priority');
    }

}
