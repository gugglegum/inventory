<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

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
 * @property User $createdByUser
 * @property ?User $updatedByUser
 */
class Post extends ActiveRecord
{
    public const string SCENARIO_CREATE = 'create';
    public const string SCENARIO_UPDATE = 'update';

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

        $scenarios[self::SCENARIO_CREATE] = ['datetime', 'title', 'text'];
        $scenarios[self::SCENARIO_UPDATE] = ['datetime', 'title', 'text'];

        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['datetime', 'title'], 'required'],
            [['datetime'], 'integer'],
            [['title', 'text'], 'string'],
            [['title', 'text'], 'filter', 'filter' => 'trim'],
            [['title'], 'string', 'max' => 200],
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
            'title' => 'Заголовок поста',
            'text' => 'Текст поста',
            'createdBy' => 'ID создавшего пост пользователя',
            'updatedBy' => 'ID последнего изменившего пост пользователя',
            'created' => 'Время создания',
            'updated' => 'Время последнего изменения',
        ];
    }

    /**
     * @return bool
     * @throws \Throwable
     */
    public function beforeDelete(): bool
    {
        if (parent::beforeDelete()) {
            foreach ($this->postPhotos as $postPhoto) {
                $postPhoto->delete();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return ActiveQuery<Item>
     */
    public function getItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'itemId']);
    }

    /**
     * @return ActiveQuery<PostPhoto>
     */
    public function getPostPhotos(): ActiveQuery
    {
        return $this->hasMany(PostPhoto::class, ['postId' => 'id'])->orderBy(['sortIndex' => SORT_ASC]);
    }

    /**
     * @return ActiveQuery<PostPhoto>
     */
    public function getPrimaryPhoto(): ActiveQuery
    {
        return $this->hasOne(PostPhoto::class, ['postId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->limit(1);
    }

    /**
     * @return ActiveQuery<PostPhoto>
     */
    public function getSecondaryPhotos(): ActiveQuery
    {
        return $this->hasMany(PostPhoto::class, ['postId' => 'id'])->orderBy(['sortIndex' => SORT_ASC])->offset(1);
    }

    /**
     * @return ActiveQuery<User>
     */
    public function getCreatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'createdBy']);
    }

    /**
     * @return ActiveQuery<User>
     */
    public function getUpdatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'updatedBy']);
    }

    /**
     * @inheritdoc
     * @return PostQuery<static> the active query used by this AR class.
     */
    public static function find(): PostQuery
    {
        /** @var PostQuery<static> $query */
        $query = new PostQuery(static::class);
        return $query;
    }
}
