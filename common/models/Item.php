<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Предмет
 *
 * @property string $id
 * @property string $parentId
 * @property string $name
 * @property string $description
 * @property integer $isContainer
 * @property integer $created
 * @property integer $updated
 *
 * @property ItemRelation[] $itemRelations
 * @property ItemRelation[] $itemBackRelations
 * @property Item $parent
 * @property Item[] $items
 * @property ItemPhoto[] $itemPhotos
 * @property ItemPhoto $primaryPhoto
 * @property ItemPhoto[] $secondaryPhotos
 * @property ItemTag[] $itemTags
 */
class Item extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'items';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'created',
                'updatedAtAttribute' => 'updated',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'isContainer'], 'required'],
            [['parentId', 'isContainer'], 'integer'],
            [['parentId'], 'checkParentExists'],
            [['parentId'], 'checkParentIsNotLooped'],
            [['name', 'description'], 'filter', 'filter' => 'trim'],
            [['description'], 'string'],
            [['name'], 'string', 'max' => 100],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID предмета',
            'parentId' => 'ID родительского предмета-контейнера',
            'name' => 'Наименование',
            'description' => 'Описание',
            'isContainer' => 'Является ли предмет контейнером?',
            'created' => 'Время создания',
            'updated' => 'Время последнего изменения',
        ];
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            foreach ($this->items as $item) {
                $item->delete();
            }
            foreach ($this->itemPhotos as $itemPhoto) {
                $itemPhoto->delete();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Проверяет новый parentId на существование предмета с таким ID
     *
     * @param $attribute
     * @return void
     */
    public function checkParentExists($attribute)
    {
        if ($this->parentId != null && $this->parent == null) {
            $this->addError($attribute, 'Родительский предмет не существует');
        }
    }

    /**
     * Проверяет новый parentId на отсутствие петли в цепочке родительских предметов, т.е. когда мы делаем parentId равным id
     * или равным ID какого-то из дочерних предметов.
     *
     * @param $attribute
     * @return void
     */
    public function checkParentIsNotLooped($attribute)
    {
        $parentItem = $this->parent;
        while ($parentItem != null) {
            if ($parentItem->id == $this->id) {
                $this->addError($attribute, 'Родительский предмет является одновременно дочерним (что образует бесконечную цепочку вложенности предметов)');
            }
            $parentItem = $parentItem->parent;
        }
    }


    /**
     * @param array $tags
     * @throws \yii\db\Exception
     */
    public function saveTags(array $tags)
    {
        // Удаляем тегов, которых больше нет
        self::getDb()->createCommand()
            ->delete(ItemTag::tableName(), [
                'and', 'itemId = :itemId', ['not in', 'tag', $tags]
            ], ['itemId' => $this->id])
            ->execute();

        // Добавляем теги, которых не было
        foreach ($tags as $tag) {
            self::getDb()->createCommand('REPLACE INTO ' . ItemTag::tableName() . ' (itemId, tag) VALUES (:itemId, :tag)', ['itemId' => $this->id, 'tag' => $tag])->execute();
        }
    }

    public function saveTagsFromString($tagsString)
    {
        $tags = preg_split('/\s*,\s*/', $tagsString, -1, PREG_SPLIT_NO_EMPTY);
        $this->saveTags($tags);
    }

    /**
     * @return array
     * @throws \yii\db\Exception
     */
    public function fetchTags()
    {
        return self::getDb()->createCommand('SELECT tag FROM ' . ItemTag::tableName() . ' WHERE itemId = :itemId ORDER BY tag ASC', ['itemId' => $this->id])->queryColumn();
    }

    public function fetchTagsAsString($separator = ', ')
    {
        $tags = $this->fetchTags();
        return implode($separator, $tags);
    }

        /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemRelations()
    {
        return $this->hasMany(ItemRelation::className(), ['srcItemId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemBackRelations()
    {
        return $this->hasMany(ItemRelation::className(), ['dstItemId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(Item::className(), ['id' => 'parentId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItems()
    {
        return $this->hasMany(Item::className(), ['parentId' => 'id'])->orderBy(['isContainer' => SORT_DESC, 'id' => SORT_ASC]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemPhotos()
    {
        return $this->hasMany(ItemPhoto::className(), ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPrimaryPhoto()
    {
        return $this->hasOne(ItemPhoto::className(), ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->limit(1);
    }

    public function getSecondaryPhotos()
    {
        return $this->hasMany(ItemPhoto::className(), ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->offset(1);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemTags()
    {
        return $this->hasMany(ItemTag::className(), ['itemId' => 'id']);
    }

    /**
     * @inheritdoc
     * @return ItemQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ItemQuery(get_called_class());
    }
}
