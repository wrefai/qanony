<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\DocumentParser;
use App\Libraries\ArabicTextNormalizer;
use App\Models\LegalDocumentModel;

/**
 * Batch re-parse existing documents in the database.
 *
 * Documents already stored were parsed with the old flattened logic (single \n
 * between every text element). This command re-reads each document's original
 * file and re-parses it with the new paragraph-aware parser, then updates
 * full_text, normalized_text, page_count, word_count, and char_count.
 *
 * Usage:
 *   php spark documents:reparse           Re-parse all documents
 *   php spark documents:reparse --dry-run Preview what would be re-parsed (no DB writes)
 *   php spark documents:reparse --id=42   Re-parse a single document by ID
 *   php spark documents:reparse --limit=5 Re-parse only the first N documents
 *
 * @codeCoverageIgnore
 */
class ReparseDocuments extends BaseCommand
{
    protected $group       = 'Documents';
    protected $name        = 'documents:reparse';
    protected $description = 'Re-parse existing documents with the paragraph-aware parser to fix full_text formatting.';

    protected $usage = 'documents:reparse [--dry-run] [--id=<id>] [--limit=<n>]';

    protected $arguments = [];

    protected $options = [
        '--dry-run' => 'Preview changes without writing to the database.',
        '--id'      => 'Re-parse only the document with this ID.',
        '--limit'   => 'Limit the number of documents to re-parse.',
    ];

    public function run(array $params)
    {
        $dryRun = CLI::getOption('dry-run') !== null || (array_key_exists('dry-run', $params) && $params['dry-run'] !== false);
        $docId  = CLI::getOption('id');
        $limit  = CLI::getOption('limit');

        $model = new LegalDocumentModel();

        // Build query: select id, file_path, file_name, title
        $builder = $model->builder();
        $builder->select('id, file_path, file_name, title');

        if ($docId) {
            $builder->where('id', (int) $docId);
        }

        $builder->orderBy('id', 'ASC');

        if ($limit) {
            $builder->limit((int) $limit);
        }

        $documents = $builder->get()->getResultArray();

        if (empty($documents)) {
            CLI::write('No documents found.', 'yellow');
            return;
        }

        $total       = count($documents);
        $success     = 0;
        $skipped     = 0;
        $errors      = 0;
        $unchanged   = 0;

        CLI::write("Found {$total} document(s) to process.", 'white');
        if ($dryRun) {
            CLI::write('[DRY RUN] No changes will be written to the database.', 'yellow');
        }
        CLI::newLine();

        foreach ($documents as $index => $doc) {
            $num     = $index + 1;
            $docId   = $doc['id'];
            $title   = $doc['title'] ?: $doc['file_name'];
            $relPath = $doc['file_path'];

            CLI::write("[{$num}/{$total}] ID {$docId}: {$title}", 'white');

            // Resolve full path (supports both old flat directory and new original/ subdirectory)
            $fullPath = WRITEPATH . $relPath;

            if (!file_exists($fullPath)) {
                // Try original/ subdirectory
                $altPath = WRITEPATH . 'uploads/documents/original/' . basename($relPath);
                if (file_exists($altPath)) {
                    $fullPath = $altPath;
                } else {
                    CLI::write("  SKIP: File not found at {$fullPath}", 'yellow');
                    $skipped++;
                    continue;
                }
            }

            // Re-parse the document
            $parseResult = DocumentParser::parse($fullPath);

            if (!$parseResult['success']) {
                CLI::write("  ERROR: {$parseResult['error']}", 'red');
                $errors++;
                continue;
            }

            // Generate normalized text for search
            $normalizedText = ArabicTextNormalizer::normalize($parseResult['full_text']);

            // Compute word and character counts
            $fullText  = $parseResult['full_text'];
            $trimmed   = trim($fullText);
            $wordCount = $trimmed !== '' ? count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY)) : 0;
            $charCount = mb_strlen($fullText);

            if ($dryRun) {
                CLI::write("  DRY RUN: Would update full_text ({$charCount} chars, {$wordCount} words, {$parseResult['page_count']} pages)", 'cyan');
                $success++;
                continue;
            }

            // Update the record
            $model->update($docId, [
                'full_text'       => $fullText,
                'normalized_text' => $normalizedText,
                'page_count'      => $parseResult['page_count'],
                'word_count'      => $wordCount,
                'char_count'      => $charCount,
            ]);

            CLI::write("  OK: Updated ({$charCount} chars, {$wordCount} words, {$parseResult['page_count']} pages)", 'green');
            $success++;
        }

        CLI::newLine();
        CLI::write('=== Summary ===', 'white');
        CLI::write("  Total:     {$total}", 'white');
        CLI::write("  Success:   {$success}", 'green');
        CLI::write("  Skipped:   {$skipped}", 'yellow');
        CLI::write("  Errors:    {$errors}", $errors > 0 ? 'red' : 'white');

        if ($dryRun) {
            CLI::newLine();
            CLI::write('This was a dry run. Run without --dry-run to apply changes.', 'yellow');
        }
    }
}
