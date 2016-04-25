<?php

namespace common\models;

use common\components\ImageResize;
use Yii;
use yii\base\Exception;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Url;

/**
 * Фотография предмета
 *
 * @property int $id
 * @property int $itemId
 * @property string $md5
 * @property int $size
 * @property int $width
 * @property int $height
 * @property integer $sortIndex
 * @property integer $created
 * @property integer $updated
 *
 * @property Item $item
 */
class ItemPhoto extends ActiveRecord
{
    private $_assignedFile;
    private $_tempFile;
    private $_image;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'items_photos';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'created',
                'updatedAtAttribute' => 'updated',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['itemId'], 'required'],
            [['itemId', 'md5', 'size', 'width', 'height'], 'required'],
            [['itemId', 'size', 'width', 'height', 'sortIndex'], 'integer'],
            ['md5', 'string', 'length' => 32],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID фотографии',
            'itemId' => 'ID предмета',
            'md5' => 'MD5 содержимого файла',
            'size' => 'Размер файла',
            'width' => 'Ширина фотографии',
            'height' => 'Высота фотографии',
            'sortIndex' => 'Порядковый номер',
            'created' => 'Время создания',
            'updated' => 'Время последнего изменения',
        ];
    }

    public function __destruct()
    {
        if ($this->_tempFile !== null) {
            @unlink($this->_tempFile);
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getItem()
    {
        return $this->hasOne(Item::className(), ['id' => 'itemId']);
    }

    /**
     * @inheritdoc
     * @return ItemPhotoQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ItemPhotoQuery(get_called_class());
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                if (! $this->_assignedFile) {
                    throw new Exception('File must be assigned before save');
                }
                $maxSortIndex = (new Query())
                    ->select('MAX(sortIndex)')
                    ->from(self::tableName())
                    ->where('itemId = :itemId', ['itemId' => $this->itemId])
                    ->scalar();
                $this->sortIndex = $maxSortIndex !== null ? $maxSortIndex + 1 : 0;
            }
            return true;
        } else {
            return false;
        }
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert) {
            $file = $this->getFile();
            $dir = dirname($file);
            if (!file_exists($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new Exception('Failed to create directory "' . $dir . '"');
            }
            if (!rename($this->_tempFile, $file)) {
                throw new Exception('Failed to move photo file from "' . $this->_tempFile . '" to "' . $file);
            }
        }
    }

    public function afterDelete()
    {
        parent::afterDelete();
        @unlink($this->getFile());
    }

    /**
     * @param $file
     * @throws Exception
     */
    public function assignFile($file)
    {
        if (! file_exists($file)) {
            throw new Exception('File "' . $file . '" doesn\'t exists"');
        }
        if (! is_readable($file)) {
            throw new Exception('File "' . $file . '" can\'t be read');
        }
        $this->_assignedFile = $file;

        $this->_image = ImageResize::resizeImage(
            ImageResize::getImageFromFile($this->_assignedFile),
            Yii::$app->params['photos']['resize']['width'],
            Yii::$app->params['photos']['resize']['height'],
            Yii::$app->params['photos']['resize']['upscale'],
            Yii::$app->params['photos']['resize']['crop']
        );

        $this->_tempFile = tempnam(Yii::$app->params['photos']['storageTemp'], 'inv');

        imagejpeg($this->_image, $this->_tempFile, Yii::$app->params['photos']['resize']['quality']);

        if (($md5 = md5_file($this->_tempFile)) === false) {
            throw new Exception('Failed to calculate MD5 sum of file "' . $this->_tempFile . '"');
        }
        $this->md5 = $md5;
        if (($size = @filesize($this->_tempFile)) === false) {
            throw new Exception('Failed to get file size of file "' . $this->_tempFile . '"');
        }
        $this->size = $size;

        $this->width = imagesx($this->_image);
        $this->height = imagesy($this->_image);
    }

    /**
     * Возвращает относительный путь к файлу на диске относительно корня хранилища полноразмерных фотографий
     *
     * @param int $id
     * @return string
     */
    private static function getFileRelativePath(int $id)
    {
        $hash = md5(Yii::$app->params['photos']['md5salt'] . $id);
        $hash = substr_replace($hash, '/', 2, 0);
        $hash = substr_replace($hash, '/', 5, 0);
        return $hash . '.jpg';
    }

    /**
     * Возвращает относительный путь к уменьшенному файлу на диске относительно корня thumbnails
     *
     * @param int $id
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @return string
     */
    private static function getThumbnailFileRelativePath(int $id, int $width, int $height, bool $upscale, bool $crop, int $quality)
    {
        $suffixes = ["q{$quality}"];
        if ($upscale) {
            $suffixes[] = 'upscale';
        }
        if ($crop) {
            $suffixes[] = 'crop';
        }
        $path = [];
        $path[] = "{$width}x{$height}." . implode('.', $suffixes);
        $path[] = self::getFileRelativePath($id);
        return implode('/', $path);
    }

    /**
     * Возвращает абсолютный путь к файлу с полноразмерной фотографией на диске по ID фотографии
     *
     * @param int $id
     * @return string
     */
    public static function getFileById(int $id)
    {
        return Yii::$app->params['photos']['storagePath'] . '/' . self::getFileRelativePath($id);
    }

    /**
     * Возвращает абсолютный путь к файлу с полноразмерной фотографией на диске для текущей фотографии
     *
     * @return string
     */
    public function getFile()
    {
        return self::getFileById($this->primaryKey);
    }

    /**
     * Возвращает абсолютный путь к файлу с уменьшенной фотографией на диске по ID фотографии и параметрам уменьшения
     *
     * @param int $id
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @return string
     */
    public static function getThumbnailFileById(int $id, int $width, int $height, bool $upscale, bool $crop, int $quality)
    {
        return Yii::$app->params['photos']['thumbnailPath'] . '/' . self::getThumbnailFileRelativePath($id, $width, $height, $upscale, $crop, $quality);
    }

    /**
     * Возвращает абсолютный путь к файлу с уменьшенной фотографией на диске для текущей фотографии
     *
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @return string
     */
    public function getThumbnailFile(int $width, int $height, bool $upscale, bool $crop, int $quality)
    {
        return self::getThumbnailFileById($this->primaryKey, $width, $height, $upscale, $crop, $quality);
    }

    /**
     * Возвращает URL полноразмерной фотографии
     *
     * @return string
     */
    public function getUrl()
    {
        $urlParts = [];
        $urlParts[] = Yii::$app->request->baseUrl;
        $storageRelativeUrl = trim(Yii::$app->params['photos']['storageRelativeUrl'], '/');
        if ($storageRelativeUrl !== '') {
            $urlParts[] = $storageRelativeUrl;
        }
        $urlParts[] = self::getFileRelativePath($this->primaryKey);
        return implode('/', $urlParts);
    }

    /**
     * Возвращает строго статический URL уменьшенной фотографии
     * 
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @return mixed
     */
    public function getStaticThumbnailUrl(int $width, int $height, bool $upscale, bool $crop, int $quality)
    {
        $urlParts = [];
        $urlParts[] = Yii::$app->request->baseUrl;
        $storageRelativeUrl = trim(Yii::$app->params['photos']['thumbnailRelativeUrl'], '/');
        if ($storageRelativeUrl !== '') {
            $urlParts[] = $storageRelativeUrl;
        }
        $urlParts[] = self::getThumbnailFileRelativePath($this->primaryKey, $width, $height, $upscale, $crop, $quality);
        return implode('/', $urlParts);
    }

    /**
     * Возвращает URL уменьшенной фотографии. При этом, если уменьшенная фотография на диске
     * есть, то возвращает ссылку на статику, отдаваемую веб-сервером напрямую без участия PHP.
     * Если же уменьшенной фотографии нет, то возвращает ссылку на action, который генерирует 
     * уменьшенную фотографию на диске и редиректит 
     *
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @return string
     * @throws \yii\base\InvalidParamException
     */
    public function getThumbnailUrl(int $width, int $height, bool $upscale, bool $crop, int $quality)
    {
        if (file_exists($this->getThumbnailFile($width, $height, $upscale, $crop, $quality))) {
            return $this->getStaticThumbnailUrl($width, $height, $upscale, $crop, $quality);
        } else {
            return Url::toRoute(['photo/thumbnail', 'id' => $this->primaryKey, 'width' => $width, 'height' => $height, 'upscale' => $upscale, 'crop' => $crop, 'quality' => $quality]);
        }
    }

    /**
     * Создает файл с уменьшенной фотографией
     *
     * @param int $width
     * @param int $height
     * @param bool $upscale
     * @param bool $crop
     * @param int $quality
     * @throws Exception
     */
    public function createThumbnail(int $width, int $height, bool $upscale, bool $crop, int $quality)
    {
        $thumbnailFile = $this->getThumbnailFile($width, $height, $upscale, $crop, $quality);

        $image = ImageResize::getImageFromFile(ItemPhoto::getFileById($this->primaryKey));
        $image = ImageResize::resizeImage($image, $width, $height, $upscale, $crop);

        $dir = dirname($thumbnailFile);
        if (!file_exists($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Exception('Failed to create directory "' . $dir . '"');
        }

        $tempFile = tempnam(Yii::$app->params['photos']['thumbnailTemp'], $this->primaryKey);

        if (file_put_contents($tempFile, ImageResize::getImageJPEG($image, $quality)) === false) {
            throw new Exception('Failed to create thumbnail file "' . $thumbnailFile . '"');
        }

        if (!rename($tempFile, $thumbnailFile)) {
            @unlink($tempFile);
            throw new Exception('Failed to move temporary file "' . $tempFile . '" to "' . $thumbnailFile . '"');
        }
        @unlink($tempFile);
    }
}
