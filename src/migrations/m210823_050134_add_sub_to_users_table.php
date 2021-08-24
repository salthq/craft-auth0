<?php

namespace salt\craftauth0\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210823_050134_add_sub_to_users_table migration.
 */
class m210823_050134_add_sub_to_users_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('users', 'sub', 'string');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropColumn('users', 'sub');
    }
}
