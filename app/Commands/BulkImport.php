<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DocumentParser;
use App\Libraries\ArabicTextNormalizer;
use App\Models\LegalDocumentModel;

/**
 * Bulk-import Word documents from a local folder directly into legal_documents.
 *
 * Bypasses the HTTP upload pipeline entirely — files are read from disk,
 * parsed, and inserted in batches. Suitable for importing large archives
 * (100k+ documents) without browser or web-server involvement.
 *
 * Features
 * --------
 * - SHA-256 duplicate detection (skips files already in legal_documents)
 * - Skips Word lock files (~$*.doc/docx) and files < 500 bytes automatically
 * - Copies each file into writable/uploads/documents/original/ (preserving the
 *   source directory untouched)
 * - Live progress bar updated every batch
 * - Fail log written to writable/logs/import_failed_<timestamp>.txt
 * - --offset / --limit for parallel workers (split 170k across 4 terminals)
 * - Race-condition safe: Duplicate-key DB errors treated as skips, not failures
 *
 * Usage
 * -----
 *   # Basic
 *   php spark docs:import "C:\legal_docs"
 *
 *   # With scope
 *   php spark docs:import "C:\legal_docs" --scope=5
 *
 *   # Include sub-folders
 *   php spark docs:import "C:\legal_docs" --recursive
 *
 *   # Dry-run (count only, no changes)
 *   php spark docs:import "C:\legal_docs" --dry-run
 *
 *   # Parallel workers (split 170k into 4 × 42500)
 *   php spark docs:import "C:\legal_docs" --scope=5 --offset=0      --limit=42500
 *   php spark docs:import "C:\legal_docs" --scope=5 --offset=42500  --limit=42500
 *   php spark docs:import "C:\legal_docs" --scope=5 --offset=85000  --limit=42500
 *   php spark docs:import "C:\legal_docs" --scope=5 --offset=127500 --limit=42500
 *
 * @codeCoverageIgnore
 */
class BulkImport extends BaseCommand
{
    protected $group       = 'Docs';
    protected $name        = 'docs:import';
    protected $description = 'Bulk-import .doc/.docx files from a folder directly into legal_documents';

    protected $usage   = 'docs:import <folder> [options]';
    protected $options = [
        'scope'          => 'Assign all imported documents to this search_scope ID',
        'recursive'      => 'Recurse into sub-folders',
        'dry-run'        => 'Scan and count files only — make no changes',
        'offset'         => 'Skip the first N files (for parallel workers)',
        'limit'          => 'Process at most N files (for parallel workers)',
        'batch'          => 'DB/progress flush interval (default: 50)',
        'user'           => 'User ID to record as indexed_by (default: 1)',
        'no-interaction' => 'Skip the confirmation prompt',
        'verbose'        => 'Print one line per file (slow for large imports)',
    ];

    /** Destination directory inside WRITEPATH */
    private const DEST_SUBDIR = 'uploads/documents/original/';

    /** Minimum file size to consider (bytes) — skips 0-byte lock files */
    private const MIN_FILE_BYTES = 500;

