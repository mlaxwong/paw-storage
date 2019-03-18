<?php
namespace paw\storage\models;

use Yii;
use yii\base\Model;
use yii\base\InvalidConfigException;
use yii\storage\models\File;

class UploadForm extends Model
{
    const MODE_ONE      = 'one';
    const MODE_MULTIPLE = 'multiple';

    public $file;

    public $extensions = null;

    public $maxFiles = 0;

    public $mode = self::MODE_ONE;

    public $uploadDir = null;

    public function init()
    {
        parent::init();

        if ($this->uploadDir === null) throw new InvalidConfigException(Yii::t('app', 'Configure \'{configure}\' is required', ['configure' => 'uploadDir']));

        if (!file_exists(Yii::getAlias($this->uploadDir))) throw new InvalidConfigException(Yii::t('app', 'Directory \'{path}\' not exists', ['path' => Yii::getAlias($this->uploadDir)]));

        if (!in_array($this->mode, [self::MODE_ONE, self::MODE_MULTIPLE])) throw new InvalidConfigException(Yii::t('app', 'Invaild upload mode'));
    }

    public function rules()
    {
        return [
            [['file'], 'file', 'skipOnEmpty' => false, 'extensions' => $this->extensions, $maxFiles = $this->maxFiles],
        ];
    }

    public function upload()
    {
        if (!$this->validate()) return false;

        try {
            $transaction = Yii::$app->db->beginTransaction();
            if ($this->mode == self::MODE_MULTIPLE) {
                foreach ($this->file as $file) {
                    if (!$this->uploadFile($file))
                    {
                        $transaction->rollBacK();
                        return false;
                    }
                }
            } else {
                if (!$this->uploadFile($this->file))
                {
                    $transaction->rollBacK();
                    return false;
                }
            }

            $transaction->commit();
            return false;
        } catch (\Exception $ex) {
            $transaction->rollBacK();
            if (YII_DEBUG) throw $ex;
            return false;
        }
    }

    protected function uploadFile($file)
    {
        $uploadDir      = Yii::getAlias($this->uploadDir);
        $originalName   = $file->baseName;
        $extension      = $file->extension;
        $uploadName     = $this->getUploadName($uploadDir, $extension);
        $type           = $file->type;
        $size           = $file->size;

        $dist           = "$uploadDir/$uploadName.$extension";

        if (!$file->saveAs($dist)) return false;

        $model = new File([
            'path'      => $uploadDir,
            'filename'  => "$uploadName.$extension",
            'name'      => "$originalName.$extension",
            'extension' => $extension,
            'type'      => $type,
            'size'      => $size,
        ]);
        if (!$model->save())
        {
            if (YII_DEBUG) throw new \Exception(print_r($model->errors, 1));
            return false;
        }

        return true;
    }

    protected function getUploadName($uploadDir, $extension)
    {
        $uploadName = null;
        do {
            $uploadName = uniqid();
            $dist = "$uploadDir/$uploadName.$extension";
        } while (file_exists($dist));
        return $uploadName;
    }
}