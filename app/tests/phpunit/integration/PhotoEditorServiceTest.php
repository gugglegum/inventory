<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\PhotoEditorService;
use backend\services\PhotoUploadService;
use common\models\Item;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\PhotoDeletionQueue;
use common\models\PhotoUploadFile;
use common\models\PhotoUploadSession;
use common\models\Repo;
use common\models\RepoUser;
use common\models\PostPhoto;
use common\models\User;
use RuntimeException;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\UploadedFile;

/**
 * Integration-тесты отложенного применения общего списка фотографий формы.
 */
final class PhotoEditorServiceTest extends DbTestCase
{
    public function testApplyReordersKeepsAddsAndDeletesOnlyOnSave(): void
    {
        [$repo, $item, $user] = $this->prepareFixture();
        $first = $this->createItemPhoto($item);
        $second = $this->createItemPhoto($item);
        $removedPhotoId = (int) $first->photoId;
        $removedFile = $first->photo->getFile();

        $editorService = new PhotoEditorService();
        $editorForm = $editorService->createFormForItem($item);
        [$session, $temporary] = $this->uploadTemporary($repo, $user, PhotoUploadSession::CONTEXT_ITEM_UPDATE);
        $temporaryPhotoId = (int) $temporary->photoId;
        $editorForm->sessionToken = (string) $session->token;
        $editorForm->manifest = $this->manifest([
            ['type' => 'existing', 'id' => (int) $second->id],
            ['type' => 'temporary', 'id' => (int) $temporary->id],
        ]);

        // До применения manifest база и файл существующей фотографии не меняются.
        self::assertNotNull(ItemPhoto::findOne($first->id));
        self::assertFileExists($removedFile);
        self::assertNotNull(PhotoUploadFile::findOne($temporary->id));

        $plan = $editorService->prepareForItem($editorForm, $item, (int) $user->id);
        self::assertNotNull(
            $plan,
            json_encode($editorForm->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );
        $transaction = Yii::$app->db->beginTransaction();
        $committed = false;
        try {
            $detachedPhotoIds = $editorService->applyToItem($plan, $item);
            self::assertNotNull(PhotoDeletionQueue::findOne(['photoId' => $removedPhotoId]));
            $transaction->commit();
            $committed = true;
        } finally {
            if (!$committed && $transaction->isActive) {
                $transaction->rollBack();
            }
        }

        $attachments = ItemPhoto::find()
            ->where(['itemId' => $item->id])
            ->orderBy(['sortIndex' => SORT_ASC])
            ->all();

        self::assertCount(2, $attachments);
        self::assertSame((int) $second->id, (int) $attachments[0]->id);
        self::assertSame($temporaryPhotoId, (int) $attachments[1]->photoId);
        self::assertSame([0, 1], array_map(static fn(ItemPhoto $photo): int => (int) $photo->sortIndex, $attachments));
        self::assertSame([$removedPhotoId], $detachedPhotoIds);
        self::assertNotNull(Photo::findOne($removedPhotoId));
        self::assertNotNull(PhotoDeletionQueue::findOne(['photoId' => $removedPhotoId]));
        self::assertFileExists($removedFile);
        self::assertNull(PhotoUploadFile::findOne($temporary->id));
        self::assertNotNull(PhotoUploadSession::findOne($session->id)?->consumedAt);

        $editorService->cleanupDetachedPhotos($detachedPhotoIds);

        self::assertNull(Photo::findOne($removedPhotoId));
        self::assertNull(PhotoDeletionQueue::findOne(['photoId' => $removedPhotoId]));
        self::assertFileDoesNotExist($removedFile);
    }

    public function testRollbackAfterApplyPreservesAttachmentAndPhysicalFile(): void
    {
        [, $item, $user] = $this->prepareFixture();
        $attachment = $this->createItemPhoto($item);
        $photoId = (int) $attachment->photoId;
        $photoFile = $attachment->photo->getFile();
        $service = new PhotoEditorService();
        $form = $service->createFormForItem($item);
        $form->manifest = '[]';

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $plan = $service->prepareForItem($form, $item, (int) $user->id);
            self::assertNotNull(
                $plan,
                json_encode($form->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            );
            self::assertSame([$photoId], $service->applyToItem($plan, $item));
            self::assertNull(ItemPhoto::findOne($attachment->id));
            self::assertNotNull(PhotoDeletionQueue::findOne(['photoId' => $photoId]));
            self::assertFileExists($photoFile);
        } finally {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
        }

        self::assertNotNull(ItemPhoto::findOne($attachment->id));
        self::assertNotNull(Photo::findOne($photoId));
        self::assertNull(PhotoDeletionQueue::findOne(['photoId' => $photoId]));
        self::assertFileExists($photoFile);
    }

    public function testApplyToPostUsesSameOrderedManifest(): void
    {
        [$repo, $item, $user] = $this->prepareFixture();
        $post = $this->createPost($item, $user);
        $removed = $this->createPostPhoto($post);
        $kept = $this->createPostPhoto($post);
        $removedPhotoId = (int) $removed->photoId;
        $service = new PhotoEditorService();
        $form = $service->createFormForPost($post);
        [$session, $temporary] = $this->uploadTemporary(
            $repo,
            $user,
            PhotoUploadSession::CONTEXT_POST
        );
        $form->sessionToken = (string) $session->token;
        $form->manifest = $this->manifest([
            ['type' => 'temporary', 'id' => (int) $temporary->id],
            ['type' => 'existing', 'id' => (int) $kept->id],
        ]);

        $plan = $service->prepareForPost(
            $form,
            $post,
            (int) $repo->id,
            (int) $user->id
        );
        self::assertNotNull($plan, json_encode($form->errors, JSON_THROW_ON_ERROR));
        self::assertSame([$removedPhotoId], $service->applyToPost($plan, $post));

        $attachments = PostPhoto::find()
            ->where(['postId' => $post->id])
            ->orderBy(['sortIndex' => SORT_ASC])
            ->all();
        self::assertCount(2, $attachments);
        self::assertSame((int) $temporary->photoId, (int) $attachments[0]->photoId);
        self::assertSame((int) $kept->id, (int) $attachments[1]->id);
        self::assertSame([0, 1], array_map(
            static fn(PostPhoto $attachment): int => (int) $attachment->sortIndex,
            $attachments
        ));
        self::assertNull(PhotoUploadFile::findOne($temporary->id));
        self::assertNotNull(PhotoDeletionQueue::findOne(['photoId' => $removedPhotoId]));

        $service->cleanupDetachedPhotos([$removedPhotoId]);
        self::assertNull(Photo::findOne($removedPhotoId));
    }

    public function testSameTemporaryMarkerCannotBeConsumedByTwoPreparedPlans(): void
    {
        [$repo, $firstItem, $user] = $this->prepareFixture();
        $secondItem = $this->createItem($repo, $user, ['name' => 'Второй предмет']);
        [$session, $temporary] = $this->uploadTemporary(
            $repo,
            $user,
            PhotoUploadSession::CONTEXT_ITEM_UPDATE
        );
        $photoId = (int) $temporary->photoId;
        $service = new PhotoEditorService();

        $firstForm = $service->createFormForItem($firstItem);
        $firstForm->sessionToken = (string) $session->token;
        $firstForm->manifest = $this->manifest([
            ['type' => 'temporary', 'id' => (int) $temporary->id],
        ]);
        $secondForm = $service->createFormForItem($secondItem);
        $secondForm->sessionToken = (string) $session->token;
        $secondForm->manifest = $firstForm->manifest;

        // Оба plan намеренно создаются до первого apply: второй содержит
        // валидный на момент подготовки, но затем устаревающий marker snapshot.
        $firstPlan = $service->prepareForItem($firstForm, $firstItem, (int) $user->id);
        $secondPlan = $service->prepareForItem($secondForm, $secondItem, (int) $user->id);
        self::assertNotNull(
            $firstPlan,
            json_encode($firstForm->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );
        self::assertNotNull(
            $secondPlan,
            json_encode($secondForm->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        self::assertSame([], $service->applyToItem($firstPlan, $firstItem));
        self::assertNull(PhotoUploadFile::findOne($temporary->id));
        self::assertNotNull(PhotoUploadSession::findOne($session->id)?->consumedAt);

        $secondTransaction = Yii::$app->db->beginTransaction();
        $caughtException = null;
        try {
            $service->applyToItem($secondPlan, $secondItem);
        } catch (RuntimeException $exception) {
            $caughtException = $exception;
        } finally {
            if ($secondTransaction->isActive) {
                $secondTransaction->rollBack();
            }
        }

        self::assertInstanceOf(RuntimeException::class, $caughtException);
        self::assertStringContainsString(
            'Не удалось завершить временную загрузку',
            $caughtException->getMessage()
        );
        self::assertTrue(ItemPhoto::find()->where([
            'itemId' => $firstItem->id,
            'photoId' => $photoId,
        ])->exists());
        self::assertFalse(ItemPhoto::find()->where([
            'itemId' => $secondItem->id,
            'photoId' => $photoId,
        ])->exists());
    }

    public function testPrepareRejectsStalePhotoListRevision(): void
    {
        [, $item, $user] = $this->prepareFixture();
        $this->createItemPhoto($item);
        $service = new PhotoEditorService();
        $form = $service->createFormForItem($item);

        $this->createItemPhoto($item);

        self::assertNull($service->prepareForItem($form, $item, (int) $user->id));
        self::assertNotEmpty($form->getErrors('manifest'));
    }

    public function testPrepareRejectsTemporaryFileFromAnotherSession(): void
    {
        [$repo, $item, $user] = $this->prepareFixture();
        $service = new PhotoEditorService();
        $form = $service->createFormForItem($item);
        [$ownSession] = $this->uploadTemporary($repo, $user, PhotoUploadSession::CONTEXT_ITEM_UPDATE);
        [, $foreignFile] = $this->uploadTemporary($repo, $user, PhotoUploadSession::CONTEXT_ITEM_UPDATE);
        $form->sessionToken = (string) $ownSession->token;
        $form->manifest = $this->manifest([
            ['type' => 'temporary', 'id' => (int) $foreignFile->id],
        ]);

        self::assertNull($service->prepareForItem($form, $item, (int) $user->id));
        self::assertNotEmpty($form->getErrors('manifest'));
    }

    public function testViewEntriesRestoresTemporaryCardAfterValidationFailure(): void
    {
        [$repo, $item, $user] = $this->prepareFixture();
        $service = new PhotoEditorService();
        $form = $service->createFormForItem($item);
        [$session, $temporary] = $this->uploadTemporary($repo, $user, PhotoUploadSession::CONTEXT_ITEM_UPDATE);
        $form->sessionToken = (string) $session->token;
        $form->manifest = $this->manifest([
            ['type' => 'temporary', 'id' => (int) $temporary->id],
        ]);
        self::assertNotNull($service->prepareForItem($form, $item, (int) $user->id));

        $entries = $service->viewEntriesForItem($form, $item, (int) $user->id);

        self::assertCount(1, $entries);
        self::assertSame('temporary', $entries[0]['type']);
        self::assertSame((int) $temporary->id, $entries[0]['id']);
        self::assertStringContainsString('/photo-upload/thumbnail', $entries[0]['thumbnailUrl']);
        self::assertStringContainsString('/photo-upload/view', $entries[0]['previewUrl']);
        $deleteUrl = $entries[0]['deleteUrl'] ?? null;
        self::assertIsString($deleteUrl);
        self::assertStringContainsString('/photo-upload/delete', $deleteUrl);
    }

    public function testViewEntriesDropsExpiredTemporaryCardAndResetsSession(): void
    {
        [$repo, $item, $user] = $this->prepareFixture();
        $service = new PhotoEditorService();
        $form = $service->createFormForItem($item);
        [$session, $temporary] = $this->uploadTemporary(
            $repo,
            $user,
            PhotoUploadSession::CONTEXT_ITEM_UPDATE
        );
        $form->sessionToken = (string) $session->token;
        $form->manifest = $this->manifest([
            ['type' => 'temporary', 'id' => (int) $temporary->id],
        ]);
        self::assertTrue($form->validate());

        $session->expiresAt = time() - 1;
        self::assertTrue($session->save(false, ['expiresAt', 'updated']));

        self::assertSame([], $service->viewEntriesForItem($form, $item, (int) $user->id));
        self::assertSame('', $form->sessionToken);
        self::assertSame('[]', $form->manifest);
    }

    /**
     * @return array{Repo, Item, User}
     */
    private function prepareFixture(): array
    {
        $user = $this->createUser(['access' => User::ACCESS_CREATE_REPO]);
        $repo = $this->createRepo($user);
        $this->grantRepoAccess(
            $repo,
            $user,
            RepoUser::ACCESS_CREATE_ITEMS | RepoUser::ACCESS_EDIT_ITEMS
        );
        $item = $this->createItem($repo, $user, ['name' => 'Предмет с редактором фото']);

        return [$repo, $item, $user];
    }

    /**
     * @return array{PhotoUploadSession, PhotoUploadFile}
     */
    private function uploadTemporary(Repo $repo, User $user, string $context): array
    {
        $uploadService = new PhotoUploadService();
        $session = $uploadService->createSession($repo, $user, $context);
        $source = $this->createUploadedJpegFixture();
        $size = filesize($source);
        self::assertIsInt($size);

        try {
            $file = $uploadService->storeUploadedFile(
                $session,
                new UploadedFile([
                    'name' => 'temporary.jpg',
                    'tempName' => $source,
                    'type' => 'image/jpeg',
                    'size' => $size,
                    'error' => UPLOAD_ERR_OK,
                ]),
                (int) $user->id,
            );
        } finally {
            @unlink($source);
        }

        return [$session, $file];
    }

    /**
     * @param list<array{type:string, id:int}> $entries
     */
    private function manifest(array $entries): string
    {
        return json_encode($entries, JSON_THROW_ON_ERROR);
    }
}
