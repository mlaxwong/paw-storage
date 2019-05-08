<?php
namespace paw\storage\behaviors;

use paw\storage\models\Bucket;
use paw\storage\models\File;
use paw\storage\models\FileMap;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class FileBehavior extends Behavior
{
    const MODE_ONE = 'one';
    const MODE_MULTIPLE = 'multiple';

    public $attribute;
    public $mode = self::MODE_ONE;
    public $bucket = 'default';

    protected $_bucket = null;

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_VALIDATE => 'afterValidate',
            // ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            // ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
        ];
    }

    public function afterFind($event)
    {
        $owner = $this->owner;
        $map = FileMap::find()->andWhere([
            'model_class' => get_class($owner),
            'model_id' => $owner->id,
            'model_attribute' => $this->attribute,
        ])->one();
        if ($map) {
            $owner->{$this->attribute} = $map->file;
        }
    }

    public function afterValidate($event)
    {
        if (!$this->getIsEmpty()) {
            $file = $this->getFileModel();
            if (!$file) {
                $owner = $this->owner;
                $owner->addError($this->attribute, Yii::t('app', "File '{filename}' not found", ['filename' => $owner->{$this->attribute}]));
            }
        }
    }

    public function afterSave($event)
    {
        $except = [];
        if (!$this->getIsEmpty()) {
            $file = $this->getFileModel();
            $bucket = $this->getBucketModel();
            $file->belongTo($this->owner, $this->attribute, $bucket);
            $except[] = $file;
        }
        $this->destoryUsedImages($except);
    }

    protected function destoryUsedImages($except = [])
    {
        $fileIds = ArrayHelper::getColumn($except, 'id');
        $owner = $this->owner;
        $maps = FileMap::find()
            ->andWhere([
                'model_class' => get_class($owner),
                'model_id' => $owner->id,
                'model_attribute' => $this->attribute,
            ])
            ->andWhere(['not in', 'file_id', $fileIds])
            ->all();
        foreach ($maps as $map) {
            $file = $map->file;
            if ($file) {
                $file->deleteIfNoUsed($owner);
            }
            if ($map) {
                $map->delete();
            }

        }
    }

    protected function getFileModel()
    {
        $attributeValue = $this->owner->{$this->attribute};
        $filename = basename($attributeValue);
        return File::findOne(compact('filename'));
    }

    protected function getBucketModel()
    {
        if ($this->_bucket === null) {

            if ($this->bucket === null) {
                $this->_bucket = Bucket::findOne(['is_default' => true]);
            } else if ($this->bucket instanceof Bucket) {
                $this->_bucket = $this->bucket;
            } else if (is_integer($this->bucket)) {
                $this->_bucket = Bucket::findOne($this->bucket);
            } else {
                $this->_bucket = Bucket::findOne(['handle' => $this->bucket]);
            }
        }
        return $this->_bucket;
    }

    protected function getIsEmpty()
    {
        $owner = $this->owner;
        if (!isset($owner->{$this->attribute})) {
            return true;
        }
        return !$owner->{$this->attribute};
    }
}
