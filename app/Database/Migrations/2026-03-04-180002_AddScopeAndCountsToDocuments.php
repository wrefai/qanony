<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddScopeAndCountsToDocuments extends Migration
{
    public function up()
    {
        // Add scope_id FK, word_count, and char_count to legal_documents
        $this->forge->addColumn('legal_documents', [
            'scope_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'id',
            ],
            'word_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
                'after'    => 'page_count',
            ],
            'char_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
                'after'    => 'word_count',
            ],
        ]);

        // Add FK constraint and index for scope_id
        $this->db->query('ALTER TABLE legal_documents ADD INDEX idx_scope_id (scope_id)');
        $this->db->query('ALTER TABLE legal_documents ADD CONSTRAINT fk_documents_scope FOREIGN KEY (scope_id) REFERENCES search_scopes(id) ON DELETE SET NULL ON UPDATE CASCADE');

        // Backfill word_count and char_count from existing full_text data
        $this->db->query("
            UPDATE legal_documents
            SET word_count = CASE
                    WHEN full_text IS NULL OR full_text = '' THEN 0
                    ELSE (LENGTH(TRIM(full_text)) - LENGTH(REPLACE(TRIM(full_text), ' ', '')) + 1)
                END,
                char_count = CASE
                    WHEN full_text IS NULL THEN 0
                    ELSE CHAR_LENGTH(full_text)
                END
            WHERE word_count = 0
        ");
    }

    public function down()
    {
        // Remove FK first, then columns
        $this->db->query('ALTER TABLE legal_documents DROP FOREIGN KEY fk_documents_scope');
        $this->db->query('ALTER TABLE legal_documents DROP INDEX idx_scope_id');
        $this->forge->dropColumn('legal_documents', ['scope_id', 'word_count', 'char_count']);
    }
}
