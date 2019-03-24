<?php
namespace paw\storage\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;
use yii\helpers\Url;
use yii\helpers\StringHelper;
use yii\helpers\FileHelper;
use yii\behaviors\BlameableBehavior;
use paw\behaviors\TimestampBehavior;
use paw\behaviors\IpBehavior;
use paw\storage\records\FileMap;

class File extends ActiveRecord
{
    const SCENARIO_USE = 'scenario_use';

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
            BlameableBehavior::class,
            IpBehavior::class,
        ];
    }

    public static function tableName()
    {
        return '{{%file}}';
    }

    public function rules()
    {
        return [
            [['name', 'extension', 'type', 'mode', 'path', 'url'], 'string'],
            [['size'], 'integer'],
            [['is_dummy'], 'boolean'],
        ];
    }

    public function getStorageUrl()
    {
        return dirname(URL::base(true));
    }

    public function getDummyPath()
    {
        return PATH_BASE . '/dist/upload';
    }

    public function getStoragePath()
    {
        return PATH_BASE . '/dist/storage';
    }

    public function getLink()
    {
        $storageBaseUrl = $this->getStorageUrl();
        $urlPath = ltrim($this->url, '/');
        $fileName = $this->filename;
        return $storageBaseUrl . '/' . $urlPath . '/' . $fileName;
    }

    public function getFilePath()
    {
        return $this->path . '/' . $this->filename;
    }

    public function toString()
    {
        return $this->getLink();
    }

    public function __toString()
    {
        return $this->toString();
    }

    public function usefile(BaseActiveRecord $model, $attribute)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->scenario = self::SCENARIO_USE;

            $data = [
                'file_id' => $this->id,
                'model_class' => get_class($model),
                'model_id' => $model->id,
                'model_attribute' => $attribute, 
            ];
            
            if (FileMap::find()->andWhere($data)->exists()) return true;

            $map = new FileMap([
                'file_id' => $this->id,
                'model_class' => get_class($model),
                'model_id' => $model->id,
                'model_attribute' => $attribute, 
            ]);
                
            if (!$map->save())
            {
                $transaction->rollBack();
                return false;
            }

            $oldFile = $this->getFilePath();
            $storageDir = $this->getStoragePath();
            
            do {
                $fileName = uniqid() . '.' . $this->extension;
                $movePath = $storageDir . '/' . $fileName;
            } while (file_exists($movePath));

            if (!copy($oldFile, $movePath)) 
            {
                $transaction->rollBack();
                return false;
            }

            $this->filename = $fileName;
            $this->path = $storageDir;
            $this->url = '/storage';
            $this->is_dummy = false;

            if (!$this->save())
            {
                $transaction->rollBack();
                return false;
            }

            unlink($oldFile);
            
            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            $transaction->rollBack();
            throw $ex;
            // if (YII_DEBUG) throw $ex;
            return false;
        }
    }
}