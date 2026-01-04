<?php

namespace common\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Доступ пользователей к репозиториям.
 *
 * @property integer $repoId
 * @property integer $userId
 * @property integer $access
 *
 * @property User $user
 * @property Repo $repo
 */
class RepoUser extends ActiveRecord
{
    /** @var int Право только на чтение ко всему */
    public const int ACCESS_READONLY = 0;
    /** @var int Право на создание новых предметов */
    public const int ACCESS_CREATE_ITEMS = 1;
    /** @var int Право редактирования предметов */
    public const int ACCESS_EDIT_ITEMS = 2;
    /** @var int Право удаления предметов */
    public const int ACCESS_DELETE_ITEMS = 4;
    /** @var int Право редактирования репозитория */
    public const int ACCESS_EDIT_REPO = 16384;
    /** @var int Право удаления репозитория */
    public const int ACCESS_DELETE_REPO = 32768;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'repo_user';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['repoId', 'userId', 'access'], 'required'],
            [['repoId', 'userId', 'access'], 'integer'],
            [['userId'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['userId' => 'id']],
            [['repoId'], 'exist', 'skipOnError' => true, 'targetClass' => Repo::class, 'targetAttribute' => ['repoId' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'repoId' => 'Repo ID',
            'userId' => 'User ID',
            'access' => 'Access',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRepo(): ActiveQuery
    {
        return $this->hasOne(Repo::class, ['id' => 'repoId']);
    }

    /**
     * @inheritdoc
     * @return RepoUserQuery the active query used by this AR class.
     */
    public static function find(): RepoUserQuery
    {
        return new RepoUserQuery(get_called_class());
    }
}
