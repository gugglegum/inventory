<?php

declare(strict_types=1);

namespace backend\services;

use common\helpers\ValidateErrorsFormatter;
use common\models\ItemPhoto;
use common\models\Photo;
use common\models\PhotoDeletionQueue;
use common\models\PhotoUploadFile;
use common\models\PhotoUploadSession;
use common\models\PostPhoto;
use common\models\Repo;
use common\models\User;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Yii;
use yii\base\Exception;
use yii\web\UploadedFile;

/**
 * Управляет жизненным циклом фотографий, загружаемых до сохранения формы.
 *
 * Сам Photo и нормализованный JPEG создаются сразу: тяжелая GD-обработка
 * выполняется в отдельном upload-запросе. PhotoUploadFile служит явным marker,
 * по которому cleanup отличает временную фотографию от пользовательских данных.
 */
final class PhotoUploadService
{
    public const int SESSION_TTL_SECONDS = 86400;
    public const int THUMBNAIL_WIDTH = 320;
    public const int THUMBNAIL_HEIGHT = 320;
    public const bool THUMBNAIL_UPSCALE = false;
    public const bool THUMBNAIL_CROP = false;
    public const int THUMBNAIL_QUALITY = 90;

    private const array SUPPORTED_IMAGE_TYPES = [
        IMAGETYPE_GIF,
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_WEBP,
    ];

    public function createSession(Repo $repo, User $user, string $context): PhotoUploadSession
    {
        // Помимо проверки аргумента это фиксирует серверный whitelist контекстов.
        PhotoUploadSession::requiredRepoAccessForContext($context);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->lockUploadOwner((int) $user->id);
            $maximumSessions = (int) (Yii::$app->params['photos']['maxOpenUploadSessionsPerUser'] ?? 0);
            $openSessionCount = (int) PhotoUploadSession::find()
                ->where(['userId' => $user->id, 'consumedAt' => null])
                ->andWhere(['>', 'expiresAt', time()])
                ->count();
            if ($maximumSessions > 0 && $openSessionCount >= $maximumSessions) {
                throw new InvalidArgumentException('Открыто слишком много форм с временными загрузками.');
            }

            $session = new PhotoUploadSession([
                'token' => bin2hex(random_bytes(32)),
                'userId' => (int) $user->id,
                'repoId' => (int) $repo->id,
                'context' => $context,
                'expiresAt' => time() + self::SESSION_TTL_SECONDS,
            ]);

            if (!$session->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($session));
            }
            $transaction->commit();

