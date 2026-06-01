<?php

namespace console\controllers;

use common\models\ItemPhoto;
use Yii;
use yii\base\Exception;
use yii\console\Controller;

/**
 * Одноразовый консольный скрипт для переноса коллекции фотографий на новую структуру директорий и имена файлов.
 * Новая структура более защищенная и не позволяет путем перебора найти URL всех фотографий. Если вы начали
 * загружать фотографии после того, как этот скрипт и соответствующее обновление кода появилось в репозитории,
 * то запускать его вам не нужно.
 */
class PhotoController extends Controller
{
    private static function getOldFileRelativePath(int $id)
    {
        $crc32 = crc32((string) $id);
        // backward compatibility trick for int64 systems (e.g. php7 for win64)
        if ($crc32 > 2147483647) {
            $crc32 -= 4294967296;
        }
        $sum = abs($crc32);
        $path = [];
        $path[] = str_pad($sum % 100, 2, '0', STR_PAD_LEFT);
        $path[] = str_pad(intdiv($sum, 100) % 100, 2, '0', STR_PAD_LEFT);
        return implode('/', $path) . '/' . $id . '.jpg';
    }

    public static function getNewFileRelativePath(int $id)
    {
        $hash = md5(Yii::$app->params['photos']['md5salt'] . $id);
        $hash = substr_replace($hash, '/', 2, 0);
        $hash = substr_replace($hash, '/', 5, 0);
        return $hash . '.jpg';
    }

    public function actionRelocate()
    {
        $counter = 0;
        $photos = ItemPhoto::find()->all();
        echo 'Moving ' . count($photos) . " photos\n";
        foreach ($photos as $photo) {
            $oldFile = Yii::$app->params['photos']['storagePath'] . '/' . self::getOldFileRelativePath($photo->id);

            if (!file_exists($oldFile)) {
                throw new Exception('Missing file of photo #' . $photo->id);
            }

            $newFile = Yii::$app->params['photos']['storagePath'] . '_new/' . self::getNewFileRelativePath($photo->id);
            $newDir = dirname($newFile);

            if (!file_exists($newDir) && !@mkdir($newDir, 0777, true) && !is_dir($newDir)) {
                throw new Exception('Failed to create directory "' . $newDir . '"');
            }

            if (!rename($oldFile, $newFile)) {
                throw new Exception('Failed to move file from "' . $photo->getFile() . '" to "' . $newFile . '"');
            }
            $counter++;
            if ($counter % 10 == 0) {
                echo '.';
            }
        }
        echo "\nWell done!\n";
    }
}
