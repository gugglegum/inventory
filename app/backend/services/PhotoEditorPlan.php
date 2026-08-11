<?php

declare(strict_types=1);

namespace backend\services;

use common\models\ItemPhoto;
use common\models\PhotoUploadFile;
use common\models\PhotoUploadSession;
use common\models\PostPhoto;

/**
 * Проверенный снимок изменений списка фотографий.
 *
 * Экземпляр создается до сохранения основной формы и применяется внутри той же
 * транзакции, чтобы временные upload-записи не могли быть очищены между
 * проверкой и привязкой.
 */
final class PhotoEditorPlan
{
    /**
     * @param list<array{type:'existing'|'temporary', id:int}> $entries
     * @param array<int, ItemPhoto|PostPhoto> $existingAttachments
     * @param array<int, PhotoUploadFile> $temporaryFiles
     */
    public function __construct(
        public readonly array $entries,
        public readonly array $existingAttachments,
        public readonly array $temporaryFiles,
        public readonly ?PhotoUploadSession $session,
    ) {
    }
}
