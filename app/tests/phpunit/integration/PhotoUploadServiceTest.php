<?php

declare(strict_types=1);

namespace tests\phpunit\integration;

use backend\services\PhotoUploadService;
use common\models\Photo;
use common\models\PhotoDeletionQueue;
use common\models\PhotoUploadFile;
use common\models\PhotoUploadSession;
use common\models\Repo;
use common\models\RepoUser;
use common\models\User;
use InvalidArgumentException;
use tests\phpunit\DbTestCase;
use Yii;
use yii\web\UploadedFile;

/**
 * Integration-тесты явного жизненного цикла временных фотографий.
 */
final class PhotoUploadServiceTest extends DbTestCase
{
    public function testUploadCreatesNormalizedPhotoMarkerAndThumbnail(): void
    {
        [$service, $session, $user] = $this->prepareFixture();
        $source = $this->createUploadedJpegFixture();

        try {
            $uploadFile = $service->storeUploadedFile(
                $session,
                $this->uploadedFile($source, 'camera.jpg'),
                (int) $user->id
            );
        } finally {
            @unlink($source);
        }

        self::assertSame((int) $session->id, (int) $uploadFile->sessionId);
        self::assertSame('camera.jpg', $uploadFile->originalName);
        self::assertInstanceOf(Photo::class, $uploadFile->photo);
        self::assertFileExists($uploadFile->photo->getFile());
        self::assertFileExists($uploadFile->photo->getThumbnailFile(
            PhotoUploadService::THUMBNAIL_WIDTH,
            PhotoUploadService::THUMBNAIL_HEIGHT,
            PhotoUploadService::THUMBNAIL_UPSCALE,
            PhotoUploadService::THUMBNAIL_CROP,
            PhotoUploadService::THUMBNAIL_QUALITY
        ));
        self::assertGreaterThan(time() + PhotoUploadService::SESSION_TTL_SECONDS - 5, (int) $session->expiresAt);
    }

    public function testDiscardDeletesMarkerPhotoOriginalAndThumbnail(): void
    {
        [$service, $session, $user] = $this->prepareFixture();
        $uploadFile = $this->upload($service, $session, $user);
        $photoId = (int) $uploadFile->photoId;
        $photo = $uploadFile->photo;
        self::assertInstanceOf(Photo::class, $photo);
        $original = $photo->getFile();
        $thumbnail = $photo->getThumbnailFile(
            PhotoUploadService::THUMBNAIL_WIDTH,
            PhotoUploadService::THUMBNAIL_HEIGHT,
            PhotoUploadService::THUMBNAIL_UPSCALE,
            PhotoUploadService::THUMBNAIL_CROP,
            PhotoUploadService::THUMBNAIL_QUALITY
        );

        $service->discardFile($uploadFile);

        self::assertNull(PhotoUploadFile::findOne($uploadFile->id));
        self::assertNull(Photo::findOne($photoId));
        self::assertFileDoesNotExist($original);
        self::assertFileDoesNotExist($thumbnail);
    }

    public function testPruneDeletesOnlyExpiredMarkerBackedPhotos(): void
    {
        [$service, $session, $user] = $this->prepareFixture();
        $uploadFile = $this->upload($service, $session, $user);
        $temporaryPhotoId = (int) $uploadFile->photoId;
        $permanentPhoto = $this->createPhoto();
        $permanentPhotoId = (int) $permanentPhoto->id;

        $session->expiresAt = time() - 1;
        self::assertTrue($session->save(false, ['expiresAt', 'updated']));

        $result = $service->pruneExpired();

        self::assertSame(1, $result['sessions']);
        self::assertSame(1, $result['files']);
        self::assertNull(Photo::findOne($temporaryPhotoId));
        self::assertNotNull(Photo::findOne($permanentPhotoId));
        self::assertNull(PhotoUploadSession::findOne($session->id));
    }

    public function testPruneDeletesQueuedPhotoButPreservesLegacyUnmarkedOrphan(): void
    {
        [$service] = $this->prepareFixture();
        $queuedPhoto = $this->createPhoto();
        $queuedPhotoId = (int) $queuedPhoto->id;
        $queuedFile = $queuedPhoto->getFile();
        $legacyPhoto = $this->createPhoto();
        $legacyPhotoId = (int) $legacyPhoto->id;
        $legacyFile = $legacyPhoto->getFile();
        $legacyPhoto->updateAttributes(['created' => time() - 7 * 86400]);
        $service->queueDetachedPhoto($queuedPhotoId);

        self::assertNotNull(PhotoDeletionQueue::findOne(['photoId' => $queuedPhotoId]));
        self::assertNull(PhotoDeletionQueue::findOne(['photoId' => $legacyPhotoId]));

        $result = $service->pruneExpired();

        self::assertSame(0, $result['sessions']);
        self::assertSame(0, $result['files']);
        self::assertSame(1, $result['queuedPhotos']);
        self::assertNull(Photo::findOne($queuedPhotoId));
        self::assertNull(PhotoDeletionQueue::findOne(['photoId' => $queuedPhotoId]));
        self::assertFileDoesNotExist($queuedFile);
        self::assertNotNull(Photo::findOne($legacyPhotoId));
        self::assertFileExists($legacyFile);
    }

