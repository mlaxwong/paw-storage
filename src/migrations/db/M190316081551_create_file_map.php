<?php
namespace paw\storage\migrations\db;

use Yii;
use paw\db\Migration;

class M190316081551_create_file_map extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%file_map}}', [
            'id' => $this->primaryKey()->unsigned(),
            'file_id' => $this->integer()->unsigned()->defaultValue(null),
            'model_class' => $this->text()->defaultValue(null),
            'model_id' => $this->integer()->unsigned()->defaultValue(null),
            'model_attribute' => $this->text()->defaultValue(null),
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%file_map}}');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M190316081551_create_file_map cannot be reverted.\n";

        return false;
    }
    */
}