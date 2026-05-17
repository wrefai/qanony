<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSearchScopesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'parent_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'sort_order' => [
                'type'    => 'INT',
                'default' => 0,
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'default' => 1,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('parent_id');
        $this->forge->addKey('sort_order');
        $this->forge->addForeignKey('parent_id', 'search_scopes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('search_scopes');
    }

    public function down()
    {
        $this->forge->dropTable('search_scopes');
    }
}
