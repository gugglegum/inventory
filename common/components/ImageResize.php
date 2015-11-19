<?php

namespace common\components;

//use Yii;
//use yii\base\Component;
//use frontend\models\CorvusPayForm;
//use common\models\Order;

use yii\base\Exception;

abstract class ImageResize
{
    public static function getImageJPEG($image, $JPEGQuality = 75)
    {
        ob_start();
        imagejpeg($image, null, $JPEGQuality);
        $image = ob_get_contents();
        ob_end_clean();
        return $image;
    }

    public static function getImageFromFile($imagePath)
    {
        if (!is_file($imagePath)) {
            throw new Exception('Image file is not found');
        }
        if ($info = getimagesize($imagePath)) {
            switch ($info[2]) {
                case 1 :
                    if (!($image = @imagecreatefromgif($imagePath))) {
                        throw new Exception('Unable to open image file (GIF)');
                    }
                    break;
                case 2 :
//                    if (!($image = @imagecreatefromjpeg($imagePath))) {
                    if (!($image = @self::_imagecreatefromjpegexif($imagePath))) {
                        throw new Exception('Unable to open image file (JPEG)');
                    }
                    break;
                case 3 :
                    if (!($image = @imagecreatefrompng($imagePath))) {
                        throw new Exception('Unable to open image file (PNG)');
                    }
                    break;
                default :
                    throw new Exception('Unsupported image format');
            }
            return $image;
        } else {
            throw new Exception('Corrupted image or unknown format');
        }
    }

    private static function _imagecreatefromjpegexif($filename)
    {
        $img = imagecreatefromjpeg($filename);
        if ($img) {
            $exif = exif_read_data($filename);
            if ($exif && isset($exif['Orientation'])) {
                $ort = $exif['Orientation'];
                if ($ort == 6 || $ort == 5) {
                    $img = imagerotate($img, 270, null);
                }
                if ($ort == 3 || $ort == 4) {
                    $img = imagerotate($img, 180, null);
                }
                if ($ort == 8 || $ort == 7) {
                    $img = imagerotate($img, 90, null);
                }
                if ($ort == 5 || $ort == 4 || $ort == 7) {
                    imageflip($img, IMG_FLIP_HORIZONTAL);
                }
            }
        }
        return $img;
    }

    public static function resizeImage($image, array $params)
    {
        $defaultParams = [
            'antiAliasing' => true,
            'upscale' => false,
            'crop' => false,
        ];

        $params += $defaultParams;

        $s_img_x = imagesx($image);
        $s_img_y = imagesy($image);
        if ($params['upscale'] || $params['crop']) {
            $kx = $params['width'] > 0 ? $s_img_x / $params['width'] : 1;
            $ky = $params['height'] > 0 ? $s_img_y / $params['height'] : 1;
        } else {
            $kx = ($params['width'] > 0) && ($s_img_x > $params['width']) ? $s_img_x / $params['width'] : 1;
            $ky = ($params['height'] > 0) && ($s_img_y > $params['height']) ? $s_img_y / $params['height'] : 1;
        }
        $k = $params['crop'] ? min($kx, $ky) : max($kx, $ky);
        $d_img_x = round($s_img_x / $k);
        $d_img_y = round($s_img_y / $k);

        if ($d_img = imagecreatetruecolor($d_img_x, $d_img_y)) {
            if ($params['antiAliasing']) {
                imagecopyresampled($d_img, $image, 0, 0, 0, 0, $d_img_x, $d_img_y, $s_img_x, $s_img_y);
            } else {
                imagecopyresized($d_img, $image, 0, 0, 0, 0, $d_img_x, $d_img_y, $s_img_x, $s_img_y);
            }
            imagedestroy($image);
            return $params['crop'] ? self::cropImageCenter($d_img, $params['width'], $params['height']) : $d_img;
        } else {
            throw new Exception('Can\'t create new image [imagecreatetruecolor()]');
        }
    }

    public static function cropImageCenter($srcImage, $width, $height)
    {
        $dstImage = imagecreatetruecolor($width, $height);
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        imagecopy($dstImage, $srcImage, 0, 0, (int) ($srcWidth - $width) / 2, (int) ($srcHeight - $height) / 2, $width, $height);
        return $dstImage;
    }
}
