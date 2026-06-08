<?php

declare(strict_types=1);

namespace backend\services;

use common\models\Item;
use common\models\User as Identity;
use yii\web\User;

/**
 * Удаляет предмет выбранным пользователем способом.
 *
 * Сервис отделяет бизнес-операцию удаления от формы подтверждения и HTTP-логики ItemsController.
 * Мягкое удаление заполняет deleted/deletedBy, жесткое удаление физически удаляет запись.
 */
final class ItemDeletionService
{
    /**
     * Удаляет предмет мягко или жестко.
     *
     * @param Item $item Удаляемый предмет.
     * @param bool $hardDelete True для полного удаления из базы, false для мягкого удаления.
     * @param User<Identity> $user Пользователь, выполняющий удаление.
     *
     * @throws \yii\db\Exception
     * @throws \Throwable
     */
    public function delete(Item $item, bool $hardDelete, User $user): ItemDeletionResult
    {
        if ($hardDelete) {
            if ($item->delete() === false) {
                return ItemDeletionResult::failure(
                    'Ошибка при полном (жёстком) удалении предмета' . $this->formatModelError($item)
                );
            }

            return ItemDeletionResult::success();
        }

        $userId = $user->getId();
        if ($item->softDelete($userId !== null ? (int) $userId : null) === false) {
            return ItemDeletionResult::failure(
                'Ошибка при мягком удалении предмета' . $this->formatModelError($item)
            );
        }

        return ItemDeletionResult::success();
    }

    /**
     * Возвращает первую ошибку модели в формате, совместимом со старым сообщением формы.
     */
    private function formatModelError(Item $item): string
    {
        $message = $item->getFirstError('');

        return $message ? ': ' . $message : '';
    }
}
