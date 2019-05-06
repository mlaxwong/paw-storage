<?php
namespace paw\storage\models;

use paw\storage\models\Bucket;
use paw\storage\models\UploadForm;
use yii\helpers\ArrayHelper;

class BucketUploadForm extends UploadForm
{
    public $bucket = null;

    protected $_bucket;

    public function init()
    {
        $bucket = $this->getBucketModel();
        if ($bucket === null) {
            throw new InvalidConfigException(Yii::t('app', 'Invalid bucket or bucket not exists'));
        }

        $this->uploadDir = $bucket->path;

        parent::init();
    }

    public function rules()
    {
        $rules = parent::rules();
        return ArrayHelper::merge([
            [['bucket'], function ($attribute, $params, $validator) {
                if (!$this->getBucketModel()) {
                    $this->addError($attribute, Yii::t('app', 'Bucket \'{bucket}\' not exists', ['bucket' => $this->bucket]));
                }
            }, 'skipOnError' => true, 'skipOnEmpty' => true],
        ], $rules);
    }

    protected function beforeFileModelSave(&$model)
    {
        $bucket = $this->getBucketModel();
        $model->bucket_id = $bucket->id;
    }

    protected function getBucketModel()
    {
        if ($this->_bucket === null) {

            if ($this->bucket === null) {
                $this->_bucket = Bucket::findOne(['is_default' => true]);
            } else if ($this->bucket instanceof Bucket) {
                $this->_bucket = $this->bucket;
            } else if (is_integer($this->bucket)) {
                $this->_bucket = Bucket::findOne($this->_bucket);
            } else {
                $this->_bucket = Bucket::findOne(['handle' => $this->_bucket]);
            }
        }
        return $this->_bucket;
    }
}
