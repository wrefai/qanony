<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLegalDocumentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'document_type' => [
                'type'       => 'ENUM',
                'constraint' => ['ruling', 'memorandum', 'law', 'regulation', 'legal_opinion', 'contract'],
                'null'       => true,
            ],
            'court_level' => [
                'type'       => 'ENUM',
                'constraint' => ['first_instance', 'appeal', 'tamyeez', 'administrative', 'constitutional', 'commercial', 'criminal', 'personal_status', 'labor'],
                'null'       => true,
            ],
            'case_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'document_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'hijri_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 1000,
            ],
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'file_size' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'file_extension' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'page_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'full_text' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'normalized_text' => [
                'type'    => 'LONGTEXT',
                'null'    => true,
                'comment' => 'Arabic-normalized text for search',
            ],
            'content_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'unique'     => true,
                'comment'    => 'SHA-256 hash for dedup',
            ],
            'summary' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'keywords' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Comma-separated keywords',
            ],
            'is_indexed' => [
                'type'    => 'TINYINT',
                'default' => 0,
            ],
            'indexed_by' => [
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
        $this->forge->addKey('document_type');
        $this->forge->addKey('court_level');
        $this->forge->addKey('document_date');
        $this->forge->addKey('is_indexed');
        $this->forge->addForeignKey('indexed_by', 'users', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('legal_documents');

        // Create FULLTEXT index for search
        $this->db->query('ALTER TABLE legal_documents ADD FULLTEXT INDEX ft_documents (title, full_text, normalized_text, keywords)');
    }

    public function down()
    {
        $this->forge->dropTable('legal_documents');
    }
}
