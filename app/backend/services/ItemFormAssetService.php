<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\ItemTagsForm;
use common\models\Item;

/**
 * Сохраняет связанные текстовые данные формы предмета после успешного сохранения Item.
 *
 * Сервис отделяет сохранение тегов от HTTP-логики ItemsController::actionCreate()
 * и ItemsController::actionUpdate(). Фотографии применяет PhotoEditorService.
 */
final class ItemFormAssetService
{
    /**
     * Сохраняет теги предмета.
     *
     * @param Item $item Предмет, к которому относятся теги.
     * @param ItemTagsForm $tagsForm Форма тегов, загружаемая из POST.
     * @param array $postData POST-данные текущего запроса.
     * @throws \yii\db\Exception
     */
    public function save(Item $item, ItemTagsForm $tagsForm, array $postData): void
    {
        $this->saveTags($item, $tagsForm, $postData);
    }

    /**
     * Сохраняет строку тегов, если форма тегов была отправлена.
     *
     * @throws \yii\db\Exception
     */
    private function saveTags(Item $item, ItemTagsForm $tagsForm, array $postData): void
    {
        if ($tagsForm->load($postData)) {
            $item->saveTagsFromString($tagsForm->tags);
        }
    }
}
