<?php

namespace App\Libraries;

use PhpOffice\PhpWord\IOFactory;

/**
 * Document parser for DOCX and DOC files.
 *
 * Ported from C# Qanony.Infrastructure.DocumentProcessing.DocxParser & DocParser.
 * Uses PhpOffice\PhpWord for both .docx and .doc formats.
 *
 * Security limits:
 * - Max file size: 200 MB
 * - Max text length: 50 million characters
 */
class DocumentParser
{
    private const MAX_FILE_SIZE    = 200 * 1024 * 1024; // 200 MB
    private const MAX_TEXT_LENGTH  = 50_000_000;         // 50M chars
    private const CHARS_PER_PAGE   = 3000;               // Arabic page estimate

    /**
     * Parse a document file and extract text + metadata.
     *
     * @param string $filePath Absolute path to the file
     * @return array{success: bool, full_text: string, page_count: int, title: string, error: string}
     */
    public static function parse(string $filePath): array
    {
        $result = [
            'success'    => false,
            'full_text'  => '',
            'page_count' => 0,
            'title'      => '',
            'error'      => '',
        ];

        // Validate file exists
        if (!file_exists($filePath)) {
            $result['error'] = 'File not found: ' . $filePath;
            return $result;
        }

        // Check file size
        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_FILE_SIZE) {
            $result['error'] = 'File exceeds maximum size of 200 MB';
            return $result;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['docx', 'doc'], true)) {
            $result['error'] = 'Unsupported file format: ' . $extension;
            return $result;
        }

        try {
            if ($extension === 'docx') {
                // DOCX: use PhpWord Word2007 reader (reliable for modern format)
                $reader  = IOFactory::createReader('Word2007');
                $phpWord = $reader->load($filePath);

                $textParts = [];
                foreach ($phpWord->getSections() as $section) {
                    self::extractText($section, $textParts);
                }
                $fullText = implode("\n", $textParts);

                // Title from doc properties or first line
                $title   = '';
                $docInfo = $phpWord->getDocInfo();
                if ($docInfo && $docInfo->getTitle()) {
                    $title = $docInfo->getTitle();
                }
                if (empty($title)) {
                    foreach ($textParts as $part) {
                        $t = trim($part);
                        if ($t !== '') {
                            $title = mb_substr($t, 0, 255);
                            break;
                        }
                    }
                }
            } else {
                // DOC (Word97-2003 binary): use PhpWord MsDoc reader, then apply
                // aggressive deduplication to remove the piece-table artefacts where
                // the same sentence appears multiple times as growing prefix/suffix
                // fragments (e.g. "بالجلسة", "بالجلسة المنعقدة", "بالجلسة المنعقدة علنا…").
                [$fullText, $title] = self::extractDocMsDoc($filePath);
            }

            // Enforce text length limit
            if (mb_strlen($fullText) > self::MAX_TEXT_LENGTH) {
                $fullText = mb_substr($fullText, 0, self::MAX_TEXT_LENGTH);
            }

            // Estimate page count
            $pageCount = max(1, (int) ceil(mb_strlen($fullText) / self::CHARS_PER_PAGE));

            $result['success']    = true;
            $result['full_text']  = $fullText;
            $result['page_count'] = $pageCount;
            $result['title']      = $title;

        } catch (\Throwable $e) {
            $result['error'] = 'Failed to parse document: ' . $e->getMessage();
        }

        return $result;
    }


    /**
     * Extract text from a legacy .doc (Word97-2003 binary) file.
     *
     * Strategy: Read the Word Document Stream (WorkDocument) directly from the OLE/CFB
     * container using PhpWord's OLERead, then extract Arabic text runs from this stream
     * using UTF-16LE regex scanning.
     *
     * Reading from `dataWorkDocument` (not the raw file) is critical because:
     * - The raw .doc file is a CFB (Compound File Binary) container whose sectors are
     *   NOT stored contiguously on disk. Scanning the raw file produces fragmented,
     *   incomplete text runs.
     * - OLERead reassembles the correct sector chain and returns a contiguous string
     *   of the WordDocument stream, so regex scanning finds all text runs intact.
     *
     * Deduplication: the stream contains the piece table residue where each logical
     * paragraph appears once. Exact-duplicate lines are still discarded as a safety net.
     *
     * @param  string $filePath Absolute path to the .doc file
     * @return array{0: string, 1: string}  [full_text, title]
     */
    private static function extractDocMsDoc(string $filePath): array
    {
        // Extract the WordDocument stream using PhpWord OLERead (reassembles sectors)
        $ole    = new \PhpOffice\PhpWord\Shared\OLERead();
        $ole->read($filePath);
        $stream = $ole->getStream($ole->wrkdocument);

        if (empty($stream)) {
            return ['', ''];
        }

        // Restrict scanning to the main document text only.
        //
        // The WordDocument stream layout (from the FIB — File Information Block):
        //   - Text starts at byte offset fcMin  (FIB offset 24, uint32 LE)
        //   - fExtChar flag (FIB offset 10, bit 2): 1 = Unicode (2 bytes/char), 0 = ANSI (1 byte/char)
        //   - ccpText (FIB offset 76, uint32 LE): character count of the main body text only
        //     (excludes footnotes, headers, footers, annotations that follow in the same stream)
        //
        // For Unicode docs: main text occupies bytes [fcMin .. fcMin + ccpText*2)
        // For ANSI docs:    main text occupies bytes [fcMin .. fcMin + ccpText)
        //   but ANSI Arabic is Windows-1256; our UTF-16LE regex won't match it anyway,
        //   so we fall back to full-stream scan for those (rare) cases.
        $scanData = $stream; // default: full stream
        if (strlen($stream) >= 80) {
            $flagsWord = unpack('v', substr($stream, 10, 2))[1];
            $fExtChar  = ($flagsWord >> 2) & 1;   // 1 = Unicode
            $fcMin     = unpack('V', substr($stream, 24, 4))[1];
            $ccpText   = unpack('V', substr($stream, 76, 4))[1];

            if ($fExtChar === 1 && $ccpText > 0) {
                $byteLen = $ccpText * 2;
                $end     = $fcMin + $byteLen;
                if ($fcMin < strlen($stream) && $end <= strlen($stream)) {
                    $scanData = substr($stream, $fcMin, $byteLen);
                }
            }
        }

        // Scan the stream for Arabic text runs encoded as UTF-16LE.
        //
        // UTF-16LE unit definitions:
        //   Arabic char   : <any_byte> 0x06  →  U+0600–U+06FF  (Arabic block)
        //   ASCII visible : <0x20–0x7E> 0x00 →  space, digits, Latin, punctuation
        //   Arabic punct  : 0x0C\x06 (،), 0x1B\x06 (؛), 0x1F\x06 (؟), etc. — covered by arabicUnit
        //
        // We match any run that:
        //   - contains at least one Arabic char (starts OR ends with one)
        //   - can include ASCII chars (for dates like "30/9/2002")
        //
        // Note: the regex operates on raw bytes; PCRE's dot does NOT match \n by default
        // but we use explicit character classes so that's irrelevant here.
        $arabicUnit = '[\x00-\xFF]\x06';
        $asciiUnit  = '[\x20-\x7E]\x00';
        $unit       = "(?:$arabicUnit|$asciiUnit)";

        preg_match_all(
            "/(?:$arabicUnit)$unit*(?:$arabicUnit)/",
            $scanData,
            $matches
        );

        $paragraphs = [];
        $seen       = [];

        foreach ($matches[0] as $raw) {
            $utf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');

            // Normalise internal whitespace
            $utf8 = preg_replace('/[ \t]+/u', ' ', $utf8);
            $line = trim($utf8);

            if ($line === '' || mb_strlen($line) < 2) {
                continue;
            }

            // Must contain at least one Arabic character
            if (!preg_match('/[\x{0600}-\x{06FF}]/u', $line)) {
                continue;
            }

            // Discard "jumbled" lines: artefacts from annotation/header/footer streams
            // that appear at the end of the WordDocument stream. Heuristic: if the ratio
            // of non-space characters to total characters exceeds 0.92 AND the line is
            // longer than 30 chars, it's likely concatenated fragments with no spaces.
            if (mb_strlen($line) > 30) {
                $spaces    = mb_strlen(preg_replace('/\S/u', '', $line));
                $spaceRatio = $spaces / mb_strlen($line);
                if ($spaceRatio < 0.08) {
                    continue;
                }
            }

            // Exact-duplicate guard
            if (isset($seen[$line])) {
                continue;
            }
            $seen[$line] = true;
            $paragraphs[] = $line;
        }

        $fullText = implode("\n", $paragraphs);

        // Title = first non-empty paragraph
        $title = '';
        foreach ($paragraphs as $p) {
            $t = trim($p);
            if ($t !== '') {
                $title = mb_substr($t, 0, 255);
                break;
            }
        }

        return [$fullText, $title];
    }

    /**
     * Recursively extract text from PhpWord elements, preserving paragraph boundaries.
     *
     * Each Paragraph produces one text entry. Consecutive Text/TextRun children within
     * a single Paragraph are concatenated (not split into separate lines). Paragraphs
     * are separated by blank lines in the final output via the caller joining with "\n".
     *
     * @param mixed        $element   PhpWord element (Section, TextRun, Text, etc.)
     * @param list<string> &$parts    Accumulated text parts (one per paragraph)
     */
    private static function extractText($element, array &$parts): void
    {
        $className = get_class($element);

        // Section: iterate child elements (Paragraphs, Tables, TextRuns, etc.)
        // Used only for .docx (Word2007 reader). For .doc we use extractDocBinary().
        if ($element instanceof \PhpOffice\PhpWord\Element\Section) {
            foreach ($element->getElements() as $child) {
                self::extractText($child, $parts);
            }
            return;
        }

        // Table: extract each row → each cell → recurse into cell elements
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    if (method_exists($row, 'getCells')) {
                        foreach ($row->getCells() as $cell) {
                            if (method_exists($cell, 'getElements')) {
                                foreach ($cell->getElements() as $cellChild) {
                                    self::extractText($cellChild, $parts);
                                }
                            }
                        }
                    }
                }
            }
            return;
        }

        // Paragraph (including ListItem which extends AbstractContainer):
        // Concatenate all inline children into a single text entry.
        if ($element instanceof \PhpOffice\PhpWord\Element\TextRun
            || (method_exists($element, 'getElements')
                && ($element instanceof \PhpOffice\PhpWord\Element\ListItemRun
                    || str_contains($className, 'Paragraph')
                    || str_contains($className, 'ListItemRun')))) {
            $line = self::extractInlineText($element);
            if (trim($line) !== '') {
                $parts[] = $line;
                // Add blank line after paragraph to preserve paragraph separation
                $parts[] = '';
            }
            return;
        }

        // ListItem (non-run variant): has getText() returning a string or TextRun
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

        // Direct Text element (standalone, outside paragraph — rare)
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

        // Generic container fallback: recurse into children
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                self::extractText($child, $parts);
            }
        }
    }

    /**
     * Extract inline text from a paragraph-level element by concatenating all
     * child Text/TextRun elements into a single string.
     *
     * @param mixed $element A paragraph, TextRun, or similar inline container
     * @return string The concatenated text content
     */
    private static function extractInlineText($element): string
    {
        $buffer = '';

        // If the element itself has a direct string getText(), use it
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_string($text)) {
                return $text;
            }
        }

        // Iterate child elements (Text, TextRun, TextBreak, etc.)
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                if ($child instanceof \PhpOffice\PhpWord\Element\TextBreak) {
                    $buffer .= "\n";
                    continue;
                }

                if (method_exists($child, 'getText')) {
                    $childText = $child->getText();
                    if (is_string($childText)) {
                        $buffer .= $childText;
                    } elseif (is_object($childText)) {
                        // Nested TextRun
                        $buffer .= self::extractInlineText($childText);
                    }
                }

                // TextRun children (recursion)
                if ($child instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $buffer .= self::extractInlineText($child);
                }
            }
        }

        return $buffer;
    }

    /**
     * Compute SHA-256 hash of a file for deduplication.
     *
     * @param string $filePath Absolute path to file
     * @return string Hex-encoded SHA-256 hash
     */
    public static function computeHash(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * Check if a file extension is supported.
     */
    public static function isSupported(string $extension): bool
    {
        return in_array(strtolower($extension), ['docx', 'doc'], true);
    }
}
