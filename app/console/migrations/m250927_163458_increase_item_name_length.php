<?php

use yii\db\Migration;

class m250927_163458_increase_item_name_length extends Migration
{
    private const TABLE = 'items';

    public function safeUp()
    {
        // сохраняем ту же кодировку/сортировку, что была изначально у колонки
        $this->alterColumn(
            self::TABLE,
            'name',
            $this->string(200)->notNull()->comment('Наименование')->append(' COLLATE utf8_unicode_ci')
        );
    }

    public function safeDown()
    {
        $this->alterColumn(
            self::TABLE,
            'name',
            $this->string(100)->notNull()->comment('Наименование')->append(' COLLATE utf8_unicode_ci')
        );
    }
}
