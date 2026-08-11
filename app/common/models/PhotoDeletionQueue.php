<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/**
 * Явный marker Photo, отсоединенной от формы и ожидающей удаления файла.
 *
 * Строка создается в одной транзакции с удалением ItemPhoto/PostPhoto, поэтому
 * rollback не может оставить восстановленную связь без физического JPEG.
 *
 * @property int $id
 * @property int $photoId
 * @property int $created
 * @property ?Photo $photo
 */
final class PhotoDeletionQueue extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'photo_deletion_queue';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created',
                'updatedAtAttribute' => false,
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created'],
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['photoId'], 'required'],
            [['photoId'], 'integer'],
            ['photoId', 'exist', 'targetClass' => Photo::class, 'targetAttribute' => ['photoId' => 'id']],
        ];
    }

    /** @return ActiveQuery<Photo> */
    public function getPhoto(): ActiveQuery
    {
        return $this->hasOne(Photo::class, ['id' => 'photoId']);
    }
}
