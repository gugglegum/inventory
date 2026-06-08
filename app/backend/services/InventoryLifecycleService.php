<?php

declare(strict_types=1);

namespace backend\services;

use common\helpers\ValidateErrorsFormatter;
use common\models\Inventory;
use common\models\Item;
use yii\base\Exception;
use yii\db\StaleObjectException;
use yii\web\User;

/**
 * Управляет жизненным циклом инвентаризации: открытием и удалением.
 *
 * Сервис содержит мутации самой модели Inventory, чтобы контроллер оставался HTTP-обвязкой
 * и не занимался созданием/удалением доменных записей напрямую.
 */
final class InventoryLifecycleService
{
    /**
     * Открывает новую инвентаризацию для контейнера.
     *
     * @param Item $container Контейнер, внутри которого проводится инвентаризация.
     * @param User $user Пользователь, начавший инвентаризацию.
     *
     * @throws Exception
     */
    public function open(Item $container, User $user): Inventory
    {
        $inventory = new Inventory([
            'containerId' => $container->id,
            'status' => Inventory::STATUS_OPENED,
            'createdBy' => (int) $user->id,
        ]);

        if (!$inventory->save()) {
            throw new Exception(ValidateErrorsFormatter::getMessage($inventory));
        }

        return $inventory;
    }

    /**
     * Удаляет инвентаризацию.
     *
     * @throws Exception
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function delete(Inventory $inventory): void
    {
        if ($inventory->delete() === false) {
            throw new Exception('Failed to delete inventory');
        }
    }
}
