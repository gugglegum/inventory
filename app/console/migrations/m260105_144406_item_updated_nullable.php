<?php

use yii\db\Migration;

class m260105_144406_item_updated_nullable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->alterColumn('item', 'created', $this->integer()->unsigned()->notNull()->comment('Время создания'));
        $this->alterColumn('item', 'updated', $this->integer()->unsigned()->null()->comment('Время последнего изменения'));

        // Ранее поле updated инициализировалось в текущее время при создании записи. Сейчас update остаётся NULL до первого изменения. Для существующих записей делаем updated = null если updated совпадает с created и updatedBy = null
        $this->execute('UPDATE `item` SET `updated` = NULL WHERE `updated` = `created` AND `updatedBy` IS NULL');

        $this->alterColumn('repo', 'created', $this->integer()->unsigned()->notNull()->comment('Время создания'));
        $this->alterColumn('repo', 'updated', $this->integer()->unsigned()->null()->comment('Время последнего изменения'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->alterColumn('repo', 'updated', $this->integer()->null()->comment('Время последнего изменения'));
        $this->alterColumn('repo', 'created', $this->integer()->notNull()->comment('Время создания'));

        // Если в таблице уже есть NULL, откат упадёт.
        // Поэтому сначала заполним NULL значением created
        $this->execute('UPDATE item SET `updated` = `created` WHERE `updated` IS NULL');

        $this->alterColumn('item', 'updated', $this->integer()->notNull()->comment('Время последнего изменения'));
        $this->alterColumn('item', 'created', $this->integer()->notNull()->comment('Время создания'));
    }
}
