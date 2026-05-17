<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the upload_queue table for background document processing.
 *
 * Flow:
 *   1. HTTP upload → save file to disk + insert row (status=pending)
 *   2. CLI worker  → pick pending rows, parse, insert into legal_documents, set status=processed/failed
 */
class CreateUploadQueue extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Path relative to WRITEPATH (same convention as legal_documents.file_path)
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => false,
            ],
            'original_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => false,
            ],
            'file_size' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'file_extension' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
            ],
            // Optional metadata supplied by user at upload time
            'scope_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'document_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
            ],
            'court_level' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
            ],
            'case_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            'document_date' => [
                'type' => 'DATE',
                'null' => true,
                'default' => null,
            ],
            // pending → processing → processed | failed | duplicate
            'status' => [
                'type'       => "ENUM('pending','processing','processed','failed','duplicate')",
                'default'    => 'pending',
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            // ID of the resulting legal_documents row (set after successful processing)
            'document_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            'uploaded_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'default'  => null,
            ],
            // Number of processing attempts (to detect stuck jobs)
            'attempts' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'default'  => 0,
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
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->addKey('uploaded_by');

        $this->forge->createTable('upload_queue', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('upload_queue', true);
    }
}
