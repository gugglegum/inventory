<?php

namespace common\models;

use common\components\ItemAccessValidator;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use Yii;

/**
 * Пост к предмету
 *
 * @property int $id ID поста
 * @property int $itemId ID предмета, к которому относится пост
 * @property int $datetime Дата и время, к которому относится пост
 * @property string $title Заголовок поста
 * @property ?string $text Текст поста
 * @property int $createdBy ID создавшего запись пользователя
 * @property ?int $updatedBy ID последнего изменившего запись пользователя
 * @property int $created Время создания
 * @property ?int $updated Время последнего изменения
 *
 * @property Item $item
 * @property PostPhoto[] $postPhotos
 * @property PostPhoto $primaryPhoto
 * @property PostPhoto[] $secondaryPhotos
 * @property ?User $createdByUser
 * @property ?User $updatedByUser
 */
class Post extends ActiveRecord
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

    public ?string $datetimeText = null; // виртуальное поле даты и времени в текстовом виде

//    private ItemAccessValidator $itemAccessValidator;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'post';
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

        $scenarios[self::SCENARIO_CREATE] = ['datetimeText', 'title', 'text'];
        $scenarios[self::SCENARIO_UPDATE] = ['datetimeText', 'title', 'text'];

        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['title'], 'required'],
            [['title', 'text'], 'string'],
            [['datetime'], 'string'],
            [['title', 'text'], 'filter', 'filter' => 'trim'],
            [['title'], 'string', 'max' => 200],

            [['datetimeText'], 'required'],
            [['datetimeText'], 'filter', 'filter' => 'trim'],
            [['datetimeText'], 'datetime', 'format' => 'php:d.m.Y H:i'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID поста',
            'itemId' => 'ID предмета',
            'datetime' => 'Unixtime, к которому относится пост',
            'datetimeText' => 'Дата и время, к которому относится пост',
            'title' => 'Заголовок поста',
            'text' => 'Текст поста',
            'createdBy' => 'ID создавшего предмет пользователя',
            'updatedBy' => 'ID последнего изменившего предмет пользователя',
            'created' => 'Время создания',
            'updated' => 'Время последнего изменения',
        ];
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->datetimeText = $this->datetime ? Yii::$app->formatter->asDatetime($this->datetime, 'php:d.m.Y H:i') : null;
    }

    public function beforeValidate()
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        if ($this->datetimeText !== null && $this->datetimeText !== '') {
            // ВАЖНО: реши в какой TZ пользователь вводит дату.
            // Если пока считаешь, что ввод в TZ приложения (или сервера) — ок:
            $tz = new \DateTimeZone(Yii::$app->timeZone ?: 'UTC');

            $dt = \DateTimeImmutable::createFromFormat('d.m.Y H:i', $this->datetimeText, $tz);

            if ($dt === false) {
                $this->addError('datetimeText', 'Неверный формат даты/времени.');
            } else {
                $this->datetime = $dt->getTimestamp(); // int в БД
            }
        }

        return !$this->hasErrors();
    }

    /**
     * @return bool
     * @throws \Throwable
     */
    public function beforeDelete(): bool
    {
        if (parent::beforeDelete()) {
//            if (!$this->itemAccessValidator->hasUserAccessToRepoById($this->repoId, RepoUser::ACCESS_DELETE_ITEMS)) {
//                $this->addError('', 'Недостаточно прав для удаления предмета.');
//                return false;
//            }

            foreach ($this->postPhotos as $postPhoto) {
//                $postPhoto->setItemAccessValidator($this->itemAccessValidator);
                $postPhoto->delete();
            }
            return true;
        } else {
            return false;
        }
    }

//    public function beforeSave($insert): bool
//    {
//        if (!$this->itemAccessValidator->hasUserAccessToRepoById($this->repoId, $insert ? RepoUser::ACCESS_CREATE_ITEMS : RepoUser::ACCESS_EDIT_ITEMS)) {
//            $this->addError('', 'Недостаточно прав для сохранения предмета.');
//            return false;
//        }
//
//        return parent::beforeSave($insert);
//    }

//    public function setItemAccessValidator(ItemAccessValidator $itemAccessValidator): static
//    {
//        $this->itemAccessValidator = $itemAccessValidator;
//        return $this;
//    }

    public function getItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'itemId']);
    }

    public function getPostPhotos(): ActiveQuery
    {
        return $this->hasMany(PostPhoto::class, ['postId' => 'id'])->orderBy(['sortIndex' => SORT_ASC]);
    }

    public function getPrimaryPhoto(): ActiveQuery
    {
        return $this->hasOne(PostPhoto::class, ['postId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->limit(1);
    }

    public function getSecondaryPhotos(): ActiveQuery
    {
        return $this->hasMany(PostPhoto::class, ['postId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->offset(1);
    }

    public function getCreatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'createdBy']);
    }

    public function getUpdatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'updatedBy']);
    }

    /**
     * @inheritdoc
     * @return PostQuery the active query used by this AR class.
     */
    public static function find(): PostQuery
    {
        return new PostQuery(get_called_class());
    }
}
