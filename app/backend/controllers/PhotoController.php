<?php

namespace backend\controllers;

use backend\services\PhotoAccessService;
use backend\services\PhotoAttachmentService;
use backend\services\PhotoDeliveryService;
use common\models\Photo;
use InvalidArgumentException;
use Yii;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Управление фотографиями товаров
 *
 * @package backend\controllers
 */
class PhotoController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'sort-up' => ['post'],
                    'sort-down' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Отдает оригинал фотографии после проверки доступа к связанному репозиторию.
     */
    public function actionView(int $id): Response
    {
        $photo = $this->findAccessiblePhoto($id);
        if (!is_file($photo->getFile())) {
            throw new NotFoundHttpException('Файл фотографии не найден.');
        }

        return (new PhotoDeliveryService())->original($photo);
    }

    /**
     * Возвращает уменьшенную фотографию предмета
     *
     * Пример запроса:
     *
     * GET /photo/thumbnail?id=123&width=
     *
     * @param int $id
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @return Response
     * @throws Exception
     * @throws HttpException
     */
    public function actionThumbnail(int $id, int $width, int $height, bool $upscale, bool $crop, int $quality): Response
    {
        $photo = $this->findAccessiblePhoto($id);

        $thumbnailFile = $photo->getThumbnailFile($width, $height, $upscale, $crop, $quality);

        if (!file_exists($thumbnailFile)) {
            $photo->createThumbnail($width, $height, $upscale, $crop, $quality);
        }

        return (new PhotoDeliveryService())->thumbnail($photo, $width, $height, $upscale, $crop, $quality);
    }

    /**
     * Перемещает фотографию предмета в списке фотографий на одну позицию вверх
     *
     * @throws HttpException
     * @throws \yii\db\Exception
     */
    public function actionSortUp(): void
    {
        $this->runPhotoOperation(
            fn(PhotoAttachmentService $service, int $id, string $type): bool => $service->sortUp($id, $type)
        );
    }

    /**
     * Перемещает фотографию предмета в списке фотографий на одну позицию внизу
     *
     * @throws HttpException
     * @throws \yii\db\Exception
     */
    public function actionSortDown(): void
    {
        $this->runPhotoOperation(
            fn(PhotoAttachmentService $service, int $id, string $type): bool => $service->sortDown($id, $type)
        );
    }

    /**
     * Удаляет фотографию
     *
     * @return void
     * @throws HttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete(): void
    {
        $this->runPhotoOperation(
            fn(PhotoAttachmentService $service, int $id, string $type): bool => $service->delete($id, $type)
        );
    }

    /**
     * Выполняет POST-операцию над связью фотографии.
     *
     * @param callable(PhotoAttachmentService, int, string): bool $operation
     * @throws HttpException
     * @throws \Throwable
     */
    private function runPhotoOperation(callable $operation): void
    {
        $id = (int) Yii::$app->request->post('id');
        if (!$id) {
            throw new HttpException(400, 'Missing required parameter "id"');
        }

        try {
            $isFound = $operation(new PhotoAttachmentService(), $id, $this->getPhotoType());
        } catch (InvalidArgumentException $exception) {
            throw new HttpException(400, $exception->getMessage(), 0, $exception);
        }

        if (!$isFound) {
            throw new HttpException(404, 'Photo attachment #' . $id . ' is not found');
        }
    }

    /**
     * Возвращает тип связи фотографии из POST.
     */
    private function getPhotoType(): string
    {
        return (string) Yii::$app->request->post('photoType', PhotoAttachmentService::TYPE_ITEM);
    }

    /**
     * Находит фотографию и проверяет право текущего пользователя на ее репозиторий.
     */
    private function findAccessiblePhoto(int $id): Photo
    {
        $photo = Photo::findOne($id);
        if ($photo === null) {
            throw new NotFoundHttpException('Фотография не найдена.');
        }

        $userId = (int) Yii::$app->getUser()->id;
        if (!(new PhotoAccessService())->canView($photo, $userId)) {
            throw new ForbiddenHttpException('У вас нет доступа к этой фотографии.');
        }

        return $photo;
    }
}
