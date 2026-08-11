<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\services\PhotoDeliveryService;
use backend\services\PhotoUploadService;
use common\models\Photo;
use common\models\PhotoUploadFile;
use common\models\PhotoUploadSession;
use common\models\User;
use InvalidArgumentException;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\UnprocessableEntityHttpException;
use yii\web\UploadedFile;

/**
 * HTTP API асинхронных временных загрузок фотографий.
 */
final class PhotoUploadController extends RepoAwareController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['view', 'thumbnail'],
                        'allow' => false,
                        'roles' => ['?'],
                        'denyCallback' => static function (): never {
                            throw new UnauthorizedHttpException('Для просмотра фотографии требуется авторизация.');
                        },
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['post'],
                    'upload' => ['post'],
                    'delete' => ['post', 'delete'],
                    'state' => ['get'],
                    'view' => ['get'],
                    'thumbnail' => ['get'],
                ],
            ],
        ];
    }

    /**
     * Создает отдельную сессию для одной открытой формы.
     *
     * POST: repoId, context=item-create|item-update|post.
     *
     * @return array<string, mixed>
     */
    public function actionCreate(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $repoId = (int) Yii::$app->request->post('repoId');
        $context = (string) Yii::$app->request->post('context');

        try {
            $requiredAccess = PhotoUploadSession::requiredRepoAccessForContext($context);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), 0, $exception);
        }

        $repo = $this->findRepo($repoId, $requiredAccess);
        $identity = $this->getIdentity();
        try {
            $session = (new PhotoUploadService())->createSession($repo, $identity, $context);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), 0, $exception);
        }

        return $this->serializeSession($session);
    }

    /**
     * Восстанавливает карточки временных файлов после reload или ошибки формы.
     *
     * @return array<string, mixed>
     */
    public function actionState(string $token, int $repoId, string $context): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $session = $this->findAccessibleSession($token, $repoId, $context);

        return $this->serializeSession($session);
    }

    /**
     * Принимает один файл в multipart field `file`.
     *
     * @return array<string, mixed>
     */
    public function actionUpload(string $token, int $repoId, string $context): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $session = $this->findAccessibleSession($token, $repoId, $context);
        $uploadedFile = UploadedFile::getInstanceByName('file');
        if (!$uploadedFile instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('Файл не был передан.');
        }

        try {
            $uploadFile = (new PhotoUploadService())->storeUploadedFile(
                $session,
                $uploadedFile,
                (int) $this->getLoggedUser()->id
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), 0, $exception);
        }

        Yii::$app->response->statusCode = 201;
        return $this->serializeFile($session, $uploadFile);
    }

    /**
     * Немедленно отменяет только новую временную карточку.
     *
     * @return array{deleted:bool}
     */
    public function actionDelete(string $token, int $repoId, string $context, int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $session = $this->findAccessibleSession($token, $repoId, $context);
        $service = new PhotoUploadService();
        $uploadFile = $service->findSessionFile($session, $id);
        if (!$uploadFile instanceof PhotoUploadFile) {
            throw new NotFoundHttpException('Временная фотография не найдена.');
        }

        $service->discardFile($uploadFile);

        return ['deleted' => true];
    }

    /**
     * Защищенный preview временного оригинала.
     */
    public function actionView(string $token, int $repoId, string $context, int $id): Response
    {
        $uploadFile = $this->findAccessibleFile($token, $repoId, $context, $id);
        if (!$uploadFile->photo instanceof Photo || !is_file($uploadFile->photo->getFile())) {
            throw new NotFoundHttpException('Файл временной фотографии не найден.');
        }

        return (new PhotoDeliveryService())->original($uploadFile->photo);
    }

    /**
     * Защищенная квадратная миниатюра временной фотографии.
     */
    public function actionThumbnail(string $token, int $repoId, string $context, int $id): Response
    {
        $uploadFile = $this->findAccessibleFile($token, $repoId, $context, $id);
        $photo = $uploadFile->photo;
        if (!$photo instanceof Photo || !is_file($photo->getFile())) {
            throw new NotFoundHttpException('Файл временной фотографии не найден.');
        }

        $thumbnailFile = $photo->getThumbnailFile(
            PhotoUploadService::THUMBNAIL_WIDTH,
            PhotoUploadService::THUMBNAIL_HEIGHT,
            PhotoUploadService::THUMBNAIL_UPSCALE,
            PhotoUploadService::THUMBNAIL_CROP,
            PhotoUploadService::THUMBNAIL_QUALITY
        );
        if (!is_file($thumbnailFile)) {
            $photo->createThumbnail(
                PhotoUploadService::THUMBNAIL_WIDTH,
                PhotoUploadService::THUMBNAIL_HEIGHT,
                PhotoUploadService::THUMBNAIL_UPSCALE,
                PhotoUploadService::THUMBNAIL_CROP,
                PhotoUploadService::THUMBNAIL_QUALITY
            );
        }

        return (new PhotoDeliveryService())->thumbnail(
            $photo,
            PhotoUploadService::THUMBNAIL_WIDTH,
            PhotoUploadService::THUMBNAIL_HEIGHT,
            PhotoUploadService::THUMBNAIL_UPSCALE,
            PhotoUploadService::THUMBNAIL_CROP,
            PhotoUploadService::THUMBNAIL_QUALITY
        );
    }

    private function findAccessibleSession(
        string $token,
        int $repoId,
        string $context
    ): PhotoUploadSession {
        $session = (new PhotoUploadService())->findOwnedOpenSession(
            $token,
            (int) $this->getLoggedUser()->id
        );
        if (
            !$session instanceof PhotoUploadSession
            || (int) $session->repoId !== $repoId
            || !hash_equals((string) $session->context, $context)
        ) {
            throw new NotFoundHttpException('Сессия загрузки не найдена или истекла.');
        }

        // Права перепроверяются на каждом запросе: отзыв RepoUser немедленно
        // закрывает upload, preview и state уже созданной сессии.
        $this->findRepo((int) $session->repoId, $session->getRequiredRepoAccess());

        return $session;
    }

    private function findAccessibleFile(
        string $token,
        int $repoId,
        string $context,
        int $id
    ): PhotoUploadFile {
        $session = $this->findAccessibleSession($token, $repoId, $context);
        $uploadFile = (new PhotoUploadService())->findSessionFile($session, $id);
        if (!$uploadFile instanceof PhotoUploadFile) {
            throw new NotFoundHttpException('Временная фотография не найдена.');
        }

        return $uploadFile;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(PhotoUploadSession $session): array
    {
        $routeParams = $this->sessionRouteParams($session);

        return [
            'session_id' => (string) $session->token,
            'token' => (string) $session->token,
            'repo_id' => (int) $session->repoId,
            'context' => (string) $session->context,
            'expires_at' => (int) $session->expiresAt,
            'upload_url' => Url::toRoute(array_merge(['/photo-upload/upload'], $routeParams)),
            'state_url' => Url::toRoute(array_merge(['/photo-upload/state'], $routeParams)),
            'files' => array_map(
                fn(PhotoUploadFile $file): array => $this->serializeFile($session, $file),
                $session->files
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFile(PhotoUploadSession $session, PhotoUploadFile $file): array
    {
        $photo = $file->photo;
        if (!$photo instanceof Photo) {
            throw new \RuntimeException("Photo временной загрузки {$file->id} отсутствует.");
        }

        $routeParams = array_merge($this->sessionRouteParams($session), ['id' => (int) $file->id]);
        $previewUrl = Url::toRoute(array_merge(['/photo-upload/view'], $routeParams));

        return [
            'id' => (int) $file->id,
            'photo_id' => (int) $file->photoId,
            'name' => (string) $file->originalName,
            'width' => (int) $photo->width,
            'height' => (int) $photo->height,
            'preview_url' => $previewUrl,
            'open_url' => $previewUrl,
            'thumbnail_url' => Url::toRoute(array_merge(['/photo-upload/thumbnail'], $routeParams)),
            'delete_url' => Url::toRoute(array_merge(['/photo-upload/delete'], $routeParams)),
        ];
    }

    /**
     * @return array{token:string, repoId:int, context:string}
     */
    private function sessionRouteParams(PhotoUploadSession $session): array
    {
        return [
            'token' => (string) $session->token,
            'repoId' => (int) $session->repoId,
            'context' => (string) $session->context,
        ];
    }

    private function getIdentity(): User
    {
        $identity = $this->getLoggedUser()->identity;
        if (!$identity instanceof User) {
            throw new \LogicException('Авторизованный пользователь не найден.');
        }

        return $identity;
    }
}
