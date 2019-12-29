<?php
namespace paw\storage\actions;

use paw\storage\models\File;
use paw\storage\models\UploadForm;
use Yii;
use yii\base\Action;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\ForbiddenHttpException;

class StorageAction extends Action
{
    public $param = 'file';

    public $modelOptions = [];

    public $onUploadSuccess = null;

    public $onUploadFail = null;

    public $onInfoSuccess = null;

    public $modelClass = \paw\storage\models\BucketUploadForm::class;

    public $enableThumbnail = true;

    public $baseUrl = null;

    protected $_multiple = null;

    protected $_filename = null;

    public function run($action = 'upload', bool $multiple = false, $filename = null)
    {
        $this->_multiple = $multiple;
        $this->_filename = basename($filename);

        $methodName = 'action' . ucfirst($action);
        $whiteListedActions = ['upload', 'info', 'options'];
        if (method_exists($this, $methodName) && \in_array($action, $whiteListedActions)) {
            return call_user_func([$this, $methodName]);
        } else {
            throw new ForbiddenHttpException(Yii::t('app', 'Invalid action'));
        }
    }

    protected function actionUpload()
    {
        $multiple = $this->getMultiple();

        $modelConfig = ArrayHelper::merge([
            'class' => $this->modelClass,
            'mode' => $multiple ? UploadForm::MODE_MULTIPLE : UploadForm::MODE_ONE,
        ], $this->modelOptions);
        $model = Yii::createObject($modelConfig);

        if ($fileModel = $model->upload()) {
            if (is_callable($this->onUploadSuccess)) {
                return call_user_func_array($this->onUploadSuccess, [$fileModel, $model, $this]);
            } else {
                return Json::encode([
                    'success' => true,
                    'value' => $fileModel->getLink(),
                ]);
            }
        } else {
            if (is_callable($this->onUploadFail)) {
                return call_user_func_array($this->onUploadFail, [$model, $this]);
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

    protected function actionInfo()
    {
        $filename = $this->getFilename();
        $fileModel = File::findOne(compact('filename'));
        $fileModel->baseUrl = $this->baseUrl;
        if ($fileModel) {
            if (is_callable($this->onInfoSuccess)) {
                return call_user_func_array($this->onInfoSuccess, [$fileModel, $this]);
            } else {
                return Json::encode([
                    'success' => true,
                    'id' => $fileModel->id,
                    'name' => $fileModel->name,
                    'preview' => $this->enableThumbnail ? Yii::$app->thumbnail->get($fileModel->getLink()) : $fileModel->getLink(),
                    'isImage' => $this->getIsImage($fileModel->filepath),
                    'extension' => $fileModel->extension,
                ]);
            }
        } else {
            return Json::encode([
                'success' => false,
            ]);
        }
    }

    protected function actionOptions()
    {
        return Json::encode([
            'success' => true,
            'accept' => $this->getClientAccepts(),
        ]);
    }

    protected function getClientAccepts()
    {
        $extensions = isset($this->modelOptions['extensions']) ? $this->modelOptions['extensions'] : null;

        if (!$extensions) {
            return '*';
        }

        $extensionParts = explode(',', $extensions);
        array_walk($extensionParts, function (&$item, $key) {
            $extension = trim($item);
            if (substr($extension, 0, 1) != '.') {
                $extension = ".$extension";
            }
            $item = $extension;
        });

        return implode(', ', $extensionParts);
    }

    protected function getMultiple()
    {
        return $this->_multiple;
    }

    protected function getFilename()
    {
        return $this->_filename;
    }

    protected function getIsImage($link)
    {
        return @is_array(getimagesize($link)) ? true : false;
    }
}
