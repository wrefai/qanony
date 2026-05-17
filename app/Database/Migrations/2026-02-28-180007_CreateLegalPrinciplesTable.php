<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLegalPrinciplesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'document_id' => [
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
            'category' => [
                'type'       => 'ENUM',
                'constraint' => ['substantive', 'procedural', 'constitutional', 'commercial', 'criminal', 'civil', 'administrative', 'labor', 'personal_status'],
                'default'    => 'substantive',
            ],
            'source_quote' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'page_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'confidence' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,4',
                'default'    => 1.0,
            ],
            'extraction_method' => [
                'type'       => 'ENUM',
                'constraint' => ['manual', 'rule_based', 'llm'],
                'default'    => 'manual',
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
        $this->forge->addKey('category');
        $this->forge->addForeignKey('document_id', 'legal_documents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('legal_principles');

        $this->db->query('ALTER TABLE legal_principles ADD FULLTEXT INDEX ft_principles (title, description, legal_basis, source_quote)');
    }

    public function down()
    {
        $this->forge->dropTable('legal_principles');
    }
}
