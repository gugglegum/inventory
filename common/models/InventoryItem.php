<?php

declare(strict_types=1);

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;

/**
 * Предмет в инвентаризации
 *
 * По сути это объект, связующий инвентаризацию и предмет.
 *
 * @property int $id
 * @property int $inventoryId
 * @property int $itemId
 * @property int $createdBy ID создавшего запись пользователя
 * @property int $created
 *
 * @property-read Inventory $inventory
 * @property-read Item $item
 * @property User $createdByUser
 */
class InventoryItem extends ActiveRecord
{
    public const string SCENARIO_CONFIRM = 'confirm';
    public const string SCENARIO_UNCONFIRM = 'unconfirm';

    public static function tableName(): string
    {
        return '{{%inventory_item}}';
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
            [['inventoryId', 'itemId', 'createdBy'], 'required'],
            [['inventoryId', 'itemId', 'created', 'createdBy'], 'integer'],

            // FK existence checks
            [['inventoryId'], 'exist', 'skipOnError' => true, 'targetClass' => Inventory::class, 'targetAttribute' => ['inventoryId' => 'id']],
            [['itemId'], 'exist', 'skipOnError' => true, 'targetClass' => Item::class, 'targetAttribute' => ['itemId' => 'id']],
            [['createdBy'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['createdBy' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios(): array
    {
        $scenarios = parent::scenarios();

        $scenarios[self::SCENARIO_CONFIRM] = ['itemId'];
        $scenarios[self::SCENARIO_UNCONFIRM] = ['itemId'];

        return $scenarios;
    }

    public function getInventory(): ActiveQuery
    {
        return $this->hasOne(Inventory::class, ['id' => 'inventoryId']);
    }

    public function getItem(): ActiveQuery
    {
        return $this->hasOne(Item::class, ['id' => 'itemId']);
    }

    public function getCreatedByUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'createdBy']);
    }
}
