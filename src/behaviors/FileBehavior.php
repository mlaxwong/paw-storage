<?php
namespace paw\storage\behaviors;

use paw\helpers\Json;
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
    public $baseUrl = null;

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
        if ($this->mode == self::MODE_ONE) {
            $this->findFile();
        } else {
            $this->findFiles();
        }
    }

    public function afterValidate($event)
    {
        if (!$this->getIsEmpty()) {
            if ($this->mode == self::MODE_ONE) {
                $this->validateFile();
            } else {
                $this->validateFiles();
            }
        }
    }

    public function afterSave($event)
    {
        $except = [];
        if (!$this->getIsEmpty()) {
            if ($this->mode == self::MODE_ONE) {
                $except = [$this->saveFile()];
            } else {
                $except = $this->saveFiles();
            }
        }
        $this->destoryUsedImages($except);
    }

    public function getAttributeFileModels()
    {
        if ($this->mode == self::MODE_ONE) {
            return [$this->getFileModel()];
        } else {
            return $this->getFileModels();
        }
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

    protected function getFileModel($attributeValue = null)
    {
        $attributeValue = $attributeValue === null ? $this->owner->{$this->attribute} : $attributeValue;
        $filename = basename($attributeValue);
        $fileModel = File::findOne(compact('filename'));
        if ($fileModel) {
            if ($this->baseUrl) {
                $fileModel->baseUrl = $this->baseUrl;
            }
        }
        return $fileModel;
    }

    protected function getFileModels()
    {
        $fileModels = [];
        $attributeValues = $this->owner->{$this->attribute};
        if (JSON::isJson($attributeValues)) {
            $attributeValues = JSON::decode($attributeValues);
        }
        if (is_array($attributeValues)) {
            foreach ($attributeValues as $attributeValue) {
                $fileModel = $this->getFileModel($attributeValue);
                if ($fileModel) {
                    $fileModels[] = $fileModel;
                }
            }
        }
        return $fileModels;
    }

    public function getBucketModel()
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

    protected function validateFile($file = null)
    {
        $file = $file === null ? $this->getFileModel() : $file;
        if (!$file) {
            $owner = $this->owner;
            $owner->addError($this->attribute, Yii::t('app', "File '{filename}' not found", ['filename' => $owner->{$this->attribute}]));
        }
    }

    protected function validateFiles()
    {
        $files = $this->getFileModels();
        foreach ($files as $file) {
            $this->validateFile($file);
        }
    }

    protected function saveFile($file = null)
    {
        $file = $file === null ? $this->getFileModel() : $file;
        $bucket = $this->getBucketModel();
        $file->belongTo($this->owner, $this->attribute, $bucket);
        return $file;
    }

    protected function saveFiles()
    {
        $owner = $this->owner;
        $files = $this->getFileModels();
        foreach ($files as $index => $file) {
            // save file
            $file = $this->saveFile($file);
            $file->refresh();

            // save sort
            $map = $mapQuery = FileMap::find()->andWhere([
                'model_class' => get_class($owner),
                'model_id' => $owner->id,
                'model_attribute' => $this->attribute,
                'file_id' => $file->id,
            ])->one();
            if ($map->sort != $index) {
                $map->sort = $index;
                $map->save();
            }
        }
        return $files;
    }

    protected function findFile()
    {
        $owner = $this->owner;
        $map = FileMap::find()->andWhere([
            'model_class' => get_class($owner),
            'model_id' => $owner->id,
            'model_attribute' => $this->attribute,
        ])->one();
        if ($map) {
            $file = $map->file;
            $file->baseUrl = $this->baseUrl;
            $owner->{$this->attribute} = $file->link;
        }
    }

    protected function findFiles()
    {
        $owner = $this->owner;
        $maps = FileMap::find()
            ->andWhere([
                'model_class' => get_class($owner),
                'model_id' => $owner->id,
                'model_attribute' => $this->attribute,
            ])
            ->orderBy(['sort' => SORT_ASC])
            ->all();
        if ($maps) {
            $files = ArrayHelper::getColumn($maps, function ($model) {
                $file = $model->file;
                $file->baseUrl = $this->baseUrl;
                return $file->link;
            });
            $owner->{$this->attribute} = $files;
        }
    }
}
