<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class OptimizeFulltextIndex extends Migration
{
    public function up()
    {
        // Drop the 4-column FULLTEXT index (includes redundant full_text)
        // and replace with 3-column index on (normalized_text, title, keywords)
        $this->db->query('ALTER TABLE legal_documents DROP INDEX ft_documents');
        $this->db->query('ALTER TABLE legal_documents ADD FULLTEXT INDEX ft_documents (normalized_text, title, keywords)');
    }

    public function down()
    {
        // Restore original 4-column FULLTEXT index
        $this->db->query('ALTER TABLE legal_documents DROP INDEX ft_documents');
        $this->db->query('ALTER TABLE legal_documents ADD FULLTEXT INDEX ft_documents (title, full_text, normalized_text, keywords)');
    }
}
