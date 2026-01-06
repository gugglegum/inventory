<?php

namespace common\models;

use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * Фотография поста
 *
 * @property int $id
 * @property int $postId
 * @property int $photoId
 * @property int $sortIndex
 *
 * @property Post $post
 * @property Photo $photo
 */
class PostPhoto extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'post_photo';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['postId', 'photoId'], 'required'],
            [['postId', 'photoId', 'sortIndex'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID фотографии поста',
            'postId' => 'ID поста',
            'photoId' => 'ID фотографии',
            'sortIndex' => 'Порядковый номер',
        ];
    }

    public function beforeSave($insert): bool
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $maxSortIndex = new Query()
                    ->select('MAX(sortIndex)')
                    ->from(self::tableName())
                    ->where('postId = :postId', ['postId' => $this->postId])
                    ->scalar();
                $this->sortIndex = $maxSortIndex !== null ? $maxSortIndex + 1 : 0;
            }
            return true;
        } else {
            return false;
        }
    }

    public function getPost(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Post::class, ['id' => 'postId']);
    }

    public function getPhoto(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Photo::class, ['id' => 'photoId']);
    }

    /**
     * @inheritdoc
     * @return PostPhotoQuery the active query used by this AR class.
     */
    public static function find(): PostPhotoQuery
    {
        return new PostPhotoQuery(get_called_class());
    }
}
