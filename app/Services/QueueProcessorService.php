<?php

namespace App\Services;

use App\Libraries\DocumentParser;
use App\Libraries\ArabicTextNormalizer;
use App\Models\LegalDocumentModel;
use App\Models\AuditLogModel;
use App\Models\UploadQueueModel;

/**
 * QueueProcessorService
 *
 * Processes pending rows from upload_queue after the HTTP response has been
 * sent to the browser, using fastcgi_finish_request() (available on XAMPP/PHP-FPM).
 *
 * Usage (in a controller, before returning the response):
 *   QueueProcessorService::scheduleAfterResponse();
 *
 * How it works:
 *   1. scheduleAfterResponse() registers a shutdown function (only once per request).
 *   2. After CodeIgniter sends the HTTP response, PHP calls the shutdown function.
 *   3. fastcgi_finish_request() flushes & closes the connection to the browser.
 *   4. PHP continues running and processes a small batch of pending queue rows.
 *
 * Batch size is intentionally small (BATCH_SIZE = 10) so each upload request
 * only adds a short burst of background work. With CONCURRENCY = 5 uploads in
 * parallel, up to 50 documents are being parsed concurrently in the background
 * while the browser is already free.
 *
 * On plain mod_php (no fastcgi_finish_request), the function is a no-op and
 * the queue must be drained by the CLI worker instead.
 */
class QueueProcessorService
{
    /** Documents to process per upload request (keeps background time < ~5s) */
    private const BATCH_SIZE = 8;

    /** Prevent registering the shutdown function more than once per request */
    private static bool $scheduled = false;

    /**
     * Register the background processor to run after the HTTP response is sent.
     * Safe to call multiple times — only registers once per PHP process lifetime.
     */
    public static function scheduleAfterResponse(): void
    {
        if (self::$scheduled) {
            return;
        }
        self::$scheduled = true;

        register_shutdown_function(function () {
            // Release the PHP session lock first so concurrent AJAX uploads are
            // not blocked while we parse in the background.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            // Flush & close the browser connection so the response is sent
            // before we start the (potentially slow) document parsing.
            if (function_exists('fastcgi_finish_request')) {
                // PHP-FPM / FastCGI: ideal path — instantly releases the connection
                fastcgi_finish_request();
            } else {
                // mod_php fallback: flush all output buffers and the system buffer
                // so Apache sends the response headers + body to the client now.
                $level = ob_get_level();
                for ($i = 0; $i < $level; $i++) {
                    ob_end_flush();
                }
                flush();
                // Set the connection header on next request (already sent above).
                // We cannot fully detach from Apache under mod_php, but flushing
                // means the browser receives the response while PHP continues
                // running synchronously in the shutdown handler.
                if (function_exists('apache_setenv')) {
                    apache_setenv('no-gzip', '1');
                }
                if (function_exists('set_time_limit')) {
                    set_time_limit(120); // allow up to 2 min for background parse
                }
            }

            // Process a batch of pending queue rows
            try {
                self::processBatch(self::BATCH_SIZE);
            } catch (\Throwable $e) {
                log_message('error', '[QueueProcessor] Uncaught: ' . $e->getMessage());
            }
        });
    }

    /**
     * Claim and process up to $limit pending queue rows.
     * Can also be called directly (e.g. from the CLI worker or a cron endpoint).
     */
    public static function processBatch(int $limit = 50): int
    {
        $queueModel = new UploadQueueModel();
        $docModel   = new LegalDocumentModel();
        $auditModel = new AuditLogModel();

        // Reset rows that got stuck in 'processing' for more than 30 minutes
        $queueModel->resetStuck(30);

        $rows = $queueModel->claimPending($limit);

        $processed = 0;
        foreach ($rows as $row) {
            self::processRow($row, $queueModel, $docModel, $auditModel);
            $processed++;
        }

        return $processed;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function processRow(
        array            $row,
        UploadQueueModel $queueModel,
        LegalDocumentModel $docModel,
        AuditLogModel    $auditModel
    ): void {
        $id           = (int) $row['id'];
        $relativePath = $row['file_path'];
        $fullPath     = WRITEPATH . $relativePath;
        $originalName = $row['original_name'];

        // File missing
        if (!file_exists($fullPath)) {
            $queueModel->markFailed($id, 'File not found: ' . $fullPath);
            log_message('error', "[QueueProcessor] #{$id} file missing: {$fullPath}");
            return;
        }

        // Duplicate check (another request may have processed the same hash already)
        $hash = DocumentParser::computeHash($fullPath);
        if ($docModel->existsByHash($hash)) {
            $queueModel->markDuplicate($id);
            return;
        }

        // Parse
        $parseResult = DocumentParser::parse($fullPath);
        if (!$parseResult['success']) {
            $queueModel->markFailed($id, $parseResult['error'] ?? 'Parse error');
            log_message('error', "[QueueProcessor] #{$id} parse error: " . ($parseResult['error'] ?? ''));
            return;
        }

        $normalizedText = ArabicTextNormalizer::normalize($parseResult['full_text']);
        $clean          = preg_replace('/\s+/u', ' ', trim($parseResult['full_text']));
        $wordCount      = $clean ? str_word_count($clean) : 0;
        $charCount      = mb_strlen($clean);

        $insertData = [
            'title'           => $parseResult['title'] ?: pathinfo($originalName, PATHINFO_FILENAME),
            'file_path'       => $relativePath,
            'file_name'       => $originalName,
            'file_size'       => (int) $row['file_size'],
            'file_extension'  => $row['file_extension'],
            'page_count'      => $parseResult['page_count'],
            'word_count'      => $wordCount,
            'char_count'      => $charCount,
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
            $docId = $docModel->insert($insertData);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Race condition: another concurrent process inserted the same content_hash
            // just after our existsByHash() check passed. Treat as duplicate, not failure.
            if (stripos($msg, 'Duplicate entry') !== false || stripos($msg, 'UNIQUE constraint failed') !== false) {
                $queueModel->markDuplicate($id);
                log_message('info', "[QueueProcessor] #{$id} race-condition duplicate: {$originalName}");
                return;
            }
            $queueModel->markFailed($id, 'DB error: ' . $msg);
            log_message('error', "[QueueProcessor] #{$id} DB error: {$msg}");
            return;
        }

        $queueModel->markProcessed($id, (int) $docId);
        $auditModel->log('document_indexed', "Indexed (auto): {$originalName}", 'document', $docId);
    }
}
