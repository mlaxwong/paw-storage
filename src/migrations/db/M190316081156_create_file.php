<?php
namespace paw\storage\migrations\db;

use Yii;
use paw\db\Migration;
use paw\user\models\User;

class M190316081156_create_file extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%file}}', [
            'id' => $this->primaryKey()->unsigned(),
            'path' => $this->text()->defaultValue(null),
            'url' => $this->text()->defaultValue(null),
            'filename' => $this->text()->defaultValue(null),
            'name' => $this->text()->defaultValue(null),
            'extension' => $this->string(36)->defaultValue(null),
            'mode' => "ENUM('link', 'path', 'Other') DEFAULT 'link'",
            'type' => $this->string(64)->defaultValue(null),
            'size' => $this->integer()->defaultValue(null),
            'is_dummy' => $this->boolean()->defaultValue(true),
            'created_ip' => $this->string(36)->defaultValue(null),
            'updated_ip' => $this->string(36)->defaultValue(null),
            'created_by' => $this->integer()->unsigned()->defaultValue(null),
            'updated_by' => $this->integer()->unsigned()->defaultValue(null),
            'created_at' => $this->timestamp()->defaultValue(null),
            'updated_at' => $this->timestamp()->defaultValue(null),
        ]);

        $this->addForeignKey(
            'fk_file_created_by',
            '{{%file}}', 'created_by',
            User::tableName(), 'id',
            'cascade', 'cascade'
        );

        $this->addForeignKey(
            'fk_file_updated_by',
            '{{%file}}', 'updated_by',
            User::tableName(), 'id',
            'cascade', 'cascade'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_file_updated_by', '{{%file}}');
        $this->dropForeignKey('fk_file_created_by', '{{%file}}');
        $this->dropTable('{{%file}}');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M190316081156_create_file cannot be reverted.\n";

        return false;
    }
    */
}