            return $session;
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Находит открытую сессию ее владельца.
     */
    public function findOwnedOpenSession(string $token, int $userId): ?PhotoUploadSession
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/D', $token)) {
            return null;
        }

        $session = PhotoUploadSession::findOne([
            'token' => $token,
            'userId' => $userId,
            'consumedAt' => null,
        ]);

        return $session !== null && $session->isOpen() ? $session : null;
    }

    /**
     * Выполняет current locking read открытой сессии внутри транзакции.
     */
    public function findOwnedOpenSessionForUpdate(
        string $token,
        int $userId,
        int $repoId,
        string $context
    ): ?PhotoUploadSession {
        if (
            !preg_match('/\A[0-9a-f]{64}\z/D', $token)
            || Yii::$app->db->getTransaction() === null
        ) {
            return null;
        }

        $table = Yii::$app->db->quoteTableName(PhotoUploadSession::tableName());
        $session = PhotoUploadSession::findBySql(
            "SELECT * FROM {$table}
             WHERE [[token]] = :token
               AND [[userId]] = :userId
               AND [[repoId]] = :repoId
               AND [[context]] = :context
               AND [[consumedAt]] IS NULL
               AND [[expiresAt]] > :now
             FOR UPDATE",
            [
                ':token' => $token,
                ':userId' => $userId,
                ':repoId' => $repoId,
                ':context' => $context,
                ':now' => time(),
            ]
        )->one();

        return $session;
    }

    /**
     * Сохраняет один upload как нормализованный Photo и создает temporary marker.
     *
     * @throws Exception
     */
    public function storeUploadedFile(
        PhotoUploadSession $session,
        UploadedFile $uploadedFile,
        int $userId
    ): PhotoUploadFile {
        if (!$session->isOpen()) {
            throw new InvalidArgumentException('Сессия загрузки уже завершена или истекла.');
        }
        if ((int) $session->userId !== $userId) {
            throw new InvalidArgumentException('Сессия загрузки принадлежит другому пользователю.');
        }
        if ($uploadedFile->error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($this->uploadErrorMessage($uploadedFile->error));
        }
        if (!is_file($uploadedFile->tempName) || !is_readable($uploadedFile->tempName)) {
            throw new InvalidArgumentException('Загруженный файл недоступен для чтения.');
        }
        $actualSize = filesize($uploadedFile->tempName);
        if (!is_int($actualSize)) {
            throw new InvalidArgumentException('Не удалось определить размер загруженного файла.');
        }
        $maximumBytes = (int) (Yii::$app->params['photos']['maxUploadBytes'] ?? 0);
        if ($maximumBytes > 0 && $actualSize > $maximumBytes) {
            throw new InvalidArgumentException('Загруженный файл превышает допустимый размер.');
        }

        $this->assertUploadCapacity($session, $userId);

        $originalName = $this->normalizeOriginalName($uploadedFile->name);
        $this->validateUploadedImage($uploadedFile->tempName);
        $this->ensureRuntimeDirectories();

        $photo = new Photo();
        $photo->assignFile($uploadedFile->tempName);

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $lockedSession = $this->findOwnedOpenSessionForUpdate(
                (string) $session->token,
                $userId,
                (int) $session->repoId,
                (string) $session->context,
            );
            if (!$lockedSession instanceof PhotoUploadSession || (int) $lockedSession->id !== (int) $session->id) {
                throw new InvalidArgumentException('Сессия загрузки уже завершена или истекла.');
            }

            $this->lockUploadOwner($userId);
            $this->assertUploadCapacity($lockedSession, $userId);

            if (!$photo->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($photo));
            }

            $uploadFile = new PhotoUploadFile([
                'sessionId' => (int) $lockedSession->id,
                'photoId' => (int) $photo->id,
                'originalName' => $originalName,
            ]);
            if (!$uploadFile->save()) {
                throw new Exception(ValidateErrorsFormatter::getMessage($uploadFile));
            }

            // TTL считается от последней успешной активности, поэтому файл,
            // добавленный в давно открытую форму, тоже живет полные 24 часа.
            $lockedSession->expiresAt = time() + self::SESSION_TTL_SECONDS;
            if (!$lockedSession->save(false, ['expiresAt', 'updated'])) {
                throw new RuntimeException('Не удалось продлить сессию загрузки.');
            }

            $transaction->commit();
            $session->setAttributes($lockedSession->getAttributes(), false);

            try {
                $photo->createThumbnail(
                    self::THUMBNAIL_WIDTH,
                    self::THUMBNAIL_HEIGHT,
                    self::THUMBNAIL_UPSCALE,
                    self::THUMBNAIL_CROP,
                    self::THUMBNAIL_QUALITY
                );
            } catch (Throwable $thumbnailException) {
                // Защищенный thumbnail endpoint повторит генерацию при первом GET.
                Yii::warning(
                    "Не удалось заранее создать thumbnail Photo {$photo->id}: {$thumbnailException->getMessage()}",
                    __METHOD__
                );
            }

            return $uploadFile;
        } catch (Throwable $exception) {
            try {
                if ($transaction->isActive && !$photo->isNewRecord) {
                    $this->deleteUnattachedPhoto($photo);
                }
            } catch (Throwable $cleanupException) {
                Yii::error(
                    'Не удалось удалить Photo после ошибки временной загрузки: '
                    . $cleanupException->getMessage(),
                    __METHOD__
                );
            }

            if ($transaction->isActive) {
                $transaction->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Возвращает marker, строго принадлежащий указанной сессии.
     */
    public function findSessionFile(PhotoUploadSession $session, int $fileId): ?PhotoUploadFile
    {
        return PhotoUploadFile::findOne([
            'id' => $fileId,
            'sessionId' => (int) $session->id,
        ]);
    }

    /**
     * Немедленно удаляет новую карточку: marker, Photo, оригинал и все thumbnails.
     * Уже примененные Photo этим методом удалить невозможно, так как их marker
     * удаляется во время consume.
     */
    public function discardFile(PhotoUploadFile $uploadFile): void
    {
        $photo = $uploadFile->photo;
        if (!$photo instanceof Photo) {
            if ($uploadFile->delete() === false) {
                throw new RuntimeException('Не удалось удалить marker отсутствующей временной фотографии.');
            }
            return;
        }

        if ($this->isPhotoAttached((int) $photo->id)) {
            // Fail safe: поврежденный marker не должен удалить уже примененные данные.
            if ($uploadFile->delete() === false) {
                throw new RuntimeException('Не удалось удалить временный marker фотографии.');
            }
            return;
        }

        $this->deleteUnattachedPhoto($photo);
    }

    /**
     * Удаляет Photo, отсоединенную от формы после успешного commit.
     *
     * Повторная проверка обеих таблиц связей и temporary marker делает вызов
     * безопасным при параллельном сохранении или повторном cleanup.
     */
    public function discardDetachedPhoto(int $photoId): void
    {
        if ($photoId <= 0) {
            throw new InvalidArgumentException('Некорректный ID отсоединенной фотографии.');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Во всех путях порядок блокировок одинаков: attachment ranges,
            // затем queue marker. Это не дает двум параллельным detach убрать
            // marker по устаревшему снимку общей Photo.
            $isAttached = $this->lockPhotoAttachments($photoId);
            $queueEntry = $this->findDeletionQueueForUpdate($photoId);
            if (!$queueEntry instanceof PhotoDeletionQueue) {
                $transaction->commit();
                return;
            }

            if ($isAttached) {
                if ($queueEntry->delete() !== 1) {
                    throw new RuntimeException("Не удалось отменить удаление снова привязанной Photo {$photoId}.");
                }
                $transaction->commit();
                return;
            }
            if ($this->lockTemporaryPhotoMarker($photoId)) {
                throw new RuntimeException("Photo {$photoId} одновременно отмечена временной и удаляемой.");
            }

            $photo = $this->findPhotoForUpdate($photoId);
            if (!$photo instanceof Photo) {
                if ($queueEntry->delete() !== 1) {
                    throw new RuntimeException("Не удалось удалить marker отсутствующей Photo {$photoId}.");
                }
                $transaction->commit();
                return;
            }

            $this->deletePhotoThumbnails($photoId);
            if (!is_file($photo->getFile())) {
                if (Photo::deleteAll(['id' => $photoId]) !== 1) {
                    throw new RuntimeException("Не удалось удалить запись Photo {$photoId} без файла.");
                }
            } elseif ($photo->delete() !== 1) {
                throw new RuntimeException("Не удалось удалить отсоединенную Photo {$photoId}.");
            }

            $transaction->commit();
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Ставит отсоединенную Photo в явную rollback-safe очередь удаления.
     */
    public function queueDetachedPhoto(int $photoId): void
    {
        if (Yii::$app->db->getTransaction() === null) {
            throw new RuntimeException('Photo можно поставить в очередь удаления только внутри транзакции формы.');
        }
        if ($photoId <= 0 || !Photo::find()->where(['id' => $photoId])->exists()) {
            throw new InvalidArgumentException('Некорректный ID отсоединенной фотографии.');
        }
        if ($this->findDeletionQueueForUpdate($photoId) instanceof PhotoDeletionQueue) {
            return;
        }

        $queueEntry = new PhotoDeletionQueue(['photoId' => $photoId]);
        if (!$queueEntry->save()) {
            throw new Exception(ValidateErrorsFormatter::getMessage($queueEntry));
        }
    }

    /**
     * Удаляет истекшие сессии и только те Photo, для которых еще существует marker.
     *
     * Явно поставленные в очередь отсоединенные Photo повторно удаляются этим же cron.
     * Исторические Photo без marker команда намеренно не затрагивает.
     *
     * @return array{sessions:int, files:int, queuedPhotos:int, bytes:int}
     */
    public function pruneExpired(bool $dryRun = false, ?int $now = null): array
    {
        $now ??= time();
        /** @var list<PhotoUploadSession> $sessions */
        $sessions = PhotoUploadSession::find()
            ->where(['<=', 'expiresAt', $now])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $result = ['sessions' => 0, 'files' => 0, 'queuedPhotos' => 0, 'bytes' => 0];
        foreach ($sessions as $session) {
            $sessionResult = $this->pruneSession((int) $session->id, $now, $dryRun);
            $result['sessions'] += $sessionResult['sessions'];
            $result['files'] += $sessionResult['files'];
            $result['bytes'] += $sessionResult['bytes'];
        }

        /** @var list<PhotoDeletionQueue> $queuedPhotos */
        $queuedPhotos = PhotoDeletionQueue::find()->orderBy(['id' => SORT_ASC])->all();
        foreach ($queuedPhotos as $queueEntry) {
            $result['queuedPhotos']++;
            if ($queueEntry->photo instanceof Photo) {
                $result['bytes'] += (int) $queueEntry->photo->size;
            }
            if (!$dryRun) {
                $this->discardDetachedPhoto((int) $queueEntry->photoId);
            }
        }

        return $result;
    }

    /**
     * Удаляет временные загрузки репозитория перед его hard delete.
     */
    public function discardSessionsForRepo(int $repoId): void
    {
        /** @var list<PhotoUploadSession> $sessions */
        $sessions = PhotoUploadSession::find()->where(['repoId' => $repoId])->all();
        $this->discardSessions($sessions);
    }

    /**
     * Удаляет временные загрузки пользователя перед его hard delete.
     */
    public function discardSessionsForUser(int $userId): void
    {
        /** @var list<PhotoUploadSession> $sessions */
        $sessions = PhotoUploadSession::find()->where(['userId' => $userId])->all();
        $this->discardSessions($sessions);
    }

    /**
     * @param list<PhotoUploadSession> $sessions
     */
    private function discardSessions(array $sessions): void
    {
        foreach ($sessions as $session) {
            $this->discardSession((int) $session->id);
        }
    }

    /**
     * Повторно проверяет TTL под session lock и удаляет одну истекшую сессию.
     *
     * @return array{sessions:int, files:int, bytes:int}
     */
    private function pruneSession(int $sessionId, int $now, bool $dryRun): array
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $session = $this->findSessionForUpdate($sessionId);
            if (!$session instanceof PhotoUploadSession || (int) $session->expiresAt > $now) {
                $transaction->commit();
                return ['sessions' => 0, 'files' => 0, 'bytes' => 0];
            }

            $files = $this->findSessionFilesForUpdate($sessionId);
            $bytes = 0;
            foreach ($files as $uploadFile) {
                if ($uploadFile->photo instanceof Photo) {
                    $bytes += (int) $uploadFile->photo->size;
                }
                if (!$dryRun) {
                    $this->discardFile($uploadFile);
                }
            }

            if (!$dryRun && $session->delete() !== 1) {
                throw new RuntimeException("Не удалось удалить upload-сессию {$sessionId}.");
            }

            $transaction->commit();
            return ['sessions' => 1, 'files' => count($files), 'bytes' => $bytes];
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Принудительно удаляет одну сессию под тем же lock, который используют upload и consume.
     */
    private function discardSession(int $sessionId): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $session = $this->findSessionForUpdate($sessionId);
            if (!$session instanceof PhotoUploadSession) {
                $transaction->commit();
                return;
            }

            foreach ($this->findSessionFilesForUpdate($sessionId) as $uploadFile) {
                $this->discardFile($uploadFile);
            }
            if ($session->delete() !== 1) {
                throw new RuntimeException("Не удалось удалить upload-сессию {$sessionId}.");
            }

            $transaction->commit();
        } catch (Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }

    private function findSessionForUpdate(int $sessionId): ?PhotoUploadSession
    {
        $table = Yii::$app->db->quoteTableName(PhotoUploadSession::tableName());
        $session = PhotoUploadSession::findBySql(
            "SELECT * FROM {$table} WHERE [[id]] = :sessionId FOR UPDATE",
            [':sessionId' => $sessionId]
        )->one();

        return $session;
    }

    /** @return list<PhotoUploadFile> */
    private function findSessionFilesForUpdate(int $sessionId): array
    {
        $table = Yii::$app->db->quoteTableName(PhotoUploadFile::tableName());
        /** @var list<PhotoUploadFile> $files */
        $files = PhotoUploadFile::findBySql(
            "SELECT * FROM {$table} WHERE [[sessionId]] = :sessionId ORDER BY [[id]] FOR UPDATE",
            [':sessionId' => $sessionId]
        )->all();

        return $files;
    }

    /**
     * Удаляет все thumbnail cache files, затем Photo (marker удалится по FK).
     */
    private function deleteUnattachedPhoto(Photo $photo): void
    {
        if ($this->isPhotoAttached((int) $photo->id)) {
            throw new RuntimeException("Photo {$photo->id} уже привязана и не является временной.");
        }

        $this->deletePhotoThumbnails((int) $photo->id);

        if (!is_file($photo->getFile())) {
            // Photo::afterDelete() намеренно считает отсутствие обычного файла
            // ошибкой. Для cleanup незавершенной загрузки удаляем такую битую
            // запись напрямую; target все равно ограничен явным marker.
            Photo::deleteAll(['id' => (int) $photo->id]);
            return;
        }

        if ($photo->delete() === false) {
            throw new RuntimeException("Не удалось удалить временную Photo {$photo->id}.");
        }
    }

    private function isPhotoAttached(int $photoId): bool
    {
        return ItemPhoto::find()->where(['photoId' => $photoId])->exists()
            || PostPhoto::find()->where(['photoId' => $photoId])->exists();
    }

    /**
     * Блокирует существующие attachment rows и gap для указанной Photo.
     */
    private function lockPhotoAttachments(int $photoId): bool
    {
        $isAttached = false;
        foreach ([ItemPhoto::tableName(), PostPhoto::tableName()] as $tableName) {
            $table = Yii::$app->db->quoteTableName($tableName);
            $ids = Yii::$app->db->createCommand(
                "SELECT [[id]] FROM {$table} WHERE [[photoId]] = :photoId FOR UPDATE",
                [':photoId' => $photoId]
            )->queryColumn();
            $isAttached = $isAttached || $ids !== [];
        }

        return $isAttached;
    }

    private function findDeletionQueueForUpdate(int $photoId): ?PhotoDeletionQueue
    {
        $table = Yii::$app->db->quoteTableName(PhotoDeletionQueue::tableName());
        $queueEntry = PhotoDeletionQueue::findBySql(
            "SELECT * FROM {$table} WHERE [[photoId]] = :photoId FOR UPDATE",
            [':photoId' => $photoId]
        )->one();

        return $queueEntry;
    }

    private function lockTemporaryPhotoMarker(int $photoId): bool
    {
        $table = Yii::$app->db->quoteTableName(PhotoUploadFile::tableName());
        return Yii::$app->db->createCommand(
            "SELECT [[id]] FROM {$table} WHERE [[photoId]] = :photoId FOR UPDATE",
            [':photoId' => $photoId]
        )->queryScalar() !== false;
    }

    private function findPhotoForUpdate(int $photoId): ?Photo
    {
        $table = Yii::$app->db->quoteTableName(Photo::tableName());
        $photo = Photo::findBySql(
            "SELECT * FROM {$table} WHERE [[id]] = :photoId FOR UPDATE",
            [':photoId' => $photoId]
        )->one();

        return $photo;
    }

    private function deletePhotoThumbnails(int $photoId): void
    {
        $root = rtrim((string) Yii::$app->params['photos']['thumbnailPath'], '/');
        if ($root === '' || !is_dir($root)) {
            return;
        }

        $relativePath = Photo::getFileRelativePath($photoId);
        $files = glob($root . '/*/' . $relativePath);
        if ($files === false) {
            throw new RuntimeException("Не удалось найти thumbnails временной Photo {$photoId}.");
        }

        foreach ($files as $file) {
            if (is_file($file) && !@unlink($file)) {
                throw new RuntimeException("Не удалось удалить thumbnail {$file}.");
            }
        }
    }

    /**
     * Проверяет лимиты до GD и повторно под блокировками перед сохранением marker.
     */
    private function assertUploadCapacity(PhotoUploadSession $session, int $userId): void
    {
        $maximumSessionFiles = (int) (Yii::$app->params['photos']['maxFilesPerUploadSession'] ?? 0);
        $sessionFileCount = (int) PhotoUploadFile::find()
            ->where(['sessionId' => $session->id])
            ->count();
        if ($maximumSessionFiles > 0 && $sessionFileCount >= $maximumSessionFiles) {
            throw new InvalidArgumentException('В одной форме нельзя временно загрузить больше фотографий.');
        }

        $maximumUserFiles = (int) (Yii::$app->params['photos']['maxTemporaryFilesPerUser'] ?? 0);
        $userFileCount = (int) PhotoUploadFile::find()
            ->alias('uploadFile')
            ->innerJoin(
                ['uploadSession' => PhotoUploadSession::tableName()],
                '[[uploadSession.id]] = [[uploadFile.sessionId]]'
            )
            ->where(['uploadSession.userId' => $userId])
            ->count();
        if ($maximumUserFiles > 0 && $userFileCount >= $maximumUserFiles) {
            throw new InvalidArgumentException('У пользователя слишком много временно загруженных фотографий.');
        }
    }

    /**
     * Сериализует создание сессий и финальную проверку пользовательской квоты.
     */
    private function lockUploadOwner(int $userId): void
    {
        $table = Yii::$app->db->quoteTableName(User::tableName());
        $lockedUserId = Yii::$app->db->createCommand(
            "SELECT [[id]] FROM {$table} WHERE [[id]] = :userId FOR UPDATE",
            [':userId' => $userId]
        )->queryScalar();
        if ((int) $lockedUserId !== $userId) {
            throw new InvalidArgumentException('Пользователь временной загрузки не найден.');
        }
    }

    private function normalizeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', str_replace("\0", '', $name)));
        $name = trim($name);
        if ($name === '') {
            // Clipboard Blob в некоторых браузерах не имеет filename.
            $name = 'clipboard-image.jpg';
        }
        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Имя загруженного файла длиннее 255 символов.');
        }

        return $name;
    }

    private function validateUploadedImage(string $file): void
    {
        $info = @getimagesize($file);
        if (!is_array($info) || !isset($info[0], $info[1], $info[2])) {
            throw new InvalidArgumentException('Файл поврежден или не является изображением.');
        }
        if (!in_array($info[2], self::SUPPORTED_IMAGE_TYPES, true)) {
            throw new InvalidArgumentException('Поддерживаются изображения GIF, JPEG, PNG и WebP.');
        }
        if ($info[0] <= 0 || $info[1] <= 0) {
            throw new InvalidArgumentException('Изображение имеет некорректный размер.');
        }
        $maximumPixels = (int) (Yii::$app->params['photos']['maxUploadPixels'] ?? 0);
        if ($maximumPixels > 0 && $info[0] * $info[1] > $maximumPixels) {
            throw new InvalidArgumentException('Изображение имеет слишком большое разрешение.');
        }

        // Полное декодирование выполняет Photo::assignFile(). Повторно открывать
        // потенциально большое изображение здесь не нужно.
    }

    private function ensureRuntimeDirectories(): void
    {
        foreach (
            [
                Yii::$app->params['photos']['storagePath'],
                Yii::$app->params['photos']['storageTemp'],
                Yii::$app->params['photos']['thumbnailPath'],
                Yii::$app->params['photos']['thumbnailTemp'],
            ] as $directory
        ) {
            if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException("Не удалось создать каталог фотографий {$directory}.");
            }
        }
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Загруженный файл слишком большой.',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен не полностью.',
            UPLOAD_ERR_NO_FILE => 'Файл не был передан.',
            default => 'Не удалось загрузить файл.',
        };
    }
}
