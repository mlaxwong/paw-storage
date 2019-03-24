<?php
namespace tritiq\behaviors;

use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use paw\helpers\Json;
use paw\storage\models\File;
use paw\storage\models\FileMap;

class FileBehavior extends Behavior
{
    public $attribute;

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
        if ($map)
        {
            $owner->{$this->attribute} = $map->file->toString();
        }
    }

    public function afterValidate($event)
    {
        $owner = $this->owner;
        $value = $this->parseValue($owner->{$this->attribute});
        if (is_array($value))
        {
            if (!isset($value['id'])) $owner->addError($this->attribute, Yii::t('app', 'Wrong file format'));
            if (!File::find()->andWhere(['id' => $value['id']])->exists()) $owner->addError($this->attribute, Yii::t('app', "File '{id}' not found", ['id' => $value['id']]));
        }
    }

    public function afterSave($event)
    {
        $owner = $this->owner;
        $value = $this->parseValue($owner->{$this->attribute});
        if (is_array($value) && isset($value['id']))
        {
            $fileId = $value['id'];
            $file = File::findOne($fileId);
            if ($file->usefile($owner, $this->attribute))
            {
                $owner->{$this->attribute} = $file->toString();
            }
        }
    }

    protected function parseValue($value)
    {
        return Json::isJson($value) ? Json::decode($value) : $value;
    }
}