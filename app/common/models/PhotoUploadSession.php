<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/**
 * Сессия асинхронной загрузки фотографий формы.
 *
 * @property int $id
 * @property string $token
 * @property int $userId
 * @property int $repoId
 * @property string $context
 * @property int $expiresAt
 * @property ?int $consumedAt
 * @property int $created
 * @property ?int $updated
 *
 * @property User $user
 * @property Repo $repo
 * @property PhotoUploadFile[] $files
 */
final class PhotoUploadSession extends ActiveRecord
{
    public const string CONTEXT_ITEM_CREATE = 'item-create';
    public const string CONTEXT_ITEM_UPDATE = 'item-update';
    public const string CONTEXT_POST = 'post';

    public static function tableName(): string
    {
        return 'photo_upload_session';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created',
                'updatedAtAttribute' => 'updated',
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created'],
                    BaseActiveRecord::EVENT_BEFORE_UPDATE => ['updated'],
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['token', 'userId', 'repoId', 'context', 'expiresAt'], 'required'],
            [['userId', 'repoId', 'expiresAt', 'consumedAt'], 'integer'],
            ['token', 'string', 'length' => 64],
            ['token', 'match', 'pattern' => '/\A[0-9a-f]{64}\z/D'],
            ['context', 'in', 'range' => self::contexts()],
            ['userId', 'exist', 'targetClass' => User::class, 'targetAttribute' => ['userId' => 'id']],
            ['repoId', 'exist', 'targetClass' => Repo::class, 'targetAttribute' => ['repoId' => 'id']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function contexts(): array
    {
        return [
            self::CONTEXT_ITEM_CREATE,
            self::CONTEXT_ITEM_UPDATE,
            self::CONTEXT_POST,
        ];
    }

    public function isOpen(?int $now = null): bool
    {
        return $this->consumedAt === null && (int) $this->expiresAt > ($now ?? time());
    }

    /**
     * Возвращает минимальное право, необходимое для работы с сессией.
     *
     * Заметки исторически доступны любому участнику репозитория, поэтому для
     * CONTEXT_POST достаточно самого наличия RepoUser (нулевая маска).
     */
    public function getRequiredRepoAccess(): int
    {
        return self::requiredRepoAccessForContext((string) $this->context);
    }

    public static function requiredRepoAccessForContext(string $context): int
    {
        return match ($context) {
            self::CONTEXT_ITEM_CREATE => RepoUser::ACCESS_CREATE_ITEMS,
            self::CONTEXT_ITEM_UPDATE => RepoUser::ACCESS_EDIT_ITEMS,
            self::CONTEXT_POST => RepoUser::ACCESS_READONLY,
            default => throw new \InvalidArgumentException("Неизвестный контекст загрузки: {$context}."),
        };
    }

    /** @return ActiveQuery<User> */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /** @return ActiveQuery<Repo> */
    public function getRepo(): ActiveQuery
    {
        return $this->hasOne(Repo::class, ['id' => 'repoId']);
    }

    /** @return ActiveQuery<PhotoUploadFile> */
    public function getFiles(): ActiveQuery
    {
        return $this->hasMany(PhotoUploadFile::class, ['sessionId' => 'id'])->orderBy(['id' => SORT_ASC]);
    }
}
