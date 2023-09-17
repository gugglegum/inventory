<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 * Предмет
 *
 * @property string $id
 * @property string $parentId
 * @property string $name
 * @property string $description
 * @property integer $isContainer
 * @property integer $priority
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
    public static function tableName(): string
    {
        return 'items';
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created',
                'updatedAtAttribute' => 'updated',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name', 'isContainer'], 'required'],
            [['parentId', 'isContainer'], 'integer'],
            [['parentId'], 'checkParentExists'],
            [['parentId'], 'checkParentIsNotLooped'],
            [['name', 'description'], 'filter', 'filter' => 'trim'],
            [['description'], 'string'],
            [['name'], 'string', 'max' => 100],
            [['priority'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID предмета',
            'parentId' => 'ID родительского предмета-контейнера',
            'name' => 'Наименование',
            'description' => 'Описание',
            'isContainer' => 'Является ли предмет контейнером?',
            'priority' => 'Приоритет сортировки',
            'created' => 'Время создания',
            'updated' => 'Время последнего изменения',
        ];
    }

    /**
     * @return bool
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function beforeDelete(): bool
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

    public function beforeSave($insert): bool
    {
        if (trim($this->priority) == '') {
            $this->priority = 0;
        }
        return parent::beforeSave($insert);
    }

    /**
     * Проверяет новый parentId на существование предмета с таким ID
     *
     * @param $attribute
     * @return void
     */
    public function checkParentExists($attribute): void
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
    public function checkParentIsNotLooped($attribute): void
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
    public function saveTags(array $tags): void
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

    /**
     * @param string $tagsString
     * @return void
     * @throws \yii\db\Exception
     */
    public function saveTagsFromString(string $tagsString): void
    {
        $tags = preg_split('/\s*,\s*/', $tagsString, -1, PREG_SPLIT_NO_EMPTY);
        $this->saveTags($tags);
    }

    /**
     * @return string[]
     * @throws \yii\db\Exception
     */
    public function fetchTags(): array
    {
        return self::getDb()->createCommand('SELECT tag FROM ' . ItemTag::tableName() . ' WHERE itemId = :itemId ORDER BY tag ASC', ['itemId' => $this->id])->queryColumn();
    }

    /**
     * @param string $separator
     * @return string
     * @throws Exception
     */
    public function fetchTagsAsString(string $separator = ', '): string
    {
        $tags = $this->fetchTags();
        return implode($separator, $tags);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemRelations(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ItemRelation::class, ['srcItemId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemBackRelations(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ItemRelation::class, ['dstItemId' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'parentId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItems(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Item::class, ['parentId' => 'id'])->orderBy(['isContainer' => SORT_DESC, 'id' => SORT_ASC]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemPhotos(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ItemPhoto::class, ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPrimaryPhoto(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ItemPhoto::class, ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->limit(1);
    }

    public function getSecondaryPhotos(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ItemPhoto::class, ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->offset(1);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItemTags(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ItemTag::class, ['itemId' => 'id']);
    }

    /**
     * @inheritdoc
     * @return ItemQuery the active query used by this AR class.
     */
    public static function find(): ItemQuery
    {
        return new ItemQuery(get_called_class());
    }
}