    public function testUploadRejectsFileOverByteLimitBeforeCreatingPhoto(): void
    {
        [$service, $session, $user] = $this->prepareFixture();
        $source = $this->createUploadedJpegFixture();
        $photoCount = (int) Photo::find()->count();
        $originalLimit = Yii::$app->params['photos']['maxUploadBytes'];
        Yii::$app->params['photos']['maxUploadBytes'] = 1;

        try {
            $this->assertRejectedUpload(
                fn() => $service->storeUploadedFile(
                    $session,
                    $this->uploadedFile($source, 'too-large.jpg'),
                    (int) $user->id
                ),
                'превышает допустимый размер'
            );
        } finally {
            Yii::$app->params['photos']['maxUploadBytes'] = $originalLimit;
            @unlink($source);
        }

        self::assertSame($photoCount, (int) Photo::find()->count());
        self::assertSame(0, (int) PhotoUploadFile::find()->where(['sessionId' => $session->id])->count());
    }

    public function testUploadRejectsImageOverPixelLimitBeforeCreatingPhoto(): void
    {
        [$service, $session, $user] = $this->prepareFixture();
        $source = $this->createUploadedJpegFixture();
        $photoCount = (int) Photo::find()->count();
        $originalLimit = Yii::$app->params['photos']['maxUploadPixels'];
        // Тестовая фикстура имеет размер 8x8, то есть 64 pixels.
        Yii::$app->params['photos']['maxUploadPixels'] = 63;

        try {
            $this->assertRejectedUpload(
                fn() => $service->storeUploadedFile(
                    $session,
                    $this->uploadedFile($source, 'too-many-pixels.jpg'),
                    (int) $user->id
                ),
                'слишком большое разрешение'
            );
        } finally {
            Yii::$app->params['photos']['maxUploadPixels'] = $originalLimit;
            @unlink($source);
        }

        self::assertSame($photoCount, (int) Photo::find()->count());
        self::assertSame(0, (int) PhotoUploadFile::find()->where(['sessionId' => $session->id])->count());
    }

    public function testUploadEnforcesPerSessionFileLimit(): void
    {
        [$service, $session, $user] = $this->prepareFixture();
        $originalLimit = Yii::$app->params['photos']['maxFilesPerUploadSession'];
        Yii::$app->params['photos']['maxFilesPerUploadSession'] = 1;

        try {
            $this->upload($service, $session, $user);
            $this->assertRejectedUpload(
                fn() => $this->upload($service, $session, $user),
                'В одной форме нельзя временно загрузить больше фотографий'
            );
        } finally {
            Yii::$app->params['photos']['maxFilesPerUploadSession'] = $originalLimit;
        }

        self::assertSame(1, (int) PhotoUploadFile::find()->where(['sessionId' => $session->id])->count());
    }

    public function testUploadEnforcesPerUserTemporaryFileLimitAcrossSessions(): void
    {
        [$service, $firstSession, $user, $repo] = $this->prepareFixture();
        $secondSession = $service->createSession($repo, $user, PhotoUploadSession::CONTEXT_ITEM_UPDATE);
        $originalLimit = Yii::$app->params['photos']['maxTemporaryFilesPerUser'];
        Yii::$app->params['photos']['maxTemporaryFilesPerUser'] = 1;

        try {
            $this->upload($service, $firstSession, $user);
            $this->assertRejectedUpload(
                fn() => $this->upload($service, $secondSession, $user),
                'слишком много временно загруженных фотографий'
            );
        } finally {
            Yii::$app->params['photos']['maxTemporaryFilesPerUser'] = $originalLimit;
        }

        self::assertSame(1, (int) PhotoUploadFile::find()->where([
            'sessionId' => [$firstSession->id, $secondSession->id],
        ])->count());
    }

    public function testCreateSessionEnforcesPerUserOpenSessionLimit(): void
    {
        [$service, , $user, $repo] = $this->prepareFixture();
        $originalLimit = Yii::$app->params['photos']['maxOpenUploadSessionsPerUser'];
        Yii::$app->params['photos']['maxOpenUploadSessionsPerUser'] = 1;

        try {
            $this->assertRejectedUpload(
                static fn() => $service->createSession(
                    $repo,
                    $user,
                    PhotoUploadSession::CONTEXT_ITEM_UPDATE
                ),
                'Открыто слишком много форм с временными загрузками'
            );
        } finally {
            Yii::$app->params['photos']['maxOpenUploadSessionsPerUser'] = $originalLimit;
        }

        self::assertSame(1, (int) PhotoUploadSession::find()
            ->where(['userId' => $user->id, 'consumedAt' => null])
            ->count());
    }

    /**
     * @return array{PhotoUploadService, PhotoUploadSession, User, Repo}
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

        $service = new PhotoUploadService();
        $session = $service->createSession($repo, $user, PhotoUploadSession::CONTEXT_ITEM_CREATE);

        return [$service, $session, $user, $repo];
    }

    private function upload(
        PhotoUploadService $service,
        PhotoUploadSession $session,
        User $user
    ): PhotoUploadFile {
        $source = $this->createUploadedJpegFixture();
        try {
            return $service->storeUploadedFile(
                $session,
                $this->uploadedFile($source, 'temporary.jpg'),
                (int) $user->id
            );
        } finally {
            @unlink($source);
        }
    }

    private function uploadedFile(string $file, string $name): UploadedFile
    {
        $size = filesize($file);
        self::assertIsInt($size);

        return new UploadedFile([
            'name' => $name,
            'tempName' => $file,
            'type' => 'image/jpeg',
            'size' => $size,
            'error' => UPLOAD_ERR_OK,
        ]);
    }

    /**
     * @param callable(): mixed $operation
     */
    private function assertRejectedUpload(callable $operation, string $expectedMessage): void
    {
        $caughtException = null;
        try {
            $operation();
        } catch (InvalidArgumentException $exception) {
            $caughtException = $exception;
        }

        if (!$caughtException instanceof InvalidArgumentException) {
            self::fail('Операция должна быть отклонена исключением InvalidArgumentException.');
        }
        self::assertStringContainsString($expectedMessage, $caughtException->getMessage());
    }
}