    public function run(array $params): void
    {
        ini_set('memory_limit', '2G');
        // ── 1. Parse arguments ────────────────────────────────────────────────

        // CodeIgniter parses "--key=value" as key "key=value" (bug in some versions).
        // We parse argv directly to be safe.
        $argv = $_SERVER['argv'] ?? [];
        $cliArgs = [];
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$k, $v] = explode('=', $arg, 2);
                    $cliArgs[$k] = $v;
                } else {
                    $cliArgs[$arg] = true;
                }
            }
        }

        $folder = $params[0] ?? $cliArgs['folder'] ?? '';
        if ($folder === '') {
            CLI::error('Usage: php spark docs:import <folder> [options]');
            return;
        }
        $folder = rtrim(realpath($folder) ?: $folder, DIRECTORY_SEPARATOR);

        if (!is_dir($folder)) {
            CLI::error("Folder not found: {$folder}");
            return;
        }

        $scopeId       = isset($cliArgs['scope'])          ? (int) $cliArgs['scope']  : null;
        $recursive     = isset($cliArgs['recursive']);
        $dryRun        = isset($cliArgs['dry-run']);
        $offset        = max(0, (int) ($cliArgs['offset'] ?? 0));
        $limit         = (int) ($cliArgs['limit']  ?? 0);   // 0 = unlimited
        $batchSize     = max(1, (int) ($cliArgs['batch']  ?? 50));
        $userId        = (int) ($cliArgs['user']   ?? 1);
        $noInteraction = isset($cliArgs['no-interaction']);
        $verbose       = isset($cliArgs['verbose']);

        // ── 2. Collect files ─────────────────────────────────────────────────

        CLI::write('Scanning folder: ' . $folder . ($recursive ? ' (recursive)' : ''), 'cyan');

        $allFiles = $this->collectFiles($folder, $recursive);
        $total    = count($allFiles);

        if ($total === 0) {
            CLI::write('No .doc/.docx files found.', 'yellow');
            return;
        }

        // Apply offset + limit
        $slice = ($limit > 0)
            ? array_slice($allFiles, $offset, $limit)
            : array_slice($allFiles, $offset);

        $sliceCount = count($slice);

        // ── 3. Summary + confirmation ─────────────────────────────────────────

        CLI::write('');
        CLI::write('┌─────────────────────────────────────────┐', 'cyan');
        CLI::write('│          docs:import  summary            │', 'cyan');
        CLI::write('├─────────────────────────────────────────┤', 'cyan');
        CLI::write('│ Total files found : ' . str_pad($total, 21) . '│', 'white');
        CLI::write('│ Files to process  : ' . str_pad($sliceCount, 21) . '│', 'white');
        CLI::write('│ Offset            : ' . str_pad($offset, 21) . '│', 'white');
        CLI::write('│ Scope ID          : ' . str_pad($scopeId ?? 'none', 21) . '│', 'white');
        CLI::write('│ Destination       : writable/uploads/   │', 'white');
        CLI::write('│                     documents/original/ │', 'white');
        if ($dryRun) {
            CLI::write('│ Mode              : DRY-RUN (no changes)│', 'yellow');
        }
        CLI::write('└─────────────────────────────────────────┘', 'cyan');
        CLI::write('');

        if ($sliceCount === 0) {
            CLI::write('No files in selected range.', 'yellow');
            return;
        }

        // Estimated time (rough: 25ms per file)
        $estSec = (int) ($sliceCount * 0.025);
        $estStr = $estSec < 60
            ? "{$estSec}s"
            : round($estSec / 60, 1) . 'min';
        CLI::write("Estimated time: ~{$estStr}  (single process, 25ms/file)");
        CLI::write('');

        if (!$dryRun && !$noInteraction) {
            $answer = CLI::prompt('Continue?', ['y', 'n'], 'n');
            if (strtolower($answer) !== 'y') {
                CLI::write('Aborted.', 'yellow');
                return;
            }
        }

        if ($dryRun) {
            CLI::write('Dry-run complete. No files were imported.', 'green');
            return;
        }

        // ── 4. Prepare destination & models ──────────────────────────────────

        $destDir = WRITEPATH . self::DEST_SUBDIR;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $docModel = new LegalDocumentModel();

        // Pre-load all known hashes into a memory set for O(1) lookup.
        // At 170k docs in DB each hash is 64 hex chars = ~11 MB RAM — acceptable.
        CLI::write('Loading existing hashes from DB…');
        $knownHashes = $this->loadKnownHashes($docModel);
        CLI::write('  Loaded ' . number_format(count($knownHashes)) . ' existing hashes.');
        CLI::write('');

        // Fail log
        $failLogPath = WRITEPATH . 'logs/import_failed_' . date('Ymd_His') . '.txt';
        $failLog     = [];

        // ── 5. Main import loop ───────────────────────────────────────────────

        $stats = ['imported' => 0, 'duplicate' => 0, 'failed' => 0];
        $startTime = microtime(true);
        $processed = 0;

        foreach ($slice as $srcPath) {
            $processed++;
            $originalName = basename($srcPath);
            $extension    = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));

            // ── a. Hash + duplicate check ─────────────────────────────────
            $hash = DocumentParser::computeHash($srcPath);

            if (isset($knownHashes[$hash])) {
                $stats['duplicate']++;
                if ($verbose) {
                    CLI::write("  [SKIP] {$originalName} — duplicate", 'yellow');
                }
                $this->printProgress($processed, $sliceCount, $stats, $startTime, $batchSize);
                continue;
            }

            // Mark as seen immediately (prevents in-process duplicates for
            // parallel runs that started at the same time this won't help,
            // but at least prevents re-processing within one worker)
            $knownHashes[$hash] = true;

            // ── b. Copy file to destination ───────────────────────────────
            $newName  = $this->makeRandomName($extension);
            $destPath = $destDir . $newName;
            $relPath  = self::DEST_SUBDIR . $newName;

            if (!@copy($srcPath, $destPath)) {
                $stats['failed']++;
                $failLog[] = "COPY_FAIL\t{$srcPath}";
                if ($verbose) {
                    CLI::write("  [FAIL] {$originalName} — cannot copy", 'red');
                }
                $this->printProgress($processed, $sliceCount, $stats, $startTime, $batchSize);
                continue;
            }

            // ── c. Parse in isolated subprocess to survive OOM ───────────
            //
            // PHP fatal OOM errors cannot be caught with try/catch — they kill
            // the entire process. Running parse+insert in a child process means
            // a corrupt/huge file only kills the child; the main loop continues.
            $result = $this->parseAndInsertInSubprocess(
                $destPath, $srcPath, $relPath, $originalName,
                $extension, $hash, $scopeId, $userId
            );

            if ($result === 'imported') {
                $stats['imported']++;
                if ($verbose) CLI::write("  [OK]   {$originalName}", 'green');
            } elseif ($result === 'duplicate') {
                $stats['duplicate']++;
                @unlink($destPath);
                if ($verbose) CLI::write("  [SKIP] {$originalName} — race duplicate", 'yellow');
            } else {
                // $result is an error string
                $stats['failed']++;
                @unlink($destPath);
                $failLog[] = "FAIL\t{$srcPath}\t{$result}";
                if ($verbose) CLI::write("  [FAIL] {$originalName} — {$result}", 'red');
            }

            $this->printProgress($processed, $sliceCount, $stats, $startTime, $batchSize);
        }

        // ── 6. Final summary ──────────────────────────────────────────────────

        $elapsed = round(microtime(true) - $startTime, 1);
        $elStr   = $elapsed < 60
            ? "{$elapsed}s"
            : round($elapsed / 60, 1) . 'min';

        CLI::write('');
        CLI::write('');
        CLI::write('┌─────────────────────────────────────────┐', 'cyan');
        CLI::write('│              Import complete             │', 'cyan');
        CLI::write('├─────────────────────────────────────────┤', 'cyan');
        CLI::write('│ Imported  : ' . str_pad(number_format($stats['imported']),   28) . '│', 'green');
        CLI::write('│ Duplicate : ' . str_pad(number_format($stats['duplicate']),  28) . '│', 'yellow');
        CLI::write('│ Failed    : ' . str_pad(number_format($stats['failed']),     28) . '│', $stats['failed'] > 0 ? 'red' : 'white');
        CLI::write('│ Time      : ' . str_pad($elStr,                              28) . '│', 'white');
        CLI::write('└─────────────────────────────────────────┘', 'cyan');

        if (!empty($failLog)) {
            file_put_contents($failLogPath, implode("\n", $failLog) . "\n");
            CLI::write('');
            CLI::write("Fail log written to: {$failLogPath}", 'red');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Recursively collect all .doc / .docx files from a folder.
     * Skips Word lock files (~$*) and files below MIN_FILE_BYTES.
     *
     * @return string[] Sorted list of absolute file paths
     */
    private function collectFiles(string $folder, bool $recursive): array
    {
        $files = [];

        $iterator = $recursive
            ? new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
              )
            : new \FilesystemIterator($folder, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $entry) {
            if (!$entry->isFile()) {
                continue;
            }

            $name = $entry->getFilename();
            $ext  = strtolower($entry->getExtension());

            // Skip unsupported formats
            if (!in_array($ext, ['doc', 'docx'], true)) {
                continue;
            }

            // Skip Word temporary lock files
            if (str_starts_with($name, '~$')) {
                continue;
            }

            // Skip near-empty files (lock files that somehow have a .doc extension)
            if ($entry->getSize() < self::MIN_FILE_BYTES) {
                continue;
            }

            $files[] = $entry->getPathname();
        }

        sort($files);
        return $files;
    }

    /**
     * Load all existing content_hash values from legal_documents into a
     * hash-keyed array for O(1) duplicate detection without per-file DB queries.
     *
     * Memory: 64 bytes/hash × 170,000 rows ≈ 11 MB — well within PHP limits.
     *
     * @return array<string, true>
     */
    private function loadKnownHashes(LegalDocumentModel $docModel): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->query('SELECT content_hash FROM legal_documents WHERE content_hash IS NOT NULL')
                   ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['content_hash']] = true;
        }
        return $map;
    }

    /**
     * Generate a random filename preserving the original extension.
     * Mirrors CodeIgniter's UploadedFile::getRandomName().
     */
    private function makeRandomName(string $extension): string
    {
        return time() . '_' . bin2hex(random_bytes(10)) . '.' . $extension;
    }

    /**
     * Run parse + DB insert in an isolated PHP subprocess.
     *
     * Returns 'imported' | 'duplicate' | error-string.
     * If the child dies (OOM, segfault, etc.) we get an error string and
     * the main loop continues normally.
     */
    private function parseAndInsertInSubprocess(
        string  $destPath,
        string  $srcPath,
        string  $relPath,
        string  $originalName,
        string  $extension,
        string  $hash,
        ?int    $scopeId,
        int     $userId
    ): string {
        $php    = PHP_BINARY;
        $spark  = ROOTPATH . 'spark';
        $args   = [
            $php, '-d', 'memory_limit=512M',
            $spark, 'docs:import-one',
            '--dest='    . $destPath,
            '--src='     . $srcPath,
            '--relpath=' . $relPath,
            '--name='    . $originalName,
            '--ext='     . $extension,
            '--hash='    . $hash,
            '--user='    . $userId,
        ];
        if ($scopeId !== null) {
            $args[] = '--scope=' . $scopeId;
        }

        // Build command string safely
        $cmd = implode(' ', array_map(fn($a) => escapeshellarg($a), $args));

        $output     = [];
        $returnCode = 0;
        exec($cmd . ' 2>&1', $output, $returnCode);

        // The last non-empty line is our result ('imported'|'duplicate'|error).
        // Earlier lines are the CodeIgniter CLI header which we ignore.
        $lastLine = '';
        foreach (array_reverse($output) as $line) {
            $line = trim($line);
            if ($line !== '') { $lastLine = $line; break; }
        }

        if ($lastLine === 'imported')   return 'imported';
        if ($lastLine === 'duplicate')  return 'duplicate';

        // Any other output = error (includes OOM messages from child)
        return $lastLine ?: "subprocess exited with code {$returnCode}";
    }

    /**
     * Print a progress bar to the terminal.
     * Only redraws on each batch boundary or when forced (last item).
     */
    private function printProgress(
        int   $processed,
        int   $total,
        array $stats,
        float $startTime,
        int   $batchSize
    ): void {
        if ($processed % $batchSize !== 0 && $processed !== $total) {
            return;
        }

        $pct     = $total > 0 ? (int) ($processed / $total * 100) : 0;
        $elapsed = microtime(true) - $startTime;
        $rate    = $processed > 0 ? $elapsed / $processed : 0;
        $remain  = (int) (($total - $processed) * $rate);
        $eta     = $remain < 60 ? "{$remain}s" : round($remain / 60, 1) . 'min';

        // Build bar (40 chars wide)
        $filled  = (int) ($pct / 100 * 40);
        $empty   = 40 - $filled;
        $bar     = str_repeat('=', max(0, $filled - 1))
                 . ($processed < $total ? '>' : '=')
                 . str_repeat(' ', $empty);

        $line = sprintf(
            "\r[%s] %s/%s (%d%%) | ✓ %s  ⊘ %s  ✗ %s | ETA: %s   ",
            $bar,
            number_format($processed),
            number_format($total),
            $pct,
            number_format($stats['imported']),
            number_format($stats['duplicate']),
            number_format($stats['failed']),
            $processed < $total ? $eta : 'done'
        );

        // Use fwrite to overwrite current line
        fwrite(STDOUT, $line);
    }
}
