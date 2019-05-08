<?php
namespace paw\storage\migrations\db;

use paw\db\Migration;
use paw\storage\models\FileMap;

class M190508163652_alter_file_map extends Migration
{

    public function safeUp()
    {
        $this->addColumn(FileMap::tableName(), 'sort', $this->integer()->defaultValue(0)->after('model_attribute'));

    }

    public function safeDown()
    {
        $this->dropColumn(FileMap::tableName(), 'sort');
    }

    /*
// Use up()/down() to run migration code without a transaction.
public function up()
{

}

public function down()
{
echo "M190508163652_alter_file_map cannot be reverted.\n";

return false;
}
 */
}
