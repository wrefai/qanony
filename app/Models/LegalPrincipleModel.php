<?php

namespace App\Models;

use CodeIgniter\Model;

class LegalPrincipleModel extends Model
{
    protected $table         = 'legal_principles';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'document_id', 'title', 'description', 'legal_basis',
        'category', 'source_quote', 'page_reference',
        'confidence', 'extraction_method',
    ];
    protected $returnType = 'array';

    /**
     * Get principles by document.
     */
    public function getByDocument(int $documentId): array
    {
        return $this->where('document_id', $documentId)
            ->orderBy('confidence', 'DESC')
            ->findAll();
    }

    /**
     * Search principles.
     */
    public function search(string $query, ?string $category = null, int $page = 1, int $perPage = 20): array
    {
        $builder = $this->builder();
        $builder->select('legal_principles.*, legal_documents.title as document_title');
        $builder->join('legal_documents', 'legal_documents.id = legal_principles.document_id');

        if (! empty($query)) {
            // Use a bound parameter via query binding to prevent any possibility
            // of SQL injection — never interpolate user input into raw SQL strings.
            $builder->where(
                "MATCH(legal_principles.title, legal_principles.description, legal_principles.legal_basis, legal_principles.source_quote) AGAINST(? IN BOOLEAN MODE)",
                $query,
                false
            );
        }

        if ($category) {
            $builder->where('legal_principles.category', $category);
        }

        $total = $builder->countAllResults(false);
        $results = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return [
            'items'       => $results,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }
}
