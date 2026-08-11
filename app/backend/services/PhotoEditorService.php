<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\PhotoEditorForm;
use common\helpers\ValidateErrorsFormatter;
use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\PhotoUploadFile;
use common\models\PhotoUploadSession;
use common\models\Post;
use common\models\PostPhoto;
use RuntimeException;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\helpers\Url;

/**
 * Проверяет и применяет единый ordered manifest фотографий предмета или заметки.
 */
final class PhotoEditorService
{
    private const int MAX_SORT_INDEX = 2147483647;

    private const int MIN_SORT_INDEX = -2147483648;

    /**
     * @return list<array{type:'existing'|'temporary', id:int, thumbnailUrl:string, previewUrl:string, name:string, deleteUrl?:string}>
     */
    public function viewEntriesForItem(PhotoEditorForm $form, Item $item, int $userId): array
    {
        $attachments = $item->isNewRecord
            ? []
            : array_values(
                ItemPhoto::find()->where(['itemId' => $item->id])->orderBy(['sortIndex' => SORT_ASC])->all()
            );
        $context = $item->isNewRecord
            ? PhotoUploadSession::CONTEXT_ITEM_CREATE
            : PhotoUploadSession::CONTEXT_ITEM_UPDATE;

        return $this->viewEntries($form, $attachments, (int) $item->repoId, $userId, $context);
    }

    /**
     * @return list<array{type:'existing'|'temporary', id:int, thumbnailUrl:string, previewUrl:string, name:string, deleteUrl?:string}>
     */
    public function viewEntriesForPost(PhotoEditorForm $form, Post $post, int $repoId, int $userId): array
    {
        $attachments = $post->isNewRecord
            ? []
            : array_values(
                PostPhoto::find()->where(['postId' => $post->id])->orderBy(['sortIndex' => SORT_ASC])->all()
            );

        return $this->viewEntries(
            $form,
            $attachments,
            $repoId,
            $userId,
            PhotoUploadSession::CONTEXT_POST,
        );
    }

    public function createFormForItem(Item $item): PhotoEditorForm
    {
        $attachments = $item->isNewRecord
            ? []
            : array_values(
                ItemPhoto::find()->where(['itemId' => $item->id])->orderBy(['sortIndex' => SORT_ASC])->all()
            );

        return $this->createForm($attachments);
    }

    public function createFormForPost(Post $post): PhotoEditorForm
    {
        $attachments = $post->isNewRecord
            ? []
            : array_values(
                PostPhoto::find()->where(['postId' => $post->id])->orderBy(['sortIndex' => SORT_ASC])->all()
            );

        return $this->createForm($attachments);
    }

    public function prepareForItem(PhotoEditorForm $form, Item $item, int $userId): ?PhotoEditorPlan
    {
        $context = $item->isNewRecord
            ? PhotoUploadSession::CONTEXT_ITEM_CREATE
            : PhotoUploadSession::CONTEXT_ITEM_UPDATE;
        $attachments = $item->isNewRecord
            ? []
            : $this->lockAndFindAttachments(ItemPhoto::class, 'itemId', (int) $item->id);

        return $this->prepare($form, $attachments, (int) $item->repoId, $userId, $context);
    }

    public function prepareForPost(PhotoEditorForm $form, Post $post, int $repoId, int $userId): ?PhotoEditorPlan
    {
        $attachments = $post->isNewRecord
            ? []
            : $this->lockAndFindAttachments(PostPhoto::class, 'postId', (int) $post->id);

        return $this->prepare(
            $form,
            $attachments,
            $repoId,
            $userId,
            PhotoUploadSession::CONTEXT_POST,
        );
    }

    /**
     * @return list<int> ID отсоединенных Photo для безопасной очистки после commit.
     * @throws Exception
     */
    public function applyToItem(PhotoEditorPlan $plan, Item $item): array
    {
        if ($item->isNewRecord) {
            throw new RuntimeException('Нельзя прикрепить фотографии к несохраненному предмету.');
        }

        return $this->apply($plan, ItemPhoto::class, 'itemId', (int) $item->id);
    }

    /**
     * @return list<int> ID отсоединенных Photo для безопасной очистки после commit.
     * @throws Exception
     */
    public function applyToPost(PhotoEditorPlan $plan, Post $post): array
    {
        if ($post->isNewRecord) {
            throw new RuntimeException('Нельзя прикрепить фотографии к несохраненной заметке.');
        }

        return $this->apply($plan, PostPhoto::class, 'postId', (int) $post->id);
    }

