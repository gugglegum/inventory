<?php

namespace common\models;

use common\components\ItemAccessValidator;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 * Предмет
 *
 * @property int $id ID предмета (глобальный по всем репозиториям)
 * @property int $itemId ID предмета (внутри репозитория)
 * @property int $parentItemId ID родительского предмета-контейнера (ссылка на itemId)
 * @property int repoId ID репозитория
 * @property string $name Наименование
 * @property string $description Описание
 * @property int $isContainer Является ли предмет контейнером?
 * @property int $priority Приоритет сортировки
 * @property int $createdBy ID создавшего запись пользователя
 * @property int $updatedBy ID последнего изменившего запись пользователя
 * @property int $created Время создания
 * @property int $updated Время последнего изменения
 *
 * @property ItemRelation[] $itemRelations
 * @property ItemRelation[] $itemBackRelations
 * @property Item $parentItem
 * @property Repo $repo
 * @property Item[] $items
 * @property ItemPhoto[] $itemPhotos
 * @property ItemPhoto $primaryPhoto
 * @property ItemPhoto[] $secondaryPhotos
 * @property ItemTag[] $itemTags
 */
class Item extends ActiveRecord
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

    private ItemAccessValidator $itemAccessValidator;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'item';
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
    public function scenarios(): array
    {
        $scenarios = parent::scenarios();

        $scenarios[self::SCENARIO_CREATE] = ['parentItemId', 'name', 'description', 'isContainer', 'priority'];
        $scenarios[self::SCENARIO_UPDATE] = ['itemId', 'parentItemId', 'name', 'description', 'isContainer', 'priority'];

        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['itemId', 'repoId', 'name', 'isContainer', 'createdBy'], 'required'],
            [['itemId', 'parentItemId', 'repoId', 'isContainer', 'priority', 'createdBy', 'updatedBy'], 'integer'],
            [['parentItemId'], 'checkParentExists'],
            [['parentItemId'], 'checkParentIsNotLooped'],
            [['name', 'description'], 'filter', 'filter' => 'trim'],
            [['description'], 'string'],
            [['name'], 'string', 'max' => 200],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID предмета (глобальный)',
            'itemId' => 'ID предмета',
            'parentItemId' => 'ID родительского предмета-контейнера',
            'repoId' => 'ID репозитория',
            'name' => 'Наименование',
            'description' => 'Описание',
            'isContainer' => 'Является ли предмет контейнером?',
            'priority' => 'Приоритет сортировки',
            'createdBy' => 'ID создавшего предмет пользователя',
            'updatedBy' => 'ID последнего изменившего предмет пользователя',
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
            if (!$this->itemAccessValidator->hasUserAccessToRepoById($this->repoId, RepoUser::ACCESS_DELETE_ITEMS)) {
                $this->addError('', 'Недостаточно прав для удаления предмета.');
                return false;
            }

            foreach ($this->itemPhotos as $itemPhoto) {
                $itemPhoto->delete();
            }
            foreach ($this->items as $item) {
                $item->setItemAccessValidator($this->itemAccessValidator);
                $item->delete();
            }
            return true;
        } else {
            return false;
        }
    }

    public function beforeSave($insert): bool
    {
        if (!$this->itemAccessValidator->hasUserAccessToRepoById($this->repoId, $insert ? RepoUser::ACCESS_CREATE_ITEMS : RepoUser::ACCESS_EDIT_ITEMS)) {
            $this->addError('', 'Недостаточно прав для сохранения предмета.');
            return false;
        }

        if ($this->itemId === null) {
            $this->itemId = $this->getNextAvailableItemId();
        }

        if (trim((string) $this->priority) === '') {
            $this->priority = 0;
        }
        return parent::beforeSave($insert);
    }

    public function setItemAccessValidator(ItemAccessValidator $itemAccessValidator): static
    {
        $this->itemAccessValidator = $itemAccessValidator;
        return $this;
    }

    /**
     * Проверяет новый parentItemId на существование предмета с таким ID
     *
     * @param $attribute
     * @return void
     */
    public function checkParentExists($attribute): void
    {
        if ($this->parentItemId != null && $this->parentItem == null) {
            $this->addError($attribute, 'Родительский предмет не существует');
        }
    }

    /**
     * Проверяет новый parentItemId на отсутствие петли в цепочке родительских предметов, т.е. когда мы делаем parentItemId равным itemId
     * или равным itemId какого-то из дочерних предметов.
     *
     * @param $attribute
     * @return void
     */
    public function checkParentIsNotLooped($attribute): void
    {
        $parentItem = $this->parentItem;
        while ($parentItem != null) {
            if ($parentItem->id == $this->id) {
                $this->addError($attribute, 'Родительский предмет является одновременно дочерним (что образует бесконечную цепочку вложенности предметов)');
            }
            $parentItem = $parentItem->parentItem;
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

    public function getRepo(): ActiveQuery
    {
        return $this->hasOne(Repo::class, ['id' => 'repoId']);
    }

    public function getItemRelations(): ActiveQuery
    {
        return $this->hasMany(ItemRelation::class, ['srcItemId' => 'id']);
    }

    public function getItemBackRelations(): ActiveQuery
    {
        return $this->hasMany(ItemRelation::class, ['dstItemId' => 'id']);
    }

    public function getParentItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['repoId' => 'repoId', 'itemId' => 'parentItemId']);
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['repoId' => 'repoId', 'parentItemId' => 'itemId'])->orderBy(['isContainer' => SORT_DESC, 'id' => SORT_ASC]);
    }

    public function getItemPhotos(): ActiveQuery
    {
        return $this->hasMany(ItemPhoto::class, ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC]);
    }

    public function getPrimaryPhoto(): ActiveQuery
    {
        return $this->hasOne(ItemPhoto::class, ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->limit(1);
    }

    public function getSecondaryPhotos(): ActiveQuery
    {
        return $this->hasMany(ItemPhoto::class, ['itemId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->offset(1);
    }

    public function getItemTags(): ActiveQuery
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

    public function getNextAvailableItemId(): int
    {
        $repo = $this->repo;
        $itemId = $repo->lastItemId + 1;
        while (Item::find()->where(['repoId' => $repo->id, 'itemId' => $itemId])->count() !== 0) {
            $itemId++;
        }
        return $itemId;
    }
}
