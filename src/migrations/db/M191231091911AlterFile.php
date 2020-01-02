<?php
namespace paw\storage\migrations\db;

use paw\db\Migration;

class M191231091911AlterFile extends Migration
{
    use \paw\db\TextTypesTrait;
    use \paw\db\DefaultColumn;

    public function safeUp()
    {
        $this->alterColumn('{{%file}}', 'type', $this->longText()->unsigned()->defaultValue(null));
    }

    public function safeDown()
    {
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "M191231091911AlterFile cannot be reverted.\n";

        return false;
    }
    */
}