    /**
     * Удаляет физические файлы только после успешного commit основной формы.
     *
     * Ошибка cleanup не отменяет уже сохраненную форму: Photo остается в явной
     * очереди и ее повторно подберет следующий photo-uploads/prune.
     *
     * @param list<int> $photoIds
     */
    public function cleanupDetachedPhotos(array $photoIds): void
    {
        $service = new PhotoUploadService();
        foreach (array_values(array_unique($photoIds)) as $photoId) {
            try {
                $service->discardDetachedPhoto($photoId);
            } catch (Throwable $exception) {
                Yii::error(
                    "Не удалось удалить отсоединенную Photo {$photoId}: {$exception->getMessage()}",
                    __METHOD__
                );
            }
        }
    }

    /**
     * @param list<ItemPhoto|PostPhoto> $attachments
     */
    private function createForm(array $attachments): PhotoEditorForm
    {
        $entries = [];
        foreach ($attachments as $attachment) {
            $entries[] = [
                'type' => 'existing',
                'id' => (int) $attachment->id,
            ];
        }

        return new PhotoEditorForm($entries, $this->revision($attachments));
    }

    /**
     * @param list<ItemPhoto|PostPhoto> $attachments
     * @return list<array{type:'existing'|'temporary', id:int, thumbnailUrl:string, previewUrl:string, name:string, deleteUrl?:string}>
     */
    private function viewEntries(
        PhotoEditorForm $form,
        array $attachments,
        int $repoId,
        int $userId,
        string $context,
    ): array {
        $existingById = [];
        foreach ($attachments as $attachment) {
            $existingById[(int) $attachment->id] = $attachment;
        }

        $temporaryById = [];
        $session = null;
        if ($form->sessionToken !== '') {
            $candidate = (new PhotoUploadService())->findOwnedOpenSession($form->sessionToken, $userId);
            if (
                $candidate instanceof PhotoUploadSession
                && (int) $candidate->repoId === $repoId
                && hash_equals((string) $candidate->context, $context)
            ) {
                $session = $candidate;
                foreach ($session->files as $file) {
                    $temporaryById[(int) $file->id] = $file;
                }
            }
        }

        $result = [];
        foreach ($form->getEntries() as $entry) {
            if ($entry['type'] === 'existing') {
                $attachment = $existingById[$entry['id']] ?? null;
                if (!$attachment instanceof ItemPhoto && !$attachment instanceof PostPhoto) {
                    continue;
                }

                $photo = $attachment->photo;
                if (!$photo instanceof Photo) {
                    continue;
                }
                $result[] = [
                    'type' => 'existing',
                    'id' => (int) $attachment->id,
                    'thumbnailUrl' => $photo->getThumbnailUrl(320, 320, false, false, 90),
                    'previewUrl' => $photo->getUrl(),
                    'name' => 'Фотография #' . (int) $photo->id,
                ];
                continue;
            }

            $uploadFile = $temporaryById[$entry['id']] ?? null;
            if (!$uploadFile instanceof PhotoUploadFile || !$session instanceof PhotoUploadSession) {
                continue;
            }
            $routeParams = [
                'token' => (string) $session->token,
                'repoId' => (int) $session->repoId,
                'context' => (string) $session->context,
                'id' => (int) $uploadFile->id,
            ];
            $result[] = [
                'type' => 'temporary',
                'id' => (int) $uploadFile->id,
                'thumbnailUrl' => Url::toRoute(array_merge(['/photo-upload/thumbnail'], $routeParams)),
                'previewUrl' => Url::toRoute(array_merge(['/photo-upload/view'], $routeParams)),
                'deleteUrl' => Url::toRoute(array_merge(['/photo-upload/delete'], $routeParams)),
                'name' => (string) $uploadFile->originalName,
            ];
        }

        // После истечения/удаления session не оставляем форме нерабочий upload
        // URL. Manifest также синхронизируем с реально восстановленными
        // карточками: пропавшую temporary запись пользователь иначе не смог бы
        // убрать с формы перед следующей попыткой сохранения.
        if (!$session instanceof PhotoUploadSession) {
            $form->sessionToken = '';
        }
        $restoredManifest = array_map(
            static fn(array $entry): array => [
                'type' => $entry['type'],
                'id' => $entry['id'],
            ],
            $result
        );
        $encodedManifest = json_encode($restoredManifest, JSON_UNESCAPED_SLASHES);
        if (is_string($encodedManifest)) {
            $form->manifest = $encodedManifest;
        }

        return $result;
    }

