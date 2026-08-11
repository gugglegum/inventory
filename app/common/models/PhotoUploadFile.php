<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/**
 * Явный marker фотографии, которая создана асинхронно, но еще не применена формой.
 *
 * Пока marker существует, Photo считается временной и может быть удалена cleanup-командой.
 *
 * @property int $id
 * @property int $sessionId
 * @property int $photoId
 * @property string $originalName
 * @property int $created
 *
 * @property ?PhotoUploadSession $session
 * @property ?Photo $photo
 */
final class PhotoUploadFile extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'photo_upload_file';
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
            [['sessionId', 'photoId', 'originalName'], 'required'],
            [['sessionId', 'photoId'], 'integer'],
            ['originalName', 'string', 'max' => 255],
            ['sessionId', 'exist', 'targetClass' => PhotoUploadSession::class, 'targetAttribute' => ['sessionId' => 'id']],
            ['photoId', 'exist', 'targetClass' => Photo::class, 'targetAttribute' => ['photoId' => 'id']],
        ];
    }

    /** @return ActiveQuery<PhotoUploadSession> */
    public function getSession(): ActiveQuery
    {
        return $this->hasOne(PhotoUploadSession::class, ['id' => 'sessionId']);
    }

    /** @return ActiveQuery<Photo> */
    public function getPhoto(): ActiveQuery
    {
        return $this->hasOne(Photo::class, ['id' => 'photoId']);
    }
}
