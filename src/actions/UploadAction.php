<?php
namespace paw\storage\actions;

use paw\storage\models\UploadForm;
use Yii;
use yii\base\Action;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class UploadAction extends Action
{
    public $param = 'file';

    public $modelOptions = [];

    public $onSuccess = null;

    public $onFail = null;

    public $modelClass = \paw\storage\models\BucketUploadForm::class;

    public function run(bool $multiple = false)
    {
        $modelConfig = ArrayHelper::merge([
            'class' => $this->modelClass,
            'mode' => $multiple ? UploadForm::MODE_MULTIPLE : UploadForm::MODE_ONE,
        ], $this->modelOptions);
        $model = Yii::createObject($modelConfig);

        if ($fileModel = $model->upload()) {
            if (is_callable($this->onSuccess)) {
                return call_user_func_array($this->onSuccess, [$fileModel, $model, $this]);
            } else {
                return Json::encode([
                    'success' => true,
                    'preview' => $fileModel->getLink(),
                    'isImage' => $this->getIsImage($fileModel->filepath),
                    'value' => $fileModel->filename,
                    'extension' => $fileModel->extension,
                ]);
            }
        } else {
            if (is_callable($this->onFail)) {
                return call_user_func_array($this->onFail, [$model, $this]);
            } else {
                $errors = array_values($model->getFirstErrors());
                $error = isset($errors[0]) ? $errors[0] : null;
                return Json::encode([
                    'success' => false,
                    'error' => $error,
                ]);
            }
        }
    }

    protected function getIsImage($link)
    {
        return @is_array(getimagesize($link)) ? true : false;
    }
}
