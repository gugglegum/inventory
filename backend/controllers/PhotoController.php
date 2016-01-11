<?php

namespace backend\controllers;

use common\components\ImageResize;
use common\models\ItemPhoto;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

class PhotoController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Возвращает уменьшенное изображение в JPEG
     *
     * @param $id
     * @param $width
     * @param $height
     * @param null $antiAliasing
     * @param null $upscale
     * @param null $crop
     * @param int $quality
     * @return string
     * @throws HttpException
     * @throws \yii\base\Exception
     */
    public function actionThumbnail($id, $width, $height, $antiAliasing = null, $upscale = null, $crop = null, $quality = 90)
    {
        /** @var ItemPhoto $photo */
        $photo = ItemPhoto::findOne($id);
        if (! $photo) {
            throw new HttpException(404, 'Photo #' . $id . ' is not found');
        }

        $image = ImageResize::getImageFromFile(ItemPhoto::getFileById($id));
        $resizeParams = array_filter([
            'width' => $width,
            'height' => $height,
            'antiAliasing' => $antiAliasing,
            'upscale' => $upscale,
            'crop' => $crop,
        ], function($value) {
            return $value !== null;
        });

        $image = ImageResize::resizeImage($image, $resizeParams);
        Yii::$app->response->headers
            ->add('Content-Type', 'image/jpeg')
            ->add('Expires', gmdate('D, d M Y H:i:s', time() + 86400 * 7) . ' GMT');
        Yii::$app->response->format = Response::FORMAT_RAW;
        return ImageResize::getImageJPEG($image, $quality);
    }

    public function actionSortUp()
    {
        $id = Yii::$app->request->post('id');
        if (!$id) {
            throw new HttpException(400, 'Missing required parameter "id"');
        }

        /** @var ItemPhoto $photo */
        $photo = ItemPhoto::findOne($id);
        if (! $photo instanceof ItemPhoto) {
            throw new HttpException(404, 'Photo #' . $id . ' is not found');
        }

        /** @var ItemPhoto $prevPhoto */
        $prevPhoto = ItemPhoto::find()
            ->where('itemId = :itemId', ['itemId' => $photo->itemId])
            ->andWhere('sortIndex < :sortIndex', ['sortIndex' => $photo->sortIndex])
            ->orderBy(['sortIndex' => SORT_DESC])
            ->limit(1)
            ->one();

        if ($prevPhoto instanceof ItemPhoto) {
            $transaction = ItemPhoto::getDb()->beginTransaction();
            $prevSortIndex = $prevPhoto->sortIndex;
            $prevPhoto->sortIndex = -1;
            $prevPhoto->save();
            $prevPhoto->sortIndex = $photo->sortIndex;
            $photo->sortIndex = $prevSortIndex;
            $photo->save();
            $prevPhoto->save();
            $transaction->commit();
        }
    }

    public function actionSortDown()
    {
        $id = Yii::$app->request->post('id');
        if (!$id) {
            throw new HttpException(400, 'Missing required parameter "id"');
        }

        /** @var ItemPhoto $photo */
        $photo = ItemPhoto::findOne($id);
        if (! $photo instanceof ItemPhoto) {
            throw new HttpException(404, 'Photo #' . $id . ' is not found');
        }

        /** @var ItemPhoto $prevPhoto */
        $nextPhoto = ItemPhoto::find()
            ->where('itemId = :itemId', ['itemId' => $photo->itemId])
            ->andWhere('sortIndex > :sortIndex', ['sortIndex' => $photo->sortIndex])
            ->orderBy(['sortIndex' => SORT_ASC])
            ->limit(1)
            ->one();

        if ($nextPhoto instanceof ItemPhoto) {
            $transaction = ItemPhoto::getDb()->beginTransaction();
            $nextSortIndex = $nextPhoto->sortIndex;
            $nextPhoto->sortIndex = -1;
            $nextPhoto->save();
            $nextPhoto->sortIndex = $photo->sortIndex;
            $photo->sortIndex = $nextSortIndex;
            $photo->save();
            $nextPhoto->save();
            $transaction->commit();
        }
    }

    public function actionDelete()
    {
        $id = Yii::$app->request->post('id');
        if (!$id) {
            throw new HttpException(400, 'Missing required parameter "id"');
        }

        /** @var ItemPhoto $photo */
        $photo = ItemPhoto::findOne($id);
        if ($photo instanceof ItemPhoto) {
            $photo->delete();
        }
    }
}
