<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DocumentParser;
use App\Libraries\ArabicTextNormalizer;
use App\Models\LegalDocumentModel;

/**
 * Internal subprocess worker called by BulkImport for each file.
 *
 * Outputs exactly one line:
 *   imported   — success
 *   duplicate  — hash already exists
 *   <error>    — any failure message
 *
 * This command is NOT meant to be called directly by the user.
 *
 * @internal
 * @codeCoverageIgnore
 */
class ImportOne extends BaseCommand
{
    protected $group       = 'Docs';
    protected $name        = 'docs:import-one';
    protected $description = '[internal] Parse and insert a single document file';

    protected $usage   = 'docs:import-one --dest=<path> --src=<path> --relpath=<rel> --name=<name> --ext=<ext> --hash=<sha256> --user=<id> [--scope=<id>]';
    protected $options = [
        '--dest'    => 'Copied destination path',
        '--src'     => 'Original source path (for file_name)',
        '--relpath' => 'Relative path to store in DB',
        '--name'    => 'Original filename',
        '--ext'     => 'File extension',
        '--hash'    => 'SHA-256 content hash',
        '--user'    => 'indexed_by user ID',
        '--scope'   => 'scope_id (optional)',
    ];

    public function run(array $params): void
    {
        // Suppress all output except our single result line
        ini_set('display_errors', '0');
        error_reporting(0);

        // Parse argv directly (CI4 parses --key=val as "key=val" key in some versions)
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

        $destPath     = $cliArgs['dest']    ?? '';
        $srcPath      = $cliArgs['src']     ?? '';
        $relPath      = $cliArgs['relpath'] ?? '';
        $originalName = $cliArgs['name']    ?? '';
        $extension    = $cliArgs['ext']     ?? '';
        $hash         = $cliArgs['hash']    ?? '';
        $userId       = (int) ($cliArgs['user']  ?? 1);
        $scopeId      = isset($cliArgs['scope']) ? (int) $cliArgs['scope'] : null;

        // Double-check hash is not already in DB (race condition between workers)
        $db  = \Config\Database::connect();
        $exists = $db->table('legal_documents')
                     ->where('content_hash', $hash)
                     ->countAllResults();
        if ($exists > 0) {
            echo 'duplicate';
            return;
        }

        // Parse
        $parseResult = DocumentParser::parse($destPath);
        if (!$parseResult['success']) {
            echo $parseResult['error'] ?? 'parse failed';
            return;
        }

        // Build insert
        $normalizedText = ArabicTextNormalizer::normalize($parseResult['full_text']);
        $clean          = preg_replace('/\s+/u', ' ', trim($parseResult['full_text']));

        $insertData = [
            'title'           => $parseResult['title'] ?: pathinfo($originalName, PATHINFO_FILENAME),
            'file_path'       => $relPath,
            'file_name'       => $originalName,
            'file_size'       => (int) @filesize($srcPath),
            'file_extension'  => $extension,
            'page_count'      => $parseResult['page_count'],
            'word_count'      => $clean ? str_word_count($clean) : 0,
            'char_count'      => mb_strlen($clean),
            'full_text'       => $parseResult['full_text'],
            'normalized_text' => $normalizedText,
            'content_hash'    => $hash,
            'is_indexed'      => 1,
            'indexed_by'      => $userId,
            'scope_id'        => $scopeId,
        ];

        try {
            $docModel = new LegalDocumentModel();
            $docModel->insert($insertData);
            echo 'imported';
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate entry') !== false
                || stripos($msg, 'UNIQUE constraint') !== false) {
                echo 'duplicate';
            } else {
                echo 'DB error: ' . $msg;
            }
        }
    }
}
