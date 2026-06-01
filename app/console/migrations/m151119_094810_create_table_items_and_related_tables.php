<?php

use yii\db\Migration;

class m151119_094810_create_table_items_and_related_tables extends Migration
{
    public function up()
    {
        // Таблица items
        $this->createTable('items', [
            'id' => "int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID предмета'",
            'parentId' => "int(10) unsigned DEFAULT NULL COMMENT 'ID родительского предмета-контейнера'",
            'name' => "varchar(100) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Наименование'",
            'description' => "text COLLATE utf8_unicode_ci COMMENT 'Описание'",
            'isContainer' => "tinyint(1) unsigned NOT NULL COMMENT 'Является ли предмет контейнером?'",
            'created' => "int(11) NOT NULL COMMENT 'Время создания'",
            'updated' => "int(11) NOT NULL COMMENT 'Время последнего изменения'",
            "PRIMARY KEY (`id`)",
            "KEY `parentId` (`parentId`)",
        ], "ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Предметы'");
        $this->addForeignKey('items_parentId', 'items', ['parentId'], 'items', ['id']);

        // Таблица items_photos
        $this->createTable('items_photos', [
            'id' => "int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID фотографии'",
            'itemId' => "int(10) unsigned NOT NULL COMMENT 'ID предмета'",
            'md5' => "char(32) COLLATE utf8_unicode_ci NOT NULL COMMENT 'MD5 содержимого файла'",
            'size' => "int(10) unsigned NOT NULL COMMENT 'Размер файла'",
            'width' => "int(10) unsigned NOT NULL COMMENT 'Ширина фотографии'",
            'height' => "int(10) unsigned NOT NULL COMMENT 'Высота фотографии'",
            'sortIndex' => "int(11) NOT NULL COMMENT 'Порядковый номер'",
            'created' => "int(11) NOT NULL COMMENT 'Время создания'",
            'updated' => "int(11) NOT NULL COMMENT 'Время последнего изменения'",
            "PRIMARY KEY (`id`)",
            "UNIQUE KEY `sort` (`itemId`,`sortIndex`)",
            "KEY `itemId` (`itemId`)",
            "KEY `md5` (`md5`)"
        ], "ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Фотографии предметов'");
        $this->addForeignKey('items_photos_itemId', 'items_photos', ['itemId'], 'items', ['id'], 'CASCADE', 'CASCADE');

        // Таблица items_relations
        $this->createTable('items_relations', [
            'srcItemId' => "int(10) unsigned NOT NULL COMMENT 'Исходный предмет'",
            'dstItemId' => "int(10) unsigned NOT NULL COMMENT 'Предмет назначения'",
            'type' => "int(10) unsigned NOT NULL COMMENT 'Код типа связи'",
            'description' => "text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Описание отношения'",
            'created' => "int(11) NOT NULL COMMENT 'Время создания связи'",
            "PRIMARY KEY (`srcItemId`,`dstItemId`,`type`)",
            "KEY `dstItemId` (`dstItemId`)",
        ], "ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Отношения между предметами'");
        $this->addForeignKey('item_relations_dstItemId', 'items_relations', ['dstItemId'], 'items', ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey('item_relations_srcItemId', 'items_relations', ['srcItemId'], 'items', ['id'], 'CASCADE', 'CASCADE');

        // Таблица items_tags
        $this->createTable('items_tags', [
            'itemId' => "int(10) unsigned NOT NULL COMMENT 'ID предмета'",
            'tag' => "varchar(40) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Метка'",
            "PRIMARY KEY (`itemId`,`tag`)",
        ], "ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Метки предметов'");
        $this->addForeignKey('items_tags_itemId', 'items_tags', ['itemId'], 'items', ['id'], 'CASCADE', 'CASCADE');
    }

    public function down()
    {
        // Таблица items_tags
        $this->dropForeignKey('items_tags_itemId', 'items_tags');
        $this->dropTable('items_tags');

        // Таблица items_relations
        $this->dropForeignKey('item_relations_srcItemId', 'items_relations');
        $this->dropForeignKey('item_relations_dstItemId', 'items_relations');
        $this->dropTable('items_relations');

        // Таблица items_photos
        $this->dropForeignKey('items_photos_itemId', 'items_photos');
        $this->dropTable('items_photos');

        // Таблица items
        $this->dropForeignKey('items_parentId', 'items');
        $this->dropTable('items');
    }
}
