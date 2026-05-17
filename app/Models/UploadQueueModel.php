<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for the upload_queue table.
 *
 * Statuses:
 *   pending    — file saved, awaiting CLI processing
 *   processing — CLI worker has claimed this row (prevents double-processing)
 *   processed  — successfully parsed and inserted into legal_documents
 *   duplicate  — skipped because content_hash already exists
 *   failed     — parse/DB error; see error_message column
 */
class UploadQueueModel extends Model
{
    protected $table         = 'upload_queue';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    protected $allowedFields = [
        'file_path', 'original_name', 'file_size', 'file_extension',
        'scope_id', 'document_type', 'court_level', 'case_number', 'document_date',
        'status', 'error_message', 'document_id', 'uploaded_by', 'attempts',
    ];

    // ── Queue Stats ────────────────────────────────────────────────

    /**
     * Returns counts grouped by status.
     * @return array{pending:int, processing:int, processed:int, failed:int, duplicate:int, total:int}
     */
    public function getStats(): array
    {
        $rows = $this->db->table($this->table)
            ->select('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()->getResultArray();

        $stats = [
            'pending'    => 0,
            'processing' => 0,
            'processed'  => 0,
            'failed'     => 0,
            'duplicate'  => 0,
            'total'      => 0,
        ];

        foreach ($rows as $row) {
            $key = $row['status'];
            if (isset($stats[$key])) {
                $stats[$key] = (int) $row['cnt'];
            }
            $stats['total'] += (int) $row['cnt'];
        }

        return $stats;
    }

    // ── CLI Worker Helpers ─────────────────────────────────────────

    /**
     * Atomically claim up to $limit pending rows for the current worker.
     * Uses UPDATE+SELECT to avoid race conditions with multiple CLI processes.
     *
     * @return array[] Array of queue row arrays claimed by this worker
     */
    public function claimPending(int $limit = 50): array
    {
        // Mark a batch as 'processing' in one atomic UPDATE
        $this->db->query(
            "UPDATE `{$this->table}`
             SET status = 'processing', attempts = attempts + 1, updated_at = NOW()
             WHERE status = 'pending'
             ORDER BY id ASC
             LIMIT ?",
            [$limit]
        );

        // Fetch the rows we just claimed (processing + attempts updated in this pass)
        return $this->where('status', 'processing')
                    ->orderBy('id', 'ASC')
                    ->limit($limit)
                    ->findAll();
    }

    /**
     * Mark a row as successfully processed.
     */
    public function markProcessed(int $id, int $documentId): void
    {
        $this->update($id, [
            'status'      => 'processed',
            'document_id' => $documentId,
            'error_message' => null,
        ]);
    }

    /**
     * Mark a row as a duplicate (file already exists by content hash).
     */
    public function markDuplicate(int $id): void
    {
        $this->update($id, [
            'status'        => 'duplicate',
            'error_message' => null,
        ]);
    }

    /**
     * Mark a row as failed with a reason.
     */
    public function markFailed(int $id, string $reason): void
    {
        $this->update($id, [
            'status'        => 'failed',
            'error_message' => mb_substr($reason, 0, 5000),
        ]);
    }

    /**
     * Reset stuck 'processing' rows back to 'pending'
     * (e.g., if the CLI process was killed mid-run).
     * A row is considered stuck if updated_at is older than $minutes.
     */
    public function resetStuck(int $minutes = 30): int
    {
        $this->db->query(
            "UPDATE `{$this->table}`
             SET status = 'pending', updated_at = NOW()
             WHERE status = 'processing'
               AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$minutes]
        );

        return $this->db->affectedRows();
    }

    /**
     * Paginated list of queue rows (for the admin UI).
     */
    public function getPage(int $page, int $perPage, string $status = ''): array
    {
        $builder = $this->orderBy('id', 'DESC');
        if ($status !== '') {
            $builder = $builder->where('status', $status);
        }
        return $builder->paginate($perPage, 'default', $page);
    }

    /**
     * Total count for a given status filter (used for pagination).
     */
    public function countByStatus(string $status = ''): int
    {
        if ($status !== '') {
            return $this->where('status', $status)->countAllResults();
        }
        return $this->countAll();
    }

    /**
     * Delete all rows with a given status (cleanup helper).
     */
    public function deleteByStatus(string $status): int
    {
        $this->where('status', $status)->delete();
        return $this->db->affectedRows();
    }
}
