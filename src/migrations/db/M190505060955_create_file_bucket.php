<?php
namespace paw\storage\migrations\db;

use paw\db\Migration;

class M190505060955_create_file_bucket extends Migration
{
    use \paw\db\TextTypesTrait;
    use \paw\db\DefaultColumn;

    public function safeUp()
    {
        $this->createTable('{{%file_bucket}}', [
            'id' => $this->primaryKey()->unsigned(),
            'handle' => $this->string()->defaultValue(null),
            'path' => $this->string()->defaultValue(null),
            'url' => $this->string()->defaultValue(null),
            'is_dummy' => $this->boolean()->defaultValue(false),
            'is_default' => $this->boolean()->defaultValue(false),
        ]);

        $this->batchInsert('{{%file_bucket}}', ['handle', 'path', 'url', 'is_dummy', 'is_default'], [
            ['dummy', '@root/storage/dummy', '/storage/dummy', true, true],
            ['default', '@root/storage/default', '/storage/default', false, false],
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('{{%file_bucket}}');
    }

    /*
// Use up()/down() to run migration code without a transaction.
public function up()
{

}

public function down()
{
echo "M190505060955_create_file_bucket cannot be reverted.\n";

return false;
}
 */
}
