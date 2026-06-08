<?php

declare(strict_types=1);

namespace backend\services;

use common\helpers\ValidateErrorsFormatter;
use common\models\ItemPhoto;
use common\models\PostPhoto;
use InvalidArgumentException;
use yii\base\Exception;
use yii\db\StaleObjectException;

/**
 * Управляет связями фотографий с предметами и заметками.
 *
 * Сервис отделяет сортировку и удаление `ItemPhoto`/`PostPhoto` от HTTP-логики PhotoController.
 */
final class PhotoAttachmentService
{
    /**
     * Тип связи фотографии с предметом.
     */
    public const string TYPE_ITEM = 'item';

    /**
     * Тип связи фотографии с заметкой.
     */
    public const string TYPE_POST = 'post';

    /**
     * Перемещает фотографию на одну позицию вверх внутри ее списка.
     *
     * @throws Exception
     */
    public function sortUp(int $id, string $type = self::TYPE_ITEM): bool
    {
        $attachment = $this->findAttachment($id, $type);
        if ($attachment === null) {
            return false;
        }

        $previousAttachment = $this->findSibling($attachment, $type, 'up');
        if ($previousAttachment !== null) {
            $this->swapSortIndexes($attachment, $previousAttachment);
        }

        return true;
    }

    /**
     * Перемещает фотографию на одну позицию вниз внутри ее списка.
     *
     * @throws Exception
     */
    public function sortDown(int $id, string $type = self::TYPE_ITEM): bool
    {
        $attachment = $this->findAttachment($id, $type);
        if ($attachment === null) {
            return false;
        }

        $nextAttachment = $this->findSibling($attachment, $type, 'down');
        if ($nextAttachment !== null) {
            $this->swapSortIndexes($attachment, $nextAttachment);
        }

        return true;
    }

    /**
     * Удаляет связь фотографии с предметом или заметкой.
     *
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function delete(int $id, string $type = self::TYPE_ITEM): bool
    {
        $attachment = $this->findAttachment($id, $type);
        if ($attachment === null) {
            return false;
        }

        return $attachment->delete() !== false;
    }

    /**
     * Ищет связь фотографии нужного типа.
     */
    private function findAttachment(int $id, string $type): ItemPhoto|PostPhoto|null
    {
        $class = $this->getAttachmentClass($type);

        /** @var ItemPhoto|PostPhoto|null */
        return $class::findOne($id);
    }

    /**
     * Находит соседнюю связь фотографии в том же списке.
     *
     * @param ItemPhoto|PostPhoto $attachment Текущая связь фотографии.
     * @param string $type Тип связи фотографии.
     * @param 'up'|'down' $direction Направление перемещения.
     */
    private function findSibling(ItemPhoto|PostPhoto $attachment, string $type, string $direction): ItemPhoto|PostPhoto|null
    {
        $class = $this->getAttachmentClass($type);
        $ownerColumn = $this->getOwnerColumn($type);
        $operator = $direction === 'up' ? '<' : '>';
        $sortDirection = $direction === 'up' ? SORT_DESC : SORT_ASC;

        /** @var ItemPhoto|PostPhoto|null */
        return $class::find()
            ->where([$ownerColumn => $attachment->{$ownerColumn}])
            ->andWhere([$operator, 'sortIndex', $attachment->sortIndex])
            ->orderBy(['sortIndex' => $sortDirection])
            ->limit(1)
            ->one();
    }

    /**
     * Меняет местами sortIndex двух соседних связей фотографии.
     *
     * @throws Exception
     */
    private function swapSortIndexes(ItemPhoto|PostPhoto $attachment1, ItemPhoto|PostPhoto $attachment2): void
    {
        $transaction = $attachment1::getDb()->beginTransaction();

        try {
            $previousSortIndex = $attachment2->sortIndex;
            $attachment2->sortIndex = -1;
            $this->saveAttachment($attachment2);

            $attachment2->sortIndex = $attachment1->sortIndex;
            $attachment1->sortIndex = $previousSortIndex;
            $this->saveAttachment($attachment1);
            $this->saveAttachment($attachment2);

            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Сохраняет связь фотографии и бросает читаемое исключение при ошибке валидации.
     *
     * @throws Exception
     */
    private function saveAttachment(ItemPhoto|PostPhoto $attachment): void
    {
        if (!$attachment->save()) {
            throw new Exception(ValidateErrorsFormatter::getMessage($attachment));
        }
    }

    /**
     * Возвращает класс связи фотографии.
     *
     * @return class-string<ItemPhoto|PostPhoto>
     */
    private function getAttachmentClass(string $type): string
    {
        return match ($type) {
            self::TYPE_ITEM => ItemPhoto::class,
            self::TYPE_POST => PostPhoto::class,
            default => throw new InvalidArgumentException("Unknown photo attachment type: {$type}"),
        };
    }

    /**
     * Возвращает поле владельца списка фотографий.
     */
    private function getOwnerColumn(string $type): string
    {
        return match ($type) {
            self::TYPE_ITEM => 'itemId',
            self::TYPE_POST => 'postId',
            default => throw new InvalidArgumentException("Unknown photo attachment type: {$type}"),
        };
    }
}
