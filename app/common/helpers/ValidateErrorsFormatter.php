<?php

namespace common\helpers;

use yii\base\Model;

/**
 * Вспомогательный хэлпер-форматтер, позволяющий в случае возникновения ошибки валидации модели (формы) сгенерировать
 * красивое и информативное сообщение об ошибке со списком ошибок и списком значений атрибутов.
 *
 * Пример использования:
 *
 * ```
 * if (! $item->save()) {
 *     throw new Exception(ValidateErrorsFormatter::getMessage($item));
 * }
 * ```
 *
 * или
 *
 * ```
 * if (! $item->validate()) {
 *     throw new Exception(ValidateErrorsFormatter::getMessage($item));
 * }
 * ```
 *
 * @package common\helpers
 */
class ValidateErrorsFormatter
{
    /**
     * Возвращает унифицированный текст сообщения для исключения при ошибке валидации модели, например, при сохранении
     *
     * @param Model $model              Модель (форма), при валидации которой возникли ошибки
     * @return string                   Сообщение об ошибки для подстановки Exception
     */
    public static function getMessage(Model $model, $text = '%MODEL% validated with errors:'): string
    {
        return str_replace(['%MODEL%'], [get_class($model)], $text)
            . "\n" . self::allErrors($model)
            . "\nForm data:\n" . self::allFields($model);
    }

    /**
     * Возвращает все ошибки из модели (формы), появившиеся после неуспешной валидации, склеенные в строку
     *
     * @param Model $model              Модель (форма), из которой нужно извлечь ошибки
     * @param string $prefix            OPTIONAL Префикс элемента списка
     * @param string $suffix            OPTIONAL Суффикс элемента списка
     * @return string                   Строка со списком ошибок
     */
    public static function allErrors(Model $model, string $prefix = ' * ', string $suffix = "\n"): string
    {
        $errors = [];
        foreach ($model->getErrors() as $attribute => $attributeErrors) {
            foreach ($attributeErrors as $error) {
                $errors[] = "{$prefix}{$attribute}: {$error}{$suffix}";
            }
        }
        return implode('', $errors);
    }

    /**
     * Возвращает все первые ошибки из модели (формы), появившиеся после неуспешной валидации, склееные в строку
     *
     * @param Model $model              Модель (форма), из которой нужно извлечь ошибки
     * @param string $prefix            OPTIONAL Префикс элемента списка
     * @param string $suffix            OPTIONAL Суффикс элемента списка
     * @return string                   Строка со списком ошибок
     */
    public static function firstErrors(Model $model, string $prefix = ' * ', string $suffix = "\n"): string
    {
        $errors = [];
        foreach ($model->getFirstErrors() as $attribute => $error) {
            $errors[] = "{$prefix}{$attribute}: {$error}{$suffix}";
        }
        return implode('', $errors);
    }

    /**
     * Возвращает первую ошибку валидации в заданном формате
     *
     * @param Model $model              Модель (форма), из которой нужно извлечь ошибки
     * @param string $text              OPTIONAL Шаблон ошибки
     * @return string                   Строка с первой ошибкой валидации
     */
    public static function firstError(Model $model, string $text = '%FIELD%: %ERROR%'): string
    {
        $firstErrors = $model->getFirstErrors();
        return str_replace(['%MODEL%', '%FIELD%', '%ERROR%'], [get_class($model), key($firstErrors), current($firstErrors)], $text);
    }

    /**
     * @param Model $model              Модель (форма), из которой нужно извлечь атрибуты
     * @param string $prefix            OPTIONAL Префикс элемента списка
     * @param string $suffix            OPTIONAL Суффикс элемента списка
     * @return string                   Строка со списком полей и их значений
     */
    public static function allFields(Model $model, string $prefix = ' * ', string $suffix = "\n"): string
    {
        $fields = [];
        foreach ($model->attributes as $attribute => $value) {
            $fields[] = "{$prefix}{$attribute}: \"{$value}\"{$suffix}";
        }
        return implode('', $fields);
    }
}
