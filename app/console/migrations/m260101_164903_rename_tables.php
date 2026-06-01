<?php

use yii\db\Migration;

class m260101_164903_rename_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        // На время переименований удобнее отключить проверки FK,
        // чтобы порядок переименований не имел значения.
        $this->execute('SET FOREIGN_KEY_CHECKS=0');

        // Дочерние/связующие таблицы — первыми (просто как привычка).
        $this->renameTable('{{%items_photos}}',    '{{%item_photo}}');
        $this->renameTable('{{%items_relations}}', '{{%item_relation}}');
        $this->renameTable('{{%items_tags}}',      '{{%item_tag}}');

        // Основные сущности
        $this->renameTable('{{%items}}', '{{%item}}');
        $this->renameTable('{{%users}}', '{{%user}}');

        $this->execute('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=0');

        $this->renameTable('{{%item_photo}}',    '{{%items_photos}}');
        $this->renameTable('{{%item_relation}}', '{{%items_relations}}');
        $this->renameTable('{{%item_tag}}',      '{{%items_tags}}');

        $this->renameTable('{{%item}}', '{{%items}}');
        $this->renameTable('{{%user}}', '{{%users}}');

        $this->execute('SET FOREIGN_KEY_CHECKS=1');
    }
}