    /**
     * @param list<ItemPhoto|PostPhoto> $attachments
     */
    private function prepare(
        PhotoEditorForm $form,
        array $attachments,
        int $repoId,
        int $userId,
        string $context,
    ): ?PhotoEditorPlan {
        if (!$form->validate()) {
            return null;
        }

        $currentRevision = $this->revision($attachments);
        if ($form->revision === '' || !hash_equals($currentRevision, $form->revision)) {
            $form->addError(
                'manifest',
                'Список фотографий уже изменился в другой вкладке. Обновите страницу и повторите изменения.'
            );
            return null;
        }

        $existingAttachments = [];
        foreach ($attachments as $attachment) {
            $existingAttachments[(int) $attachment->id] = $attachment;
        }

        $temporaryIds = [];
        foreach ($form->getEntries() as $entry) {
            if ($entry['type'] === 'existing') {
                if (!isset($existingAttachments[$entry['id']])) {
                    $form->addError('manifest', 'Список фотографий содержит чужую или уже удаленную фотографию.');
                    return null;
                }
                continue;
            }

            $temporaryIds[] = $entry['id'];
        }

        if ($temporaryIds === []) {
            return new PhotoEditorPlan($form->getEntries(), $existingAttachments, [], null);
        }

        $session = (new PhotoUploadService())->findOwnedOpenSessionForUpdate(
            $form->sessionToken,
            $userId,
            $repoId,
            $context,
        );
        if ($session === null) {
            $form->addError('manifest', 'Сессия временной загрузки истекла или недоступна. Загрузите фотографии повторно.');
            return null;
        }

        $sessionFiles = $this->findUploadFilesForUpdate((int) $session->id);
        $allSessionFiles = [];
        foreach ($sessionFiles as $file) {
            $allSessionFiles[(int) $file->id] = $file;
        }

        $temporaryFiles = [];
        foreach ($temporaryIds as $temporaryId) {
            if (!isset($allSessionFiles[$temporaryId])) {
                $form->addError(
                    'manifest',
                    'Временная фотография уже удалена или принадлежит другой сессии.'
                );
                return null;
            }
            $temporaryFiles[$temporaryId] = $allSessionFiles[$temporaryId];
        }

        return new PhotoEditorPlan($form->getEntries(), $existingAttachments, $temporaryFiles, $session);
    }

    /**
     * @template TAttachment of ItemPhoto|PostPhoto
     * @param class-string<TAttachment> $attachmentClass
     * @return list<TAttachment>
     */
    private function lockAndFindAttachments(string $attachmentClass, string $ownerColumn, int $ownerId): array
    {
        $tableName = $attachmentClass::tableName();
        $quotedTable = Yii::$app->db->quoteTableName($tableName);
        $quotedIdColumn = Yii::$app->db->quoteColumnName('id');
        $quotedOwnerColumn = Yii::$app->db->quoteColumnName($ownerColumn);
        Yii::$app->db->createCommand(
            "SELECT {$quotedIdColumn} FROM {$quotedTable} WHERE {$quotedOwnerColumn} = :ownerId FOR UPDATE",
            [':ownerId' => $ownerId]
        )->queryColumn();

        /** @var list<TAttachment> $attachments */
        $attachments = array_values(
            $attachmentClass::find()
                ->where([$ownerColumn => $ownerId])
                ->orderBy(['sortIndex' => SORT_ASC])
                ->all()
        );

        return $attachments;
    }

    /** @return list<PhotoUploadFile> */
    private function findUploadFilesForUpdate(int $sessionId): array
    {
        $quotedTable = Yii::$app->db->quoteTableName(PhotoUploadFile::tableName());
        $files = array_values(
            PhotoUploadFile::findBySql(
                "SELECT * FROM {$quotedTable}
                 WHERE [[sessionId]] = :sessionId
                 ORDER BY [[id]] ASC
                 FOR UPDATE",
                [':sessionId' => $sessionId]
            )->all()
        );

        return $files;
    }

