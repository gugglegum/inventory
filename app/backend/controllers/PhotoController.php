<?php

namespace backend\controllers;

use backend\services\PhotoAccessService;
use backend\services\PhotoDeliveryService;
use common\models\Photo;
use Yii;
use yii\base\Exception;
use yii\filters\AccessControl;
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
