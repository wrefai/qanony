<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDefensesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'principle_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'legal_basis' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
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
        $this->forge->addForeignKey('principle_id', 'legal_principles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('defenses');
    }

    public function down()
    {
        $this->forge->dropTable('defenses');
    }
}
