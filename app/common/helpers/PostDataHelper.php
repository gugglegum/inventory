<?php

declare(strict_types=1);

namespace common\helpers;

/**
 * Нормализует сырые POST-данные перед передачей в Yii forms/models.
 */
final class PostDataHelper
{
    /**
     * Возвращает POST-данные как массив, пригодный для Model::load().
     *
     * @param mixed $postData Сырые данные из request->post().
     * @return array<array-key, mixed>
     */
    public static function toArray(mixed $postData): array
    {
        if (is_array($postData)) {
            return $postData;
        }

        return [];
    }
}
