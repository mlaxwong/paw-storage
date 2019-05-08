<?php
namespace paw\storage\models;

use paw\storage\models\File;
use yii\db\ActiveRecord;

class FileMap extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%file_map}}';
    }

    public function rules()
    {
        return [
            [['file_id', 'model_class', 'model_id', 'model_attribute'], 'required'],
            [['file_id', 'model_id', 'sort'], 'integer'],
            [['model_class', 'model_attribute'], 'string'],
        ];
    }

    public function getFile()
    {
        return $this->hasOne(File::class, ['id' => 'file_id']);
    }
}
