<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DocumentParser;
use App\Libraries\ArabicTextNormalizer;
use App\Models\LegalDocumentModel;

/**
 * Re-parse existing .doc documents in the database using the improved binary
 * UTF-16LE extraction (instead of the old PhpWord MsDoc reader which produced
 * duplicated / mangled Arabic text).
 *
 * Usage:
 *   php spark docs:reparse-doc
 *
 *   # Dry-run: show what would change without writing to DB
 *   php spark docs:reparse-doc --dry-run
 *
 *   # Limit to N documents (for testing)
 *   php spark docs:reparse-doc --limit=10
 *
 *   # Only re-parse a specific document by ID
 *   php spark docs:reparse-doc --id=42
 *
 * @codeCoverageIgnore
 */
class ReparseDocFiles extends BaseCommand
{
    protected $group       = 'Docs';
    protected $name        = 'docs:reparse-doc';
    protected $description = 'Re-parse .doc documents with the improved binary UTF-16LE extractor';

    protected $usage   = 'docs:reparse-doc [--dry-run] [--limit=<n>] [--id=<n>]';
    protected $options = [
        '--dry-run' => 'Show what would change without writing to DB',
        '--limit'   => 'Max number of documents to process (default: all)',
        '--id'      => 'Re-parse a single document by its DB id',
    ];

    private LegalDocumentModel $docModel;

    public function run(array $params): void
    {
        $this->docModel = new LegalDocumentModel();

        $dryRun = CLI::getOption('dry-run') !== null;
        $limit  = (int) (CLI::getOption('limit') ?? 0);
        $singleId = (int) (CLI::getOption('id') ?? 0);

        if ($dryRun) {
            CLI::write('[docs:reparse-doc] DRY-RUN mode — no DB writes', 'yellow');
        }

        // Fetch target rows
        $db    = \Config\Database::connect();
        $query = $db->table('legal_documents')
                    ->select('id, file_path, file_name, file_extension, title')
                    ->where('file_extension', 'doc');

        if ($singleId > 0) {
            $query->where('id', $singleId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get()->getResultArray();

        if (empty($rows)) {
            CLI::write('No .doc documents found.', 'yellow');
            return;
        }

        CLI::write('[docs:reparse-doc] Found ' . count($rows) . ' .doc document(s) to re-parse.', 'cyan');

        $ok = $skipped = $failed = 0;

        foreach ($rows as $row) {
            $id       = (int) $row['id'];
            $relPath  = $row['file_path'];
            $fullPath = WRITEPATH . $relPath;
            $name     = $row['file_name'];

            if (!file_exists($fullPath)) {
                CLI::write("  [SKIP] #{$id} {$name} — file not found: {$fullPath}", 'yellow');
                $skipped++;
                continue;
            }

            $result = DocumentParser::parse($fullPath);

            if (!$result['success']) {
                CLI::write("  [FAIL] #{$id} {$name} — " . ($result['error'] ?? 'parse error'), 'red');
                $failed++;
                continue;
            }

            $newText  = $result['full_text'];
            $newTitle = $result['title'] ?: pathinfo($name, PATHINFO_FILENAME);
            $newPages = $result['page_count'];

            if ($dryRun) {
                $preview = mb_substr($newText, 0, 120);
                CLI::write("  [DRY]  #{$id} {$name}");
                CLI::write("         title: {$newTitle}");
                CLI::write("         pages: {$newPages}  chars: " . mb_strlen($newText));
                CLI::write("         text : {$preview}…");
                $ok++;
                continue;
            }

            // Normalize for search
            $normalizedText = ArabicTextNormalizer::normalize($newText);

            $this->docModel->update($id, [
                'full_text'       => $newText,
                'normalized_text' => $normalizedText,
                'title'           => $newTitle,
                'page_count'      => $newPages,
            ]);

            CLI::write("  [OK]   #{$id} {$name} — {$newPages} pages, " . mb_strlen($newText) . ' chars', 'green');
            $ok++;
        }

        CLI::write('');
        CLI::write(sprintf(
            '[docs:reparse-doc] Done. OK=%d  Skipped=%d  Failed=%d',
            $ok,
            $skipped,
            $failed
        ), $failed > 0 ? 'red' : 'green');
    }
}
