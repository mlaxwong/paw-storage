<?php
namespace paw\storage\actions;

use yii\base\Action;
use yii\helpers\ArrayHelper;
use paw\storage\models\File;
use paw\storage\models\UploadForm;
use yii\web\UploadedFile;

class UploadAction extends Action
{
    public $param = 'file';

    public $modelOptions = [];

    public $onSuccess = null;

    public $onFail = null;

    public function run(bool $multiple = false)
    {
        $model = new UploadForm(ArrayHelper::merge([
            'mode' => $multiple ? UploadForm::MODE_MULTIPLE : UploadForm::MODE_ONE
        ], $this->modelOptions));

        if ($fileModel = $model->upload()) {
            if (is_callable($this->onSuccess)) {
                return call_user_func_array($this->onSuccess, [$fileModel, $model, $this]);
            }
        } else {
            if (is_callable($this->onFail)) {
                return call_user_func_array($this->onFail, [$model, $this]);
            }
        }
    }
}