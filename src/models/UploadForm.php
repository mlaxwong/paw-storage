<?php
namespace paw\storage\models;

use paw\storage\models\File;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class UploadForm extends Model
{
    const MODE_ONE = 'one';
    const MODE_MULTIPLE = 'multiple';

    public $file;

    public $extensions = null;

    public $maxFiles = null;

    public $mode = self::MODE_ONE;

    public $uploadDir = null;

    public $url = null;

    public $fileParam = 'file';

    public function init()
    {
        parent::init();

        if ($this->uploadDir === null) {
            throw new InvalidConfigException(Yii::t('app', 'Configure \'{configure}\' is required', ['configure' => 'uploadDir']));
        }

        // if (!file_exists(Yii::getAlias($this->uploadDir))) {
        //     throw new InvalidConfigException(Yii::t('app', 'Directory \'{path}\' not exists', ['path' => Yii::getAlias($this->uploadDir)]));
        // }

        if (!in_array($this->mode, [self::MODE_ONE, self::MODE_MULTIPLE])) {
            throw new InvalidConfigException(Yii::t('app', 'Invaild upload mode'));
        }
    }

    public function rules()
    {
        return [
            ArrayHelper::merge(
                [['file'], 'file', 'skipOnEmpty' => false, 'extensions' => $this->extensions, 'checkExtensionByMimeType' => false],
                $this->maxFiles === null ? [] : ['maxFiles' => $this->maxFiles]
            ),
        ];
    }

    public function beforeValidate()
    {
        $this->file = $this->mode == self::MODE_ONE ? UploadedFile::getInstanceByName($this->fileParam) : UploadedFile::getInstancesByName($this->fileParam);
        return parent::beforeValidate();
    }

    public function upload()
    {
        if (!$this->validate()) {
            return false;
        }

        try {
            $fileModel = null;
            $transaction = Yii::$app->db->beginTransaction();
            if ($this->mode == self::MODE_MULTIPLE) {
                $fileModel = [];
                foreach ($this->file as $file) {
                    if (!$model = $this->uploadFile($file)) {
                        $transaction->rollBacK();
                        return false;
                    }
                    $fileModel[] = $model;
                }
            } else {
                if (!$fileModel = $this->uploadFile($this->file)) {
                    $transaction->rollBacK();
                    return false;
                }
            }

            $transaction->commit();
            return $fileModel;
        } catch (\Exception $ex) {
            $transaction->rollBacK();
            if (YII_DEBUG) {
                throw $ex;
            }

            return false;
        }
    }

    protected function uploadFile($file)
    {
        $uploadDir = Yii::getAlias($this->uploadDir);
        $url = $this->url;
        $originalName = $file->baseName;
        $extension = $file->extension;
        $uploadName = $this->getUploadName($uploadDir, $extension);
        $type = $file->type;
        $size = $file->size;

        $dist = "$uploadDir/$uploadName.$extension";

        // create directory if not exists
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                return false;
            }
        }

        if (!$file->saveAs($dist)) {
            return false;
        }

        $model = new File([
            'path' => $uploadDir,
            'url' => $url,
            'filename' => "$uploadName.$extension",
            'name' => "$originalName.$extension",
            'extension' => $extension,
            'type' => $type,
            'size' => $size,
        ]);
        $this->beforeFileModelSave($model);
        if (!$model->save()) {
            if (YII_DEBUG) {
                throw new \Exception(print_r($model->errors, 1));
            }

            return false;
        }

        return $model;
    }

    protected function getUploadName($uploadDir, $extension)
    {
        $uploadName = null;
        do {
            $uploadName = uniqid();
            $dist = "$uploadDir/$uploadName.$extension";
        } while (file_exists($dist) || File::find()->andWhere(['filename' => "$uploadName.$extension"])->exists());
        return $uploadName;
    }

    protected function beforeFileModelSave(&$model)
    {
    }
}