    /**
     * @param list<ItemPhoto|PostPhoto> $attachments
     */
    private function revision(array $attachments): string
    {
        $parts = [];
        foreach ($attachments as $attachment) {
            $parts[] = (int) $attachment->id . ':' . (int) $attachment->sortIndex;
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param class-string<ItemPhoto|PostPhoto> $attachmentClass
     * @return list<int> ID отсоединенных Photo для post-commit cleanup.
     * @throws Exception
     */
    private function apply(
        PhotoEditorPlan $plan,
        string $attachmentClass,
        string $ownerColumn,
        int $ownerId,
    ): array {
        /** @var list<ItemPhoto|PostPhoto> $desiredAttachments */
        $desiredAttachments = [];
        $allAttachments = array_values($plan->existingAttachments);

        foreach ($plan->entries as $entry) {
            if ($entry['type'] === 'existing') {
                $attachment = $plan->existingAttachments[$entry['id']];
                $desiredAttachments[] = $attachment;
                continue;
            }

            $uploadFile = $plan->temporaryFiles[$entry['id']];
            $attachment = $this->createAttachment(
                $attachmentClass,
                $ownerId,
                (int) $uploadFile->photoId,
            );
            if (!$attachment->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($attachment));
            }
            $allAttachments[] = $attachment;
            $desiredAttachments[] = $attachment;
        }

        $this->moveToTemporarySortRange($allAttachments);

        foreach ($desiredAttachments as $sortIndex => $attachment) {
            if ($sortIndex > self::MAX_SORT_INDEX) {
                throw new RuntimeException('Слишком много фотографий для сохранения порядка.');
            }
            $attachment->updateAttributes(['sortIndex' => $sortIndex]);
        }

        $desiredIds = [];
        foreach ($desiredAttachments as $attachment) {
            $desiredIds[(int) $attachment->id] = true;
        }
        $detachedPhotoIds = [];
        foreach ($allAttachments as $attachment) {
            if (!isset($desiredIds[(int) $attachment->id])) {
                // ActiveRecord hooks удаляют JPEG немедленно, еще до commit.
                // Удаляем только связь; сам Photo безопасно очищается после commit.
                $deletedRows = $attachmentClass::deleteAll([
                    'id' => (int) $attachment->id,
                    $ownerColumn => $ownerId,
                ]);
                if ($deletedRows !== 1) {
                    throw new RuntimeException('Не удалось удалить фотографию из списка.');
                }
                $detachedPhotoIds[] = (int) $attachment->photoId;
            }
        }

        $uploadService = new PhotoUploadService();
        foreach (array_values(array_unique($detachedPhotoIds)) as $photoId) {
            $uploadService->queueDetachedPhoto($photoId);
        }

        foreach ($plan->temporaryFiles as $uploadFile) {
            if ($uploadFile->delete() !== 1) {
                throw new RuntimeException('Не удалось завершить временную загрузку фотографии.');
            }
        }

        if (
            $plan->session !== null
            // Повторный current locking read обязателен: обычный exists() в
            // REPEATABLE READ мог бы использовать snapshot, созданный до
            // блокировки session, и не увидеть upload из другой вкладки,
            // успевший завершиться прямо перед prepare().
            && $this->findUploadFilesForUpdate((int) $plan->session->id) === []
        ) {
            $consumedAt = time();
            $updatedRows = PhotoUploadSession::updateAll(
                ['consumedAt' => $consumedAt, 'updated' => $consumedAt],
                ['id' => $plan->session->id, 'consumedAt' => null]
            );
            if ($updatedRows !== 1) {
                throw new RuntimeException('Не удалось завершить сессию загрузки фотографий.');
            }
            $plan->session->consumedAt = $consumedAt;
        }

        return array_values(array_unique($detachedPhotoIds));
    }

    /**
     * @param class-string<ItemPhoto|PostPhoto> $attachmentClass
     */
    private function createAttachment(
        string $attachmentClass,
        int $ownerId,
        int $photoId,
    ): ItemPhoto|PostPhoto {
        return match ($attachmentClass) {
            ItemPhoto::class => new ItemPhoto([
                'itemId' => $ownerId,
                'photoId' => $photoId,
            ]),
            PostPhoto::class => new PostPhoto([
                'postId' => $ownerId,
                'photoId' => $photoId,
            ]),
            default => throw new RuntimeException("Неизвестный тип связи фотографии: {$attachmentClass}."),
        };
    }

    /**
     * @param list<ItemPhoto|PostPhoto> $attachments
     */
    private function moveToTemporarySortRange(array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        $minimumSortIndex = min(array_map(
            static fn (ItemPhoto|PostPhoto $attachment): int => (int) $attachment->sortIndex,
            $attachments
        ));
        $temporaryStart = $minimumSortIndex - count($attachments) - 1;
        if ($temporaryStart < self::MIN_SORT_INDEX) {
            throw new RuntimeException('Не удалось выделить временный диапазон для сортировки фотографий.');
        }

        usort(
            $attachments,
            static fn (ItemPhoto|PostPhoto $left, ItemPhoto|PostPhoto $right): int => (int) $left->id <=> (int) $right->id
        );
        foreach ($attachments as $offset => $attachment) {
            $attachment->updateAttributes(['sortIndex' => $temporaryStart + $offset]);
        }
    }
}
