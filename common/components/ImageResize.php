<?php

namespace common\components;

use yii\base\Exception;

abstract class ImageResize
{
    /**
     * @param \GdImage $image
     * @param int $JPEGQuality
     * @return string
     */
    public static function getImageJPEG(\GdImage $image, int $JPEGQuality = 75): string
    {
        ob_start();
        imagejpeg($image, null, $JPEGQuality);
        $image = ob_get_contents();
        ob_end_clean();
        return $image;
    }

    /**
     * @param string $imagePath
     * @return \GdImage
     * @throws Exception
     */
    public static function getImageFromFile(string $imagePath): \GdImage
    {
        if (!is_file($imagePath)) {
            throw new Exception("Image file '{$imagePath}' is not found");
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
                case 18 :
                    if (!($image = @imagecreatefromwebp($imagePath))) {
                        throw new Exception('Unable to open image file (WebP)');
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

    /**
     * @param string $filename
     * @return \GdImage
     */
    private static function _imagecreatefromjpegexif(string $filename): \GdImage
    {
        $img = imagecreatefromjpeg($filename);
        if ($img) {
            $exif = exif_read_data($filename);
            if ($exif && isset($exif['Orientation'])) {
                $ort = $exif['Orientation'];
                if ($ort == 6 || $ort == 5) {
                    $img = imagerotate($img, 270, 0);
                }
                if ($ort == 3 || $ort == 4) {
                    $img = imagerotate($img, 180, 0);
                }
                if ($ort == 8 || $ort == 7) {
                    $img = imagerotate($img, 90, 0);
                }
                if ($ort == 5 || $ort == 4 || $ort == 7) {
                    imageflip($img, IMG_FLIP_HORIZONTAL);
                }
            }
        }
        return $img;
    }

    /**
     * @param \GdImage $image
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @return \GdImage
     * @throws Exception
     */
    public static function resizeImage(\GdImage $image, int $width, int $height, bool $upscale, bool $crop): \GdImage
    {
        $s_img_x = imagesx($image);
        $s_img_y = imagesy($image);
        if ($upscale || $crop) {
            $kx = $width > 0 ? $s_img_x / $width : 1;
            $ky = $height > 0 ? $s_img_y / $height : 1;
        } else {
            $kx = ($width > 0) && ($s_img_x > $width) ? $s_img_x / $width : 1;
            $ky = ($height > 0) && ($s_img_y > $height) ? $s_img_y / $height : 1;
        }
        $k = $crop ? min($kx, $ky) : max($kx, $ky);
        $d_img_x = round($s_img_x / $k);
        $d_img_y = round($s_img_y / $k);

        if ($d_img = imagecreatetruecolor($d_img_x, $d_img_y)) {
            imagecopyresampled($d_img, $image, 0, 0, 0, 0, $d_img_x, $d_img_y, $s_img_x, $s_img_y);
            imagedestroy($image);
            return $crop ? self::cropImageCenter($d_img, $width, $height) : $d_img;
        } else {
            throw new Exception('Can\'t create new image with imagecreatetruecolor()');
        }
    }

    /**
     * @param \GdImage $srcImage
     * @param int $width
     * @param int $height
     * @return \GdImage
     */
    public static function cropImageCenter(\GdImage $srcImage, int $width, int $height): \GdImage
    {
        $dstImage = imagecreatetruecolor($width, $height);
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        imagecopy($dstImage, $srcImage, 0, 0, ($srcWidth - $width) / 2, ($srcHeight - $height) / 2, $width, $height);
        return $dstImage;
    }
}
