<?php
namespace paw\storage\models;

use paw\behaviors\IpBehavior;
use paw\behaviors\TimestampBehavior;
use paw\storage\models\FileMap;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\helpers\Url;
use yii\web\UploadedFile;

class File extends ActiveRecord
{
    const SCENARIO_USE = 'scenario_use';

    public $file;

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
            BlameableBehavior::class,
            IpBehavior::class,
        ];
    }

    public static function tableName()
    {
        return '{{%file}}';
    }

    public function rules()
    {
        return [
            [['file'], 'required', 'on' => 'default'],
            [['file'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg, bmp, gif', 'on' => 'default'],
            [['file'], 'safe', 'on' => self::SCENARIO_USE],
            [['name', 'extension', 'type', 'mode', 'path', 'url'], 'string'],
            [['size'], 'integer'],
            [['is_dummy'], 'boolean'],
        ];
    }

    // public function beforeSave($insert)
    // {
    //     if (!parent::beforeSave($insert)) return false;

    //     $this->file = UploadedFile::getInstanceByName('file');

    //     return true;
    // }

    public function beforeValidate()
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        $this->file = UploadedFile::getInstanceByName('file');
        return true;
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $fileObject = $this->file;

            $extension = $fileObject->extension;
            $name = $fileObject->name;
            $size = $fileObject->size;
            $type = $fileObject->type;

            $uploadDir = $this->getDummyPath();
            $url = '/upload';

            do {
                $fileName = uniqid() . '.' . $extension;
                $uploadPath = $uploadDir . '/' . $fileName;
            } while (file_exists($uploadPath));

            if (!$fileObject->saveAs($uploadPath)) {
                return false;
            }

            $this->extension = $extension;
            $this->name = $name;
            $this->size = $size;
            $this->type = $type;
            $this->filename = $fileName;
            $this->path = $uploadDir;
            $this->url = $url;
        }

        return true;
    }

    public function fields()
    {
        return [
            'id' => 'id',
            'name' => 'name',
            'size' => 'size',
            'extension' => 'extension',
            'type' => 'type',
            'link' => function ($model, $field) {
                return (string) $model;
            },
        ];
    }

    public function getStorageUrl()
    {
        return dirname(URL::base(true));
    }

    public function getDummyPath()
    {
        return PATH_BASE . '/dist/upload';
    }

    public function getStoragePath()
    {
        return PATH_BASE . '/dist/storage';
    }

    public function getLink()
    {
        $storageBaseUrl = $this->getStorageUrl();
        $urlPath = ltrim($this->url, '/');
        $fileName = $this->filename;
        return $storageBaseUrl . '/' . $urlPath . '/' . $fileName;
    }

    public function getFilePath()
    {
        return $this->path . '/' . $this->filename;
    }

    public function toString()
    {
        return $this->getLink();
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function usefile(BaseActiveRecord $model, $attribute)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->scenario = self::SCENARIO_USE;

            $data = [
                'file_id' => $this->id,
                'model_class' => get_class($model),
                'model_id' => $model->id,
                'model_attribute' => $attribute,
            ];

            if (FileMap::find()->andWhere($data)->exists()) {
                return true;
            }

            $map = new FileMap([
                'file_id' => $this->id,
                'model_class' => get_class($model),
                'model_id' => $model->id,
                'model_attribute' => $attribute,
            ]);

            if (!$map->save()) {
                $transaction->rollBack();
                return false;
            }

            $oldFile = $this->getFilePath();
            $storageDir = $this->getStoragePath();

            do {
                $fileName = uniqid() . '.' . $this->extension;
                $movePath = $storageDir . '/' . $fileName;
            } while (file_exists($movePath));

            if (!copy($oldFile, $movePath)) {
                $transaction->rollBack();
                return false;
            }

            $this->filename = $fileName;
            $this->path = $storageDir;
            $this->url = '/storage';
            $this->is_dummy = false;

            if (!$this->save()) {
                $transaction->rollBack();
                return false;
            }

            unlink($oldFile);

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            throw $ex;
            // if (YII_DEBUG) throw $ex;
            return false;
        }
    }
}
