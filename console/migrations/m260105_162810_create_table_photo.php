<?php

use yii\db\Migration;

class m260105_162810_create_table_photo extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        // 1) Создаём photo
        $this->createTable('{{%photo}}', [
            'id' => $this->primaryKey()->unsigned()->comment('ID фотографии'),
            'md5' => $this->char(32)->notNull()->comment('MD5 содержимого файла'),
            'size' => $this->integer()->unsigned()->notNull()->comment('Размер файла'),
            'width' => $this->integer()->unsigned()->notNull()->comment('Ширина фотографии'),
            'height' => $this->integer()->unsigned()->notNull()->comment('Высота фотографии'),
            'createdBy' => $this->integer()->unsigned()->null()->comment('ID создавшего запись пользователя'),
            'updatedBy' => $this->integer()->unsigned()->null()->comment('ID последнего изменившего запись пользователя'),
            'created' => $this->integer()->unsigned()->notNull()->comment('Время создания'),
            'updated' => $this->integer()->unsigned()->null()->comment('Время последнего изменения'),
        ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT="Фотографии"');

        $this->createIndex('idx_photo_md5', '{{%photo}}', 'md5');
        $this->createIndex('idx_photo_createdBy', '{{%photo}}', 'createdBy');
        $this->createIndex('idx_photo_updatedBy', '{{%photo}}', 'updatedBy');

        // FK на user (опционально, но обычно логично)
        $this->addForeignKey(
            'fk_photo_createdBy',
            '{{%photo}}',
            'createdBy',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_photo_updatedBy',
            '{{%photo}}',
            'updatedBy',
            '{{%user}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        // 2) Копируем строки из item_photo в photo (с сохранением id)
        $this->execute("
            INSERT INTO {{%photo}} (id, md5, size, width, height, created, updated, createdBy, updatedBy)
            SELECT id, md5, size, width, height, created, updated, NULL, NULL
            FROM {{%item_photo}}
        ");

        // 3) В item_photo добавляем photoId и заполняем (= id для уже существующих строк)
        $this->addColumn('{{%item_photo}}', 'photoId', $this->integer()->unsigned()->null()->comment('ID фотографии')->after('itemId'));
        $this->execute("UPDATE {{%item_photo}} SET photoId = id");
        $this->alterColumn('{{%item_photo}}', 'photoId', $this->integer()->unsigned()->notNull()->comment('ID фотографии'));

        $this->createIndex('idx_item_photo_photoId', '{{%item_photo}}', 'photoId');
        $this->addForeignKey(
            'fk_item_photo_photoId',
            '{{%item_photo}}',
            'photoId',
            '{{%photo}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // 4) В item_photo удаляем “фото-колонки” (они теперь в photo)
        // Сначала индекс, который ссылается на md5
        $this->dropIndex('md5', '{{%item_photo}}');

        $this->dropColumn('{{%item_photo}}', 'md5');
        $this->dropColumn('{{%item_photo}}', 'size');
        $this->dropColumn('{{%item_photo}}', 'width');
        $this->dropColumn('{{%item_photo}}', 'height');
        $this->dropColumn('{{%item_photo}}', 'created');
        $this->dropColumn('{{%item_photo}}', 'updated');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        // Возврат к исходной структуре item_photo

        // 4 back) вернуть колонки md5/size/width/height/created/updated
        $this->addColumn('{{%item_photo}}', 'md5', $this->char(32)->notNull()->comment('MD5 содержимого файла')->after('itemId'));
        $this->addColumn('{{%item_photo}}', 'size', $this->integer()->unsigned()->notNull()->comment('Размер файла')->after('md5'));
        $this->addColumn('{{%item_photo}}', 'width', $this->integer()->unsigned()->notNull()->comment('Ширина фотографии')->after('size'));
        $this->addColumn('{{%item_photo}}', 'height', $this->integer()->unsigned()->notNull()->comment('Высота фотографии')->after('width'));
        $this->addColumn('{{%item_photo}}', 'created', $this->integer()->notNull()->comment('Время создания')->after('sortIndex'));
        $this->addColumn('{{%item_photo}}', 'updated', $this->integer()->notNull()->comment('Время последнего изменения')->after('created'));

        // Заполнить их из photo
        $this->execute("
            UPDATE {{%item_photo}} ip
            JOIN {{%photo}} p ON p.id = ip.photoId
            SET ip.md5 = p.md5,
                ip.size = p.size,
                ip.width = p.width,
                ip.height = p.height
        ");

        $this->createIndex('md5', '{{%item_photo}}', 'md5');

        // 3 back) убрать связь на photo
        $this->dropForeignKey('fk_item_photo_photoId', '{{%item_photo}}');
        $this->dropIndex('idx_item_photo_photoId', '{{%item_photo}}');
        $this->dropColumn('{{%item_photo}}', 'photoId');

        // 1 back) удалить photo (сначала FK)
        $this->dropForeignKey('fk_photo_createdBy', '{{%photo}}');
        $this->dropForeignKey('fk_photo_updatedBy', '{{%photo}}');
        $this->dropTable('{{%photo}}');
    }
}
