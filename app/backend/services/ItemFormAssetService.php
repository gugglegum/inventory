<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\ItemTagsForm;
use common\helpers\ValidateErrorsFormatter;
use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use yii\base\Exception;

/**
 * Сохраняет связанные данные формы предмета после успешного сохранения Item.
 *
 * Сервис отделяет сохранение тегов и новых фотографий от HTTP-логики ItemsController::actionCreate()
 * и ItemsController::actionUpdate().
 */
final class ItemFormAssetService
{
    /**
     * Сохраняет теги и прикрепляет новые загруженные фотографии к предмету.
     *
     * @param Item $item Предмет, к которому относятся теги и фотографии.
     * @param ItemTagsForm $tagsForm Форма тегов, загружаемая из POST.
     * @param array $postData POST-данные текущего запроса.
     * @param array $filesData FILES-данные текущего запроса.
     *
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function save(Item $item, ItemTagsForm $tagsForm, array $postData, array $filesData): void
    {
        $this->saveTags($item, $tagsForm, $postData);
        $this->attachPhotos($item, $filesData['photos']['tmp_name'] ?? []);
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

    /**
     * Создает Photo и ItemPhoto для каждого реально загруженного файла.
     *
     * @param mixed $tmpNames Значение `$_FILES['photos']['tmp_name']`; ожидается массив путей.
     *
     * @throws Exception
     */
    private function attachPhotos(Item $item, mixed $tmpNames): void
    {
        if (!is_array($tmpNames)) {
            return;
        }

        foreach ($tmpNames as $tmpName) {
            if (!is_string($tmpName) || $tmpName === '') {
                continue;
            }

            $photo = new Photo();
            $photo->assignFile($tmpName);
            if (!$photo->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($photo));
            }

            $itemPhoto = new ItemPhoto([
                'itemId' => $item->id,
                'photoId' => $photo->id,
            ]);
            if (!$itemPhoto->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($itemPhoto));
            }
        }
    }
}
