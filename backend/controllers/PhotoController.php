<?php

namespace backend\controllers;

use common\models\ItemPhoto;
use common\models\Photo;
use Yii;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\HttpException;
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
                ],
            ],
        ];
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
        /** @var Photo $photo */
        $photo = Photo::findOne($id);
        if (! $photo) {
            throw new HttpException(404, 'Photo #' . $id . ' is not found');
        }

        $staticThumbnailUrl = $photo->getStaticThumbnailUrl($width, $height, $upscale, $crop, $quality);
        $thumbnailFile = $photo->getThumbnailFile($width, $height, $upscale, $crop, $quality);

        if (!file_exists($thumbnailFile)) {
            $photo->createThumbnail($width, $height, $upscale, $crop, $quality);
        }
//        session_cache_limiter('private_no_expire');
        header_remove('Pragma');
        Yii::$app->getResponse()->getHeaders()
            ->set('Expires', gmdate('D, d M Y H:i:s', time() + 86400 * 7) . ' GMT');
        return $this->redirect($staticThumbnailUrl);
    }

    /**
     * Перемещает фотографию предмета в списке фотографий на одну позицию вверх
     *
     * @throws HttpException
     * @throws \yii\db\Exception
     */
    public function actionSortUp(): void
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
            $this->swapSortIndexes($photo, $prevPhoto);
        }
    }

    /**
     * Перемещает фотографию предмета в списке фотографий на одну позицию внизу
     *
     * @throws HttpException
     * @throws \yii\db\Exception
     */
    public function actionSortDown(): void
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
            $this->swapSortIndexes($photo, $nextPhoto);
        }
    }

    /**
     * @param ItemPhoto $photo1
     * @param ItemPhoto $photo2
     * @return void
     * @throws \yii\db\Exception
     */
    private function swapSortIndexes(ItemPhoto $photo1, ItemPhoto $photo2): void
    {
        $transaction = ItemPhoto::getDb()->beginTransaction();
        $prevSortIndex = $photo2->sortIndex;
        $photo2->sortIndex = -1;
        $photo2->save();
        $photo2->sortIndex = $photo1->sortIndex;
        $photo1->sortIndex = $prevSortIndex;
        $photo1->save();
        $photo2->save();
        $transaction->commit();
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
