<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/**
 * Инвентаризация
 *
 * Представляет собой процесс, проводимый для какого-то предмета-контейнера, имеющий начало и конец, в ходе которого
 * подтверждается наличие каких-то предметов внутри него.
 *
 * @property int $id ID инвентаризации
 * @property int $containerId ID контейнера, в котором проводится инвентаризация
 * @property int $status Статус инвентаризации
 * @property int $createdBy ID начавшего инвентаризацию пользователя
 * @property ?int $closedBy ID закрывшего инвентаризацию пользователя
 * @property int $created Время начала инвентаризации
 * @property ?int $closed Время закрытия инвентаризации
 *
 * @property-read Item $container Контейнер, в котором проводится инвентаризация
 * @property-read InventoryItem[] $inventoryItems Предметы, подтверждённые в рамках инвентаризации
 * @property User $createdByUser Пользователь, начавший инвентаризацию
 * @property User $closedByUser Пользователь, закрывший инвентаризацию
 */
final class Inventory extends ActiveRecord
{
    public const int STATUS_OPENED = 0;
    public const int STATUS_CLOSED = 1;

    public static function tableName(): string
    {
        return '{{%inventory}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created',
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created'],
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['containerId', 'createdBy'], 'required'],
            [['containerId', 'created', 'createdBy'], 'integer'],

            // FK existence checks
            [['containerId'], 'exist', 'skipOnError' => true, 'targetClass' => Item::class, 'targetAttribute' => ['containerId' => 'id']],
            [['createdBy'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['createdBy' => 'id']],
        ];
    }

    public function getContainer(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'containerId']);
    }

    public function getInventoryItems(): ActiveQuery
    {
        return $this->hasMany(InventoryItem::class, ['inventoryId' => 'id']);
    }

    public function getCreatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'createdBy']);
    }

    public function getClosedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'closedBy']);
    }
}
