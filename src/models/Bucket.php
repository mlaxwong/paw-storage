<?php
namespace paw\storage\models;

use paw\storage\models\File;
use Yii;
use yii\db\ActiveRecord;

class Bucket extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%file_bucket}}';
    }

    public function rules()
    {
        return [
            [['handle', 'url', 'path'], 'string'],
            [['is_dummy', 'is_default'], 'boolean'],
        ];
    }

    public function moveIn(File &$file, $force = false)
    {
        if ($file->bucket_id == $this->id && !$force) {
            return true;
        }

        $destination = null;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // prepare to move
            $source = $file->filePath;
            $dir = Yii::getAlias($this->path);
            $extension = $file->extension;
            $filename = null;

            do {
                $filename = $filename === null ? $file->filename : uniqid() . '.' . $extension;
                $file->filename = $filename;
                $destination = "$dir/$filename";
            } while (file_exists($destination) || File::find()
                ->andWhere(['filename' => $filename])
                ->andWhere(['<>', 'id', $file->id])
                ->exists());

            // update database record
            $file->bucket_id = $this->id;
            if (!$file->save()) {
                $transaction->rollBack();
                return false;
            }

            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    $transaction->rollBack();
                    return false;
                }
            }

            // copy file
            if (!copy($source, $destination)) {
                $transaction->rollBack();
                return false;
            }

            // remove old file
            unlink($source);

            //refresh
            $file->refresh();

            // commit
            $transaction->commit();
            return true;
        } catch (\Exception $ex) {
            $transaction->rollBack();
            if (file_exists($destination)) {
                unlink($destination);
            }
            if (YII_DEBUG) {
                throw $ex;
            }
            return false;
        }
    }
}
