<?php

declare(strict_types=1);

namespace common\services;

use common\components\ItemAccessValidator;
use common\models\Item;
use common\models\RepoUser;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 * Выполняет каскадные операции удаления предмета.
 *
 * Сервис отделяет обход дочерних предметов, фотографий и заметок от ActiveRecord hooks модели Item.
 * Сами hooks остаются точками входа, чтобы существующие вызовы Item::delete() и Item::softDelete()
 * сохраняли прежнее поведение.
 */
final class ItemDeletionCascadeService
{
    /**
     * Мягко удаляет предмет и его дочерние предметы.
     *
     * @throws Exception
     */
    public function softDelete(Item $item, ?int $userId, ItemAccessValidator $itemAccessValidator): bool
    {
        if ($item->deleted !== null) {
            return true;
        }

        $transaction = Item::getDb()->beginTransaction();
        try {
            if (!$this->beforeSoftDelete($item, $userId, $itemAccessValidator)) {
                $transaction->rollBack();
                return false;
            }

            $now = time();

            Item::getDb()->createCommand()->update(
                Item::tableName(),
                [
                    'deleted' => $now,
                    'deletedBy' => $userId,
                ],
                ['and', ['id' => $item->id], ['deleted' => null]]
            )->execute();

            $item->refresh();
            $transaction->commit();

            return true;
        } catch (Exception $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            $item->addError('', 'Ошибка при удалении предмета: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Проверяет права и мягко удаляет дочерние предметы перед мягким удалением текущего.
     */
    public function beforeSoftDelete(Item $item, ?int $userId, ItemAccessValidator $itemAccessValidator): bool
    {
        if (!$itemAccessValidator->hasUserAccessToRepoById($item->repoId, RepoUser::ACCESS_DELETE_ITEMS)) {
            $item->addError('', 'Недостаточно прав для удаления предмета.');
            return false;
        }

        foreach ($item->items as $childItem) {
            $childItem->setItemAccessValidator($itemAccessValidator);
            $this->softDelete($childItem, $userId, $itemAccessValidator);
        }

        return true;
    }

    /**
     * Проверяет права и удаляет вложенные данные перед жестким удалением текущего предмета.
     *
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function beforeHardDelete(Item $item, ItemAccessValidator $itemAccessValidator): bool
    {
        if (!$itemAccessValidator->hasUserAccessToRepoById($item->repoId, RepoUser::ACCESS_DELETE_ITEMS)) {
            $item->addError('', 'Недостаточно прав для удаления предмета.');
            return false;
        }

        foreach ($item->items as $childItem) {
            $childItem->setItemAccessValidator($itemAccessValidator);
            $childItem->delete();
        }
        foreach ($item->itemPhotos as $itemPhoto) {
            $itemPhoto->delete();
        }
        foreach ($item->posts as $post) {
            $post->delete();
        }

        return true;
    }
}
