<?php

namespace common\models;

use yii\db\ActiveRecord;
use yii\db\Query;
use yii\db\StaleObjectException;

/**
 * Фотография предмета
 *
 * @property int $id
 * @property int $itemId
 * @property int $photoId
 * @property int $sortIndex
 *
 * @property Item $item
 * @property Photo $photo
 */
class ItemPhoto extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'item_photo';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['itemId', 'photoId'], 'required'],
            [['itemId', 'photoId', 'sortIndex'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID фотографии предмета',
            'itemId' => 'ID предмета',
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
                    ->where('itemId = :itemId', ['itemId' => $this->itemId])
                    ->scalar();
                $this->sortIndex = $maxSortIndex !== null ? $maxSortIndex + 1 : 0;
            }
            return true;
        } else {
            return false;
        }
    }

    public function getItem(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'itemId']);
    }

    public function getPhoto(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Photo::class, ['id' => 'photoId']);
    }

    /**
     * @inheritdoc
     * @return ItemPhotoQuery the active query used by this AR class.
     */
    public static function find(): ItemPhotoQuery
    {
        return new ItemPhotoQuery(get_called_class());
    }
}
