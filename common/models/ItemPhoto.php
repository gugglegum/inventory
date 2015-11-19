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
            if (! file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            if (! copy($this->_tempFile, $file)) {
                throw new Exception('Failed to copy file "' . $this->_tempFile . '" to folder "' . $file);
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

        $this->_image = ImageResize::resizeImage(ImageResize::getImageFromFile($this->_assignedFile), [
            'width'         => Yii::$app->params['photos']['resize']['width'],
            'height'        => Yii::$app->params['photos']['resize']['height'],
            'antiAliasing'  => Yii::$app->params['photos']['resize']['antiAliasing'],
            'upscale'       => Yii::$app->params['photos']['resize']['upscale'],
            'crop'          => Yii::$app->params['photos']['resize']['crop'],
        ]);

        $this->_tempFile = tempnam(sys_get_temp_dir(), 'inv');

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

    private static function _getFileRelativePath($id)
    {
        $sum = abs(crc32((string) $id));
        $path = [];
        $path[] = str_pad($sum % 100, 2, '0', STR_PAD_LEFT);
        $path[] = str_pad((int) floor($sum / 100) % 100, 2, '0', STR_PAD_LEFT);
        return implode('/', $path) . '/' . $id . '.jpg';
    }

    public static function getFileById($id)
    {
        return Yii::$app->params['photos']['storagePath'] . '/' . self::_getFileRelativePath($id);
    }

    public function getFile()
    {
        return self::getFileById($this->primaryKey);
    }

    public function getUrl()
    {
        $urlParts = [];
        $urlParts[] = Yii::$app->request->baseUrl;
        if (Yii::$app->params['photos']['storageRelativeUrl'] !== '') {
            $urlParts[] = Yii::$app->params['photos']['storageRelativeUrl'];
        }
        $urlParts[] = self::_getFileRelativePath($this->primaryKey);
        return implode('/', $urlParts);
    }

    public function getThumbnailUrl($width, $height, array $options = [])
    {
        return Url::toRoute(['photo/thumbnail', 'id' => $this->primaryKey, 'width' => $width, 'height' => $height] + $options);
    }
}
