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
     * Строковый флаг полного удаления из POST checkbox.
     */
    public string $hardDelete = '0';

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
            ['hardDelete', 'default', 'value' => '0'],
        ];
    }

    /**
     * Возвращает true, если пользователь выбрал полное удаление.
     */
    public function isHardDelete(): bool
    {
        return $this->hardDelete !== '' && $this->hardDelete !== '0';
    }
}
