<?php

namespace console\controllers;

use common\models\ItemPhoto;
use Yii;
use yii\console\Controller;

class ThumbnailsController extends Controller
{
    /**
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @throws \yii\base\Exception
     */
    public function actionCreate(int $width, int $height, bool $upscale, bool $crop, int $quality)
    {
        $counter = 0;
        $photos = ItemPhoto::find()->all();
        foreach ($photos as $photo) {
            if (!file_exists($photo->getThumbnailFile($width, $height, $upscale, $crop, $quality))) {
                echo "{$photo->id}: ";
                $photo->createThumbnail($width, $height, $upscale, $crop, $quality);
                $file = $photo->getThumbnailFile($width, $height, $upscale, $crop, $quality);
                echo "{$file} (" . filesize($file) . " bytes)\n";
                $counter++;
            }
        }

        if ($counter != 0) {
            echo "Generated {$counter} thumbnails {$width}x{$height}.\n";
        } else {
            echo "Nothing to do. All thumbnails already exists.\n";
        }
    }
}
