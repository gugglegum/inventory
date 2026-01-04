<?php

namespace common\models;

use common\components\ItemAccessValidator;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\db\StaleObjectException;

/**
 * Репозиторий
 *
 * @property integer $id ID репозитория
 * @property string $name Название репозитория
 * @property string $description Описание репозитория
 * @property int $priority Приоритет сортировки
 * @property int $lastItemId Счётчик предметов внутри репозитория для использования в Item.itemId
 * @property int $createdBy ID создавшего репозиторий пользователя
 * @property int $updatedBy ID последнего изменившего репозиторий пользователя
 * @property int $created Время создания
 * @property ?int $updated Время последнего изменения
 *
 * @property Item[] $items
 * @property User $createdByUser
 * @property ?User $updatedByUser
 * @property RepoUser[] $repoUsers
 */
class Repo extends ActiveRecord
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

    private ItemAccessValidator $itemAccessValidator;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'repo';
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
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created'], // только created
                    BaseActiveRecord::EVENT_BEFORE_UPDATE => ['updated'], // только updated
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios(): array
    {
        $scenarios = parent::scenarios();

        $scenarios[self::SCENARIO_CREATE] = ['name', 'description', 'priority', 'lastItemId'];
        $scenarios[self::SCENARIO_UPDATE] = ['name', 'description', 'priority', 'lastItemId'];

        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name', 'createdBy', 'priority', 'lastItemId'], 'required'],
            [['name'], 'string', 'max' => 64],
            [['description'], 'string'],
            [['priority', 'lastItemId', 'createdBy'], 'integer'],
            [['name', 'description'], 'filter', 'filter' => 'trim'],
            [['createdBy'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['createdBy' => 'id']],
            [['updatedBy'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['updatedBy' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Наименование',
            'description' => 'Описание',
            'priority' => 'Приоритет сортировки',
            'lastItemId' => 'ID последнего предмета',
            'createdBy' => 'ID создавшего репозиторий пользователя',
            'updatedBy' => 'ID последнего изменившего репозиторий пользователя',
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
            if (!$this->itemAccessValidator->hasUserAccessToRepoById($this->id, RepoUser::ACCESS_DELETE_REPO)) {
                $this->addError('', 'Недостаточно прав для удаления репозитория.');
                return false;
            }
            // Удаляем корневые контейнеры в репозитории, они каскадно должны удалить все вложенные предметы и все фото
            /** @var Item $item */
            foreach ($this->getItems()->where(['parentItemId' => null])->each() as $item) {
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
        if (!$insert) {
            // Список полей, которые можно обновлять без проверки прав
            $fieldsToUpdateWithoutAccessValidation = ['lastItemId'];

            // Список полей, которые выходят за рамки тех, что можно обновлять без проверки прав
            $fieldsRequireAccessValidation = array_diff_key($this->dirtyAttributes, array_fill_keys($fieldsToUpdateWithoutAccessValidation, null));

            // Если среди грязных полей есть что-то, кроме разрешенных без проверки прав -- проверяем права
            if (!empty($fieldsRequireAccessValidation)) {
                if (!$this->itemAccessValidator->hasUserAccessToRepoById($this->id, RepoUser::ACCESS_EDIT_REPO)) {
                    $this->addError('', 'Недостаточно прав для сохранения репозитория.');
                    return false;
                }
            }
        }

        return parent::beforeSave($insert);
    }

    public function setItemAccessValidator(ItemAccessValidator $itemAccessValidator): static
    {
        $this->itemAccessValidator = $itemAccessValidator;
        return $this;
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(Item::class, ['repoId' => 'id']);
    }

    public function getCreatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'createdBy']);
    }

    public function getUpdatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'updatedBy']);
    }

    public function getRepoUsers(): ActiveQuery
    {
        return $this->hasMany(RepoUser::class, ['repoId' => 'id']);
    }

    /**
     * @inheritdoc
     * @return RepoQuery the active query used by this AR class.
     */
    public static function find(): RepoQuery
    {
        return new RepoQuery(get_called_class());
    }
}
