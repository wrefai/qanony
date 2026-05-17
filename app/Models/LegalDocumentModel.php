<?php

namespace App\Models;

use CodeIgniter\Model;

class LegalDocumentModel extends Model
{
    protected $table         = 'legal_documents';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'title', 'document_type', 'court_level', 'case_number',
        'document_date', 'hijri_year', 'file_path', 'file_name',
        'file_size', 'file_extension', 'page_count', 'word_count',
        'char_count', 'full_text', 'normalized_text', 'content_hash',
        'summary', 'keywords', 'is_indexed', 'indexed_by', 'scope_id',
    ];
    protected $returnType = 'array';

    /**
     * Columns safe for listing (excludes LONGTEXT fields for performance).
     */
    private const LIST_COLUMNS = 'id, scope_id, title, document_type, court_level, case_number, '
        . 'document_date, hijri_year, file_path, file_name, file_size, file_extension, '
        . 'page_count, word_count, char_count, content_hash, summary, keywords, '
        . 'is_indexed, indexed_by, created_at, updated_at';

    /**
     * Check if a document with this hash already exists.
     * Uses SELECT 1 LIMIT 1 — faster than COUNT(*) because it stops at first match.
     */
    public function existsByHash(string $hash): bool
    {
        $row = $this->db->table($this->table)
            ->select('1 AS found')
            ->where('content_hash', $hash)
            ->limit(1)
            ->get()
            ->getRow();

        return $row !== null;
    }

    /**
     * Full-text search using MySQL FULLTEXT index + LIKE fallback on file_name.
     *
     * Performance optimizations (B1/B4/B7):
     * - Explicit SELECT excludes full_text and normalized_text LONGTEXT columns
     * - Single-pass count via SQL_CALC_FOUND_ROWS + FOUND_ROWS()
     * - MATCH clause uses 3-column index (normalized_text, title, keywords)
     * - Supports scope_id filtering and filesize range filtering
     *
     * @param string $ftsQuery  FTS query string (already built by SearchController)
     * @param array  $filters   Associative filter array
     * @param int    $page      Page number (1-based)
     * @param int    $perPage   Results per page
     * @param int    $knownTotal Skip COUNT on pages > 1
     * @param string $rawQuery  Original raw query for LIKE search on file_name/title
     * @return array {items, total, page, per_page, total_pages}
     */
    public function fullTextSearch(string $ftsQuery, array $filters = [], int $page = 1, int $perPage = 50, int $knownTotal = 0, string $rawQuery = ''): array
    {
        $offset     = ($page - 1) * $perPage;
        $needsTotal = ($page === 1 || $knownTotal <= 0);   // only compute total on first page
        $calcRows   = $needsTotal ? 'SQL_CALC_FOUND_ROWS ' : '';

        // Build LIKE pattern for file_name / title search
        $hasRaw     = $rawQuery !== '';
        $likePat    = $hasRaw ? '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $rawQuery) . '%' : '';

        if (!empty($ftsQuery)) {
            $matchClause = "MATCH(normalized_text, title, keywords) AGAINST(? IN BOOLEAN MODE)";

            // Relevance: FULLTEXT hits score > 0; LIKE-only hits get 0.001 so they appear last
            $relevanceExpr = "CASE WHEN {$matchClause} > 0 THEN {$matchClause} ELSE 0.001 END";

            // B1: Explicit column list (no LONGTEXT).
            // B4: SQL_CALC_FOUND_ROWS only on page 1.
            $sql = "SELECT {$calcRows}" . self::LIST_COLUMNS
                 . ", {$relevanceExpr} AS relevance_score"
                 . " FROM {$this->table}"
                 . " WHERE ({$matchClause}";

            if ($hasRaw) {
                $sql .= " OR file_name LIKE ? OR title LIKE ?";
            }
            $sql .= ")";

        } elseif ($hasRaw) {
            // Empty FTS but raw query provided — LIKE-only search on file_name / title
            $sql = "SELECT {$calcRows}" . self::LIST_COLUMNS
                 . ", 0.001 AS relevance_score"
                 . " FROM {$this->table}"
                 . " WHERE (file_name LIKE ? OR title LIKE ?)";
        } else {
            // Browse mode (no query at all)
            $sql = "SELECT {$calcRows}" . self::LIST_COLUMNS
                 . ", 0 AS relevance_score"
                 . " FROM {$this->table}"
                 . " WHERE 1=1";
        }

        // Bind LIKE parameters (must come right after the WHERE clause's ? placeholders)
        $binds = [];
        if (!empty($ftsQuery)) {
            // Three placeholders for MATCH: WHERE clause + two in the CASE relevance expression
            $binds[] = $ftsQuery; // CASE WHEN MATCH ... > 0
            $binds[] = $ftsQuery; // THEN MATCH ...
            $binds[] = $ftsQuery; // WHERE MATCH ...
        }
        if (!empty($ftsQuery) && $hasRaw) {
            $binds[] = $likePat; // file_name LIKE ?
            $binds[] = $likePat; // title LIKE ?
        } elseif (empty($ftsQuery) && $hasRaw) {
            $binds[] = $likePat; // file_name LIKE ?
            $binds[] = $likePat; // title LIKE ?
        }

        // Apply filters via parameterized conditions

        if (!empty($filters['document_type'])) {
            if (is_array($filters['document_type'])) {
                $placeholders = implode(',', array_fill(0, count($filters['document_type']), '?'));
                $sql .= " AND document_type IN ({$placeholders})";
                $binds = array_merge($binds, $filters['document_type']);
            } else {
                $sql .= " AND document_type = ?";
                $binds[] = $filters['document_type'];
            }
        }
        if (!empty($filters['court_level'])) {
            $sql .= " AND court_level = ?";
            $binds[] = $filters['court_level'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND document_date >= ?";
            $binds[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND document_date <= ?";
            $binds[] = $filters['date_to'];
        }

        // Scope filter: array of scope IDs (already expanded with descendants by controller)
        if (!empty($filters['scope_ids'])) {
            $scopeIds = array_map('intval', (array) $filters['scope_ids']);
            $placeholders = implode(',', array_fill(0, count($scopeIds), '?'));
            $sql .= " AND scope_id IN ({$placeholders})";
            $binds = array_merge($binds, $scopeIds);
        }

        // Filesize range filters (in bytes)
        if (!empty($filters['min_size'])) {
            $sql .= " AND file_size >= ?";
            $binds[] = (int) $filters['min_size'];
        }
        if (!empty($filters['max_size'])) {
            $sql .= " AND file_size <= ?";
            $binds[] = (int) $filters['max_size'];
        }

        // Document IDs constraint (for re-search within existing results)
        if (!empty($filters['doc_ids'])) {
            $docIds = array_map('intval', (array) $filters['doc_ids']);
            $docIds = array_filter($docIds, fn($id) => $id > 0);
            if (!empty($docIds)) {
                $placeholders = implode(',', array_fill(0, count($docIds), '?'));
                $sql .= " AND id IN ({$placeholders})";
                $binds = array_merge($binds, $docIds);
            }
        }

        // Order and pagination
        if (!empty($ftsQuery) || $hasRaw) {
            $sql .= " ORDER BY relevance_score DESC";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }
        $sql .= " LIMIT ? OFFSET ?";
        $binds[] = $perPage;
        $binds[] = $offset;

        // Execute search query
        $results = $this->db->query($sql, $binds)->getResultArray();

        // B4: Get total from FOUND_ROWS() only when we asked for it.
        // On page > 1 with a known total, skip the extra round-trip.
        if ($needsTotal) {
            $total = (int) $this->db->query("SELECT FOUND_ROWS() AS total")->getRow()->total;
        } else {
            $total = $knownTotal;
        }

        return [
            'items'       => $results,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    /**
     * Get dashboard statistics.
     * Cached for 300 seconds — avoids 4 heavy COUNT/GROUP BY queries on every dashboard load.
     */
    public function getStats(): array
    {
        return cache()->remember('legal_docs_stats', 300, function () {
            return [
                'total'        => $this->countAllResults(false),
                'indexed'      => $this->where('is_indexed', 1)->countAllResults(false),
                'by_type'      => $this->select('document_type, COUNT(*) as count')->groupBy('document_type')->findAll(),
                'by_court'     => $this->select('court_level, COUNT(*) as count')->where('court_level IS NOT NULL')->groupBy('court_level')->findAll(),
                'recent'       => $this->select('id, title, document_type, court_level, created_at, file_name, file_size, scope_id')->orderBy('created_at', 'DESC')->limit(5)->findAll(),
            ];
        });
    }

    /**
     * Fetch a document for preview — returns only metadata + first 8 KB of full_text.
     * Avoids loading the entire LONGTEXT column (can be several MB per document).
     */
    public function findForPreview(int $id): ?array
    {
        $row = $this->db->table($this->table)
            ->select('id, title, document_type, court_level, case_number, document_date, '
                . 'page_count, file_name, file_extension, scope_id, '
                . 'SUBSTRING(full_text, 1, 8000) AS full_text')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
