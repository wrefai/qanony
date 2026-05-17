<?php

namespace App\Services;

use App\Libraries\ArabicTextNormalizer;

/**
 * Arabic Text Extraction Service.
 *
 * Extracts clean UTF-8 text from Word documents for search indexing.
 * Produces two versions:
 * - Raw text: original text preserving paragraph structure
 * - Normalized text: Arabic-normalized text for fulltext search (via ArabicTextNormalizer)
 *
 * Caching: extracted text is stored in writable/uploads/documents/extracted/
 * keyed by content hash.
 */
class ArabicTextExtractionService
{
    /** Extracted text cache directory (relative to WRITEPATH) */
    private const EXTRACTED_DIR = 'uploads/documents/extracted/';

    /** Maximum text length (50M characters) */
    private const MAX_TEXT_LENGTH = 50000000;

    /** Characters per page estimate for Arabic text */
    private const CHARS_PER_PAGE = 3000;

    /**
     * Get the extracted text cache directory (absolute path).
     */
    public static function getExtractedDir(): string
    {
        $dir = WRITEPATH . self::EXTRACTED_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Extract text from a Word document.
     *
     * @param string $filePath     Absolute path to .docx or .doc file
     * @param string $contentHash  SHA-256 hash for cache key
     * @param bool   $forceRefresh Skip cache and re-extract
     * @return array{success: bool, full_text: string, normalized_text: string, page_count: int, title: string, word_count: int, char_count: int, error: string}
     */
    public static function extract(string $filePath, string $contentHash, bool $forceRefresh = false): array
    {
        $result = [
            'success'         => false,
            'full_text'       => '',
            'normalized_text' => '',
            'page_count'      => 0,
            'title'           => '',
            'word_count'      => 0,
            'char_count'      => 0,
            'error'           => '',
        ];

        if (!file_exists($filePath)) {
            $result['error'] = 'File not found: ' . $filePath;
            log_message('error', '[TextExtract] ' . $result['error']);
            return $result;
        }

        // Check cache
        $extractedDir = self::getExtractedDir();
        $rawCachePath = $extractedDir . $contentHash . '.txt';
        $normCachePath = $extractedDir . $contentHash . '.normalized.txt';

        if (!$forceRefresh && file_exists($rawCachePath) && file_exists($normCachePath)) {
            $rawText = file_get_contents($rawCachePath);
            $normText = file_get_contents($normCachePath);
            if ($rawText !== false && $normText !== false) {
                log_message('info', "[TextExtract] Cache hit for {$contentHash}");
                $result['success'] = true;
                $result['full_text'] = $rawText;
                $result['normalized_text'] = $normText;
                $result['page_count'] = max(1, (int) ceil(mb_strlen($rawText) / self::CHARS_PER_PAGE));
                $result['title'] = self::extractTitleFromText($rawText);
                $counts = self::computeTextCounts($rawText);
                $result['word_count'] = $counts['word_count'];
                $result['char_count'] = $counts['char_count'];
                return $result;
            }
        }

        // Extract fresh text using PhpWord
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        try {
            $reader = null;
            if ($extension === 'docx') {
                $reader = \PhpOffice\PhpWord\IOFactory::createReader('Word2007');
            } elseif ($extension === 'doc') {
                $reader = \PhpOffice\PhpWord\IOFactory::createReader('MsDoc');
            } else {
                $result['error'] = 'Unsupported file format: ' . $extension;
                return $result;
            }

            $phpWord = $reader->load($filePath);

            // Extract full text from all sections
            $textParts = [];
            foreach ($phpWord->getSections() as $section) {
                self::extractTextFromElement($section, $textParts);
            }

            $fullText = implode("\n", $textParts);

            // Enforce text length limit
            if (mb_strlen($fullText) > self::MAX_TEXT_LENGTH) {
                $fullText = mb_substr($fullText, 0, self::MAX_TEXT_LENGTH);
            }

            // Extract title from document properties or first line
            $title = '';
            $docInfo = $phpWord->getDocInfo();
            if ($docInfo && $docInfo->getTitle()) {
                $title = $docInfo->getTitle();
            }
            if (empty($title)) {
                $title = self::extractTitleFromText($fullText);
            }

            // Normalize text for search
            $normalizedText = ArabicTextNormalizer::normalize($fullText);

            // Compute counts
            $counts = self::computeTextCounts($fullText);
            $pageCount = max(1, (int) ceil(mb_strlen($fullText) / self::CHARS_PER_PAGE));

            // Cache results
            file_put_contents($rawCachePath, $fullText);
            file_put_contents($normCachePath, $normalizedText);
            log_message('info', "[TextExtract] Extracted and cached text for {$contentHash} ({$counts['char_count']} chars)");

            $result['success'] = true;
            $result['full_text'] = $fullText;
            $result['normalized_text'] = $normalizedText;
            $result['page_count'] = $pageCount;
            $result['title'] = $title;
            $result['word_count'] = $counts['word_count'];
            $result['char_count'] = $counts['char_count'];

        } catch (\Throwable $e) {
            $result['error'] = 'Text extraction failed: ' . $e->getMessage();
            log_message('error', "[TextExtract] {$result['error']}");
        }

        return $result;
    }

    /**
     * Recursively extract text from PhpWord elements, preserving paragraph boundaries.
     *
     * Each paragraph produces one text entry. Consecutive inline children within
     * a paragraph are concatenated. Paragraphs are separated by blank lines.
     *
     * @param mixed        $element PhpWord element (Section, TextRun, Text, etc.)
     * @param list<string> &$parts  Accumulated text parts
     */
    private static function extractTextFromElement($element, array &$parts): void
    {
        $className = get_class($element);

        // Section: iterate children
        if ($element instanceof \PhpOffice\PhpWord\Element\Section) {
            foreach ($element->getElements() as $child) {
                self::extractTextFromElement($child, $parts);
            }
            return;
        }

        // Table: extract rows -> cells -> children
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    if (method_exists($row, 'getCells')) {
                        foreach ($row->getCells() as $cell) {
                            if (method_exists($cell, 'getElements')) {
                                foreach ($cell->getElements() as $cellChild) {
                                    self::extractTextFromElement($cellChild, $parts);
                                }
                            }
                        }
                    }
                }
            }
            return;
        }

        // Paragraph-level: TextRun, ListItemRun, etc.
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun
            || (method_exists($element, 'getElements')
                && ($element instanceof \PhpOffice\PhpWord\Element\ListItemRun
                    || str_contains($className, 'Paragraph')
                    || str_contains($className, 'ListItemRun')))) {
            $line = self::extractInlineText($element);
            if (trim($line) !== '') {
                $parts[] = $line;
                $parts[] = '';
            }
            return;
        }

        // ListItem (non-run variant)
        if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
            $textObj = $element->getTextObject();
            if ($textObj) {
                $line = self::extractInlineText($textObj);
                if (trim($line) !== '') {
                    $parts[] = $line;
                    $parts[] = '';
                }
            }
            return;
        }

        // Direct Text element (standalone)
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text) && trim($text) !== '') {
                $parts[] = $text;
                $parts[] = '';
            } elseif (is_object($text)) {
                $line = self::extractInlineText($text);
                if (trim($line) !== '') {
                    $parts[] = $line;
                    $parts[] = '';
                }
            }
            return;
        }

        // Generic container fallback
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                self::extractTextFromElement($child, $parts);
            }
        }
    }

    /**
     * Extract inline text from a paragraph-level element.
     *
     * @param mixed $element A paragraph, TextRun, or similar inline container
     * @return string Concatenated text content
     */
    private static function extractInlineText($element): string
    {
        $buffer = '';

        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text)) {
                return $text;
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                if ($child instanceof \PhpOffice\PhpWord\Element\TextBreak) {
                    $buffer .= "\n";
                    continue;
                }

                // TextRun children: recurse into them (don't also call getText)
                if ($child instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $buffer .= self::extractInlineText($child);
                    continue;
                }

                if (method_exists($child, 'getText')) {
                    $childText = $child->getText();
                    if (is_string($childText)) {
                        $buffer .= $childText;
                    } elseif (is_object($childText)) {
                        $buffer .= self::extractInlineText($childText);
                    }
                }
            }
        }

        return $buffer;
    }

    /**
     * Extract title from the first non-empty line of text.
     *
     * @param string $text Full document text
     * @return string Title (max 255 characters)
     */
    private static function extractTitleFromText(string $text): string
    {
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                return mb_substr($trimmed, 0, 255);
            }
        }
        return '';
    }

    /**
     * Compute word and character counts from text.
     *
     * @param string|null $text Full text content
     * @return array{word_count: int, char_count: int}
     */
    private static function computeTextCounts(?string $text): array
    {
        if (empty($text)) {
            return ['word_count' => 0, 'char_count' => 0];
        }
        $trimmed = trim($text);
        $wordCount = $trimmed !== '' ? count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY)) : 0;
        $charCount = mb_strlen($text);
        return ['word_count' => $wordCount, 'char_count' => $charCount];
    }

    /**
     * Clear the text extraction cache for a specific document.
     *
     * @param string $contentHash SHA-256 hash
     */
    public static function clearCache(string $contentHash): void
    {
        $extractedDir = self::getExtractedDir();
        $files = [
            $extractedDir . $contentHash . '.txt',
            $extractedDir . $contentHash . '.normalized.txt',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
                log_message('info', "[TextExtract] Cleared cache: {$file}");
            }
        }
    }
}
