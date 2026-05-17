<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePermissionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'unique'     => true,
                'comment'    => 'e.g. users.create, documents.read',
            ],
            'group_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => 'Logical grouping: users, roles, documents, etc.',
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('group_name');
        $this->forge->createTable('permissions');
    }

    public function down()
    {
        $this->forge->dropTable('permissions');
    }
}
