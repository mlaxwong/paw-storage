<?php
namespace paw\storage\models;

use paw\behaviors\IpBehavior;
use paw\behaviors\TimestampBehavior;
use paw\storage\models\Bucket;
use paw\storage\models\FileMap;
use Yii;
use yii\behaviors\BlameableBehavior;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\helpers\Url;

class File extends ActiveRecord
{
    const SCENARIO_USE = 'scenario_use';

    protected $_baseUrl = null;

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
        return URL::base(true);
    }

    public function getDummyPath()
    {
        return PATH_BASE . '/dist/upload';
    }

    public function getStoragePath()
    {
        return PATH_BASE . '/dist/storage';
    }

    public function setBaseUrl($baseUrl)
    {
        $this->_baseUrl = $baseUrl;
    }

    protected function getBaseUrl()
    {
        $bucket = $this->bucket;
        if ($this->_baseUrl === null) {
            return Yii::getAlias('@web' . $bucket->url);
        } else {   
            return Yii::getAlias($this->_baseUrl . $bucket->url);         
        }
    }

    public function getLink()
    {
        $baseUrl = $this->getBaseUrl();
        $filename = $this->filename;
        return Url::to("$baseUrl/$filename");
    }

    public function getFilePath()
    {
        $bucket = $this->bucket;
        $dir = Yii::getAlias($bucket->path);
        $filename = $this->filename;
        return "$dir/$filename";
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

            if (FileMap::find()->andWhere($data)->exists()) {
                return true;
            }

            $map = new FileMap([
                'file_id' => $this->id,
                'model_class' => get_class($model),
                'model_id' => $model->id,
                'model_attribute' => $attribute,
            ]);

            if (!$map->save()) {
                $transaction->rollBack();
                return false;
            }

            $oldFile = $this->getFilePath();
            $storageDir = $this->getStoragePath();

            do {
                $fileName = uniqid() . '.' . $this->extension;
                $movePath = $storageDir . '/' . $fileName;
            } while (file_exists($movePath));

            if (!copy($oldFile, $movePath)) {
                $transaction->rollBack();
                return false;
            }

            $this->filename = $fileName;
            $this->path = $storageDir;
            $this->url = '/storage';
            $this->is_dummy = false;

            if (!$this->save()) {
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

    public function belongTo(BaseActiveRecord $model, $attribute, $bucket)
    {
        $transaction = Yii::$app->db->beginTransaction();
        $formBucket = $this->bucket;
        try {
            // move bucket
            if (!$bucket->moveIn($this)) {
                $transaction->rollBack();
                return false;
            }

            $data = [
                'file_id' => $this->id,
                'model_class' => get_class($model),
                'model_id' => $model->id,
                'model_attribute' => $attribute,
            ];

            if (FileMap::find()->andWhere($data)->exists()) {
                $transaction->commit();
                return true;
            }

            $map = new FileMap([
                'file_id' => $this->id,
                'model_class' => get_class($model),
                'model_id' => $model->id,
                'model_attribute' => $attribute,
            ]);

            if (!$map->save()) {
                // revert database
                $transaction->rollBack();

                // double check on bucket
                $this->refresh();
                if ($this->bucket_id !== $formBucket->id) {
                    $formBucket->moveIn($this);
                }
                return false;
            }

            $transaction->commit();
            return true;
        } catch (Exception $ex) {
            // revert database
            $transaction->rollBack();

            // double check on bucket
            $this->refresh();
            if ($this->bucket_id !== $formBucket->id) {
                $formBucket->moveIn($this);
            }

            if (YII_DEBUG) {
                throw $ex;
            }
            return false;
        }
    }

    public function getBucket()
    {
        return $this->hasOne(Bucket::class, ['id' => 'bucket_id']);
    }

    public function deleteIfNoUsed($outdatedOwner, $attribute = null)
    {
        $mapQuery = FileMap::find()
            ->andWhere(['file_id' => $this->id])
            ->andWhere(['<>', 'model_class', get_class($outdatedOwner)])
            ->andWhere(['<>', 'model_id', $outdatedOwner->id])
        ;
        if ($attribute) {
            $mapQuery->andWhere(['<>', 'attribute', $attribute]);
        }
        $count = $mapQuery->count();
        if ($count <= 0) {
            $this->delete();
        }
    }

    public function delete()
    {
        $id = $this->id;
        $delete = parent::delete();
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
        FileMap::deleteAll('file_id = ' . $id);
        return $delete;
    }

    public function clone(Bucket $bucket)
    {
        $clonePath = Yii::getAlias($bucket->path);
        $sourcePath = $this->getFilePath();
        do {
            $cloneFileName = uniqid() . '.' . $this->extension;
            $cloneDist = "$clonePath/$cloneFileName";
        } while(file_exists($cloneDist));

        copy($sourcePath, $cloneDist);

        $clone = new self;
        $clone->attributes = $this->attributes;
        $clone->bucket_id = $bucket->id;
        $clone->path = $clonePath;
        $clone->filename = $cloneFileName;

        if (!$clone->save(false)) {
            if (file_exists($cloneDist)) {
                unlink($cloneDist);
            }
            return null;
        }

        return $clone;
    }
}
