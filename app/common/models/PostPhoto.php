<?php

namespace common\models;

use yii\db\ActiveRecord;
use yii\db\Query;
use yii\db\StaleObjectException;

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

    public function transactions(): array
    {
        // Чтобы delete() автоматически был в транзакции
        return [
            self::SCENARIO_DEFAULT => self::OP_DELETE,
        ];
    }

    /**
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function afterDelete(): void
    {
        parent::afterDelete();

        // Если Photo может быть привязано к нескольким PostPhoto — не удаляем.
        // (После удаления текущей строки проверяем, остались ли ещё ссылки)
        if (static::find()->where(['photoId' => $this->photoId])->exists()) {
            return;
        }

        $photo = Photo::findOne($this->photoId);
        if ($photo !== null) {
            if ($photo->delete() === false) {
                // Нужно бросить исключение, чтобы внешняя транзакция откатилась.
                throw new \RuntimeException('Не удалось удалить Photo');
            }
        }
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
//            $postPhoto->setItemAccessValidator($this->itemAccessValidator);
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
