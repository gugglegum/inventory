<?php

use yii\db\Migration;

class m260119_223735_move_repo_priority_to_repo_user extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%repo_user}}',
            'priority',
            $this->integer()
                ->notNull()
                ->defaultValue(0)
                ->comment('Приоритет сортировки')
        );
        $this->createIndex('priority', '{{%repo_user}}', 'priority');
        $this->dropColumn('{{%repo}}', 'priority');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->addColumn(
            '{{%repo}}',
            'priority',
            $this->integer()
                ->notNull()
                ->defaultValue(0)
                ->after('description')
                ->comment('Приоритет сортировки')
        );
        $this->createIndex('priority', '{{%repo}}', 'priority');
        $this->dropColumn('{{%repo_user}}', 'priority');
    }
}
