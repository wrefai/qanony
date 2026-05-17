<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DocumentParser;
use App\Libraries\ArabicTextNormalizer;
use App\Models\LegalDocumentModel;
use App\Models\AuditLogModel;
use App\Models\UploadQueueModel;

/**
 * Background processor for the upload_queue table.
 *
 * Picks up pending rows, parses each document file with PhpWord,
 * normalises Arabic text, inserts into legal_documents, and updates the
 * queue row to processed / duplicate / failed.
 *
 * Designed to run indefinitely (daemon mode) or for a single pass.
 *
 * Usage:
 *   # Process all pending items once then exit
 *   php spark queue:process
 *
 *   # Run as a daemon (loop forever, useful with a process supervisor)
 *   php spark queue:process --daemon
 *
 *   # Limit each pass to N items (default 50)
 *   php spark queue:process --batch=100
 *
 *   # Reset stuck 'processing' rows before starting (use after crash)
 *   php spark queue:process --reset-stuck
 *
 * @codeCoverageIgnore
 */
class ProcessQueue extends BaseCommand
{
    protected $group       = 'Queue';
    protected $name        = 'queue:process';
    protected $description = 'Process pending documents in the upload_queue table';

    protected $usage   = 'queue:process [--daemon] [--batch=<n>] [--reset-stuck]';
    protected $options = [
        '--daemon'      => 'Run as daemon (loop forever with a 5-second sleep between passes)',
        '--batch'       => 'Max items to claim per pass (default: 50)',
        '--reset-stuck' => 'Reset stuck "processing" rows back to "pending" before the first pass',
    ];

    /** @var UploadQueueModel */
    private UploadQueueModel $queueModel;

    /** @var LegalDocumentModel */
    private LegalDocumentModel $docModel;

    /** @var AuditLogModel */
    private AuditLogModel $auditModel;

    public function run(array $params): void
    {
        $this->queueModel = new UploadQueueModel();
        $this->docModel   = new LegalDocumentModel();
        $this->auditModel = new AuditLogModel();

        $daemon     = CLI::getOption('daemon') !== null;
        $batchSize  = (int) (CLI::getOption('batch') ?? 50);
        $resetStuck = CLI::getOption('reset-stuck') !== null;

        if ($batchSize < 1 || $batchSize > 500) {
            $batchSize = 50;
        }

        CLI::write('[queue:process] Starting — batch=' . $batchSize . ($daemon ? ' daemon=on' : ''), 'cyan');

        if ($resetStuck) {
            $reset = $this->queueModel->resetStuck(30);
            CLI::write("  Reset {$reset} stuck rows back to pending.", 'yellow');
        }

        $totalProcessed = 0;

        do {
            $rows = $this->queueModel->claimPending($batchSize);

            if (empty($rows)) {
                if ($daemon) {
                    CLI::write('  No pending items. Sleeping 5s…', 'dark');
                    sleep(5);
                    continue;
                }
                break;
            }

            CLI::write('  Processing ' . count($rows) . ' item(s)…', 'white');

            foreach ($rows as $row) {
                $this->processRow($row);
                $totalProcessed++;
            }

        } while ($daemon);

        CLI::write('[queue:process] Done. Total processed this run: ' . $totalProcessed, 'green');
    }

    // ── Private helpers ────────────────────────────────────────────

    private function processRow(array $row): void
    {
        $id           = (int) $row['id'];
        $relativePath = $row['file_path'];
        $fullPath     = WRITEPATH . $relativePath;
        $originalName = $row['original_name'];

        // Check file still exists
        if (!file_exists($fullPath)) {
            $this->queueModel->markFailed($id, 'File not found: ' . $fullPath);
            CLI::write("  [FAIL] #{$id} {$originalName} — file missing", 'red');
            return;
        }

        // Duplicate check (another process may have inserted the same file)
        $hash = DocumentParser::computeHash($fullPath);
        if ($this->docModel->existsByHash($hash)) {
            $this->queueModel->markDuplicate($id);
            CLI::write("  [SKIP] #{$id} {$originalName} — duplicate", 'yellow');
            return;
        }

        // Parse document
        $parseResult = DocumentParser::parse($fullPath);
        if (!$parseResult['success']) {
            $this->queueModel->markFailed($id, $parseResult['error'] ?? 'Parse error');
            CLI::write("  [FAIL] #{$id} {$originalName} — " . $parseResult['error'], 'red');
            return;
        }

        $normalizedText = ArabicTextNormalizer::normalize($parseResult['full_text']);
        $counts         = $this->computeTextCounts($parseResult['full_text']);

        $insertData = [
            'title'           => $parseResult['title'] ?: pathinfo($originalName, PATHINFO_FILENAME),
            'file_path'       => $relativePath,
            'file_name'       => $originalName,
            'file_size'       => (int) $row['file_size'],
            'file_extension'  => $row['file_extension'],
            'page_count'      => $parseResult['page_count'],
            'word_count'      => $counts['word_count'],
            'char_count'      => $counts['char_count'],
            'full_text'       => $parseResult['full_text'],
            'normalized_text' => $normalizedText,
            'content_hash'    => $hash,
            'is_indexed'      => 1,
            'indexed_by'      => $row['uploaded_by'] ?? null,
        ];

        if (!empty($row['document_type'])) { $insertData['document_type'] = $row['document_type']; }
        if (!empty($row['court_level']))   { $insertData['court_level']   = $row['court_level']; }
        if (!empty($row['case_number']))   { $insertData['case_number']   = $row['case_number']; }
        if (!empty($row['document_date'])) { $insertData['document_date'] = $row['document_date']; }
        if (!empty($row['scope_id']))      { $insertData['scope_id']      = (int) $row['scope_id']; }

        try {
            $docId = $this->docModel->insert($insertData);
        } catch (\Throwable $e) {
            $this->queueModel->markFailed($id, 'DB error: ' . $e->getMessage());
            CLI::write("  [FAIL] #{$id} {$originalName} — DB: " . $e->getMessage(), 'red');
            return;
        }

        $this->queueModel->markProcessed($id, (int) $docId);
        $this->auditModel->log('document_indexed', "Indexed (queue): {$originalName}", 'document', $docId);

        CLI::write("  [OK]   #{$id} {$originalName} → doc #{$docId}", 'green');
    }

    /**
     * Compute word and character counts from plain text.
     * Mirrors DocumentController::computeTextCounts().
     */
    private function computeTextCounts(string $text): array
    {
        $clean = preg_replace('/\s+/u', ' ', trim($text));
        return [
            'word_count' => $clean ? str_word_count($clean) : 0,
            'char_count' => mb_strlen($clean),
        ];
    }
}
