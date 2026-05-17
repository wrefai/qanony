<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserPermissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'permission_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'granted' => [
                'type'    => 'TINYINT',
                'default' => 1,
                'comment' => '1 = grant, 0 = deny (overrides role)',
            ],
        ]);
        $this->forge->addKey(['user_id', 'permission_id'], true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_permissions');
    }

    public function down()
    {
        $this->forge->dropTable('user_permissions');
    }
}
