<?php

declare(strict_types=1);

namespace backend\models;

use yii\base\Model;

/**
 * Форма выбора режима удаления предмета.
 *
 * Хранит пользовательский флаг hardDelete и ошибки, которые сервис удаления может показать
 * в errorSummary на странице подтверждения.
 */
final class ItemDeleteForm extends Model
{
    /**
     * Полностью удалить предмет из базы вместо мягкого удаления.
     */
    public bool $hardDelete = false;

    /**
     * Названия полей формы.
     */
    public function attributeLabels(): array
    {
        return [
            'hardDelete' => 'Полное удаление без возможности восстановления',
        ];
    }

    /**
     * Правила валидации формы удаления.
     */
    public function rules(): array
    {
        return [
            ['hardDelete', 'boolean'],
            ['hardDelete', 'default', 'value' => false],
        ];
    }
}
