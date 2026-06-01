<?php

use yii\db\Migration;

class m260105_181720_create_table_post extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->createTable('{{%post}}', [
            'id' => $this->primaryKey()->unsigned()->comment('ID поста'),
            'itemId' => $this->integer()->unsigned()->notNull()->comment('ID предмета, к которому относится пост'),
            'datetime' => $this->integer()->unsigned()->notNull()->comment('Дата и время, к которому относится пост'),
            'title' => $this->string(200)->notNull()->comment('Заголовок'),
            'text' => $this->text()->comment('Текст'),
            'createdBy' => $this->integer()->unsigned()->notNull()->comment('ID создавшего запись пользователя'),
            'updatedBy' => $this->integer()->unsigned()->null()->comment('ID последнего изменившего запись пользователя'),
            'created' => $this->integer()->unsigned()->notNull()->comment('Время создания'),
            'updated' => $this->integer()->unsigned()->null()->comment('Время последнего изменения'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Посты к предметам"');

        $this->createIndex('idx_post_itemId', '{{%post}}', 'itemId');
        $this->createIndex('idx_post_datetime', '{{%post}}', 'datetime');
        $this->createIndex('idx_post_createdBy', '{{%post}}', 'createdBy');
        $this->createIndex('idx_post_updatedBy', '{{%post}}', 'updatedBy');

        // FK на item
        $this->addForeignKey(
            'fk_post_itemId',
            '{{%post}}',
            'itemId',
            '{{%item}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        // FK на user
        $this->addForeignKey(
            'fk_post_createdBy',
            '{{%post}}',
            'createdBy',
            '{{%user}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_post_updatedBy',
            '{{%post}}',
            'updatedBy',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createTable('{{%post_photo}}', [
            'id' => $this->primaryKey()->unsigned()->comment('ID фотографии поста'),
            'postId' => $this->integer()->unsigned()->notNull()->comment('ID поста, к которому относится фото'),
            'photoId' => $this->integer()->unsigned()->notNull()->comment('ID фотографии'),
            'sortIndex' => $this->integer()->notNull()->comment('Порядковый номер'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Фотографии к постам"');

        $this->createIndex('idx_post_photo_postId', '{{%post_photo}}', 'postId');
        $this->createIndex('idx_post_photo_photoId', '{{%post_photo}}', 'photoId');
        $this->createIndex('idx_post_photo_photoId_SortIndex', '{{%post_photo}}', ['photoId', 'sortIndex'], true);

        // FK на post
        $this->addForeignKey(
            'fk_post_photo_postId',
            '{{%post_photo}}',
            'postId',
            '{{%post}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        // FK на photo
        $this->addForeignKey(
            'fk_post_photo_photoId',
            '{{%post_photo}}',
            'photoId',
            '{{%photo}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->dropForeignKey('fk_post_photo_photoId', '{{%post_photo}}');
        $this->dropForeignKey('fk_post_photo_postId', '{{%post_photo}}');

        $this->dropIndex('idx_post_photo_photoId_SortIndex', '{{%post_photo}}');
        $this->dropIndex('idx_post_photo_photoId', '{{%post_photo}}');
        $this->dropIndex('idx_post_photo_postId', '{{%post_photo}}');
        $this->dropTable('{{%post_photo}}');

        $this->dropForeignKey('fk_post_updatedBy', '{{%post}}');
        $this->dropForeignKey('fk_post_createdBy', '{{%post}}');
        $this->dropForeignKey('fk_post_itemId', '{{%post}}');

        $this->dropIndex('idx_post_updatedBy', '{{%post}}');
        $this->dropIndex('idx_post_createdBy', '{{%post}}');
        $this->dropIndex('idx_post_datetime', '{{%post}}');
        $this->dropIndex('idx_post_itemId', '{{%post}}');
        $this->dropTable('{{%post}}');
    }
}
