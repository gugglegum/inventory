<?php

use yii\db\Migration;

/**
 * Class m230821_011427_fix_item_priority_column
 */
class m230821_011427_fix_item_priority_column extends Migration
{
    public function up()
    {
        $this->alterColumn('items', 'priority', "INT NOT NULL DEFAULT 0 COMMENT 'Приоритет сортировки' AFTER `isContainer`");
    }

    public function down()
    {
        $this->alterColumn('items', 'priority', "INT DEFAULT 0 COMMENT 'Приоритет сортировки' AFTER `isContainer`");
    }
}
