<?php

use yii\db\Migration;

/**
 * Добавляет в таблицу user колонку access с правами пользователя в виде битовой маски.
 */
class m260104_214216_add_access_to_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        // int(10) unsigned, NOT NULL, DEFAULT 0
        $this->addColumn(
            '{{%user}}',
            'access',
            $this->integer()
                ->unsigned()
                ->notNull()
                ->defaultValue(0)
                ->after('email')
                ->comment('Глобальные права доступа (bitmask)')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->dropColumn('{{%user}}', 'access');
    }
}
