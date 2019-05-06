<?php
namespace paw\storage\migrations\db;

use paw\db\Migration;
use paw\storage\models\Bucket;

class M190505063743_alter_file extends Migration
{
    use \paw\db\TextTypesTrait;

    public function safeUp()
    {
        $this->addColumn('{{%file}}', 'bucket_id', $this->integer()->unsigned()->defaultValue(null)->after('id'));
        $this->addForeignKey(
            'fk_file_bucket_id',
            '{{%file}}', 'bucket_id',
            Bucket::tableName(), 'id',
            'cascade', 'cascade'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_file_bucket_id', '{{%file}}');
        $this->dropColumn('{{%file}}', 'bucket_id');
    }

    /*
// Use up()/down() to run migration code without a transaction.
public function up()
{

}

public function down()
{
echo "M190505063743_alter_file cannot be reverted.\n";

return false;
}
 */
}
