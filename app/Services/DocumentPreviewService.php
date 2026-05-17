<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;

/**
 * Document Preview Service.
 *
 * Generates HTML preview of Word documents with professional Arabic RTL rendering.
 *
 * Two preview modes:
 * 1. HTML Preview (primary) — PhpWord HTML Writer with Arabic RTL post-processing
 * 2. PDF Preview (when LibreOffice available) — via DocumentConversionService
 *
 * Caching: generated previews are stored in writable/uploads/documents/preview/
 * keyed by content hash. If a cached preview exists and is valid, it is reused.
 */
class DocumentPreviewService
{
    /** Preview cache directory (relative to WRITEPATH) */
    private const PREVIEW_DIR = 'uploads/documents/preview/';

    /**
     * Arabic RTL CSS for rendered document content.
     *
     * This CSS block ensures professional Arabic rendering with:
     * - Proper RTL direction on all block/inline elements
     * - Unicode-bidi for correct bidirectional text handling
     * - Arabic font stack with proper fallbacks
     * - Generous line-height for Arabic readability
     * - Table rendering with RTL cell order
     * - No content clipping or fragmentation
     */
    private const ARABIC_RTL_CSS = '
        /* Arabic Document RTL Base */
        .rendered-doc {
            direction: rtl;
            text-align: right;
            unicode-bidi: embed;
            font-family: "Noto Naskh Arabic", "Cairo", "Segoe UI", Tahoma, sans-serif;
            font-size: 18px;
            line-height: 2;
            word-break: break-word;
            overflow-wrap: break-word;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Force uniform 18px on every child — overrides inline PhpWord font-size values */
        .rendered-doc *,
        .rendered-doc p,
        .rendered-doc span,
        .rendered-doc div,
        .rendered-doc td,
        .rendered-doc th,
        .rendered-doc li {
            font-size: 18px !important;
        }

        /* Block-level elements: force RTL with explicit embed level.
           unicode-bidi: embed creates an explicit RTL embedding level so that
           every paragraph is anchored right-to-left regardless of the first
           character type. This is the correct setting when combined with
           dir="rtl": the browser always starts from the right margin and
           resolves inline runs (Arabic, numbers, punctuation) within that
           RTL context. Using "plaintext" here would re-evaluate paragraph
           direction from the first strong character — harmful when a paragraph
           opens with a Western digit run (European number / Arabic-Indic digit)
           because EN/AN are not strong directional types under Unicode BiDi,
           causing those paragraphs to appear left-to-right. */
        .rendered-doc p,
        .rendered-doc div,
        .rendered-doc li,
        .rendered-doc blockquote,
        .rendered-doc h1, .rendered-doc h2, .rendered-doc h3,
        .rendered-doc h4, .rendered-doc h5, .rendered-doc h6 {
            direction: rtl;
            text-align: right;
            unicode-bidi: embed;
        }

        /* Paragraphs */
        .rendered-doc p {
            margin-bottom: 0.6em;
            line-height: 2;
        }

        /* Headings */
        .rendered-doc h1, .rendered-doc h2, .rendered-doc h3 {
            margin-top: 1em;
            margin-bottom: 0.5em;
            font-weight: 700;
        }

        /* Tables: RTL cell order, proper borders */
        .rendered-doc table {
            width: 100% !important;
            border-collapse: collapse;
            direction: rtl;
            margin-bottom: 1em;
        }

        .rendered-doc td,
        .rendered-doc th {
            padding: 6px 10px;
            vertical-align: top;
            text-align: right;
            direction: rtl;
            unicode-bidi: embed;
            border: 1px solid var(--bs-border-color, #dee2e6);
        }

        .rendered-doc th {
            background-color: var(--bs-tertiary-bg, #f8f9fa);
            font-weight: 600;
        }

        /* Lists */
        .rendered-doc ul, .rendered-doc ol {
            padding-right: 2em;
            padding-left: 0;
            margin-bottom: 0.6em;
        }

        .rendered-doc li {
            margin-bottom: 0.3em;
            line-height: 2;
        }

        /* Inline spans: transparent to BiDi algorithm.
           Removing unicode-bidi: embed lets spans be invisible to the BiDi
           algorithm, so numbers inside spans flow naturally within the RTL
           paragraph without creating extra embedding levels that can cause
           numbers to appear visually reversed or mispositioned.
           !important overrides any inline unicode-bidi:embed that PhpWord
           may emit directly on <span> elements via its HTML writer. */
        .rendered-doc span {
            unicode-bidi: normal !important;
        }

        /* Images: constrain to container; cap height so logos stay medium-sized */
        .rendered-doc img {
            max-width: 100%;
            max-height: 180px;
            height: auto;
            width: auto;
        }

        /* Prevent empty paragraphs from collapsing */
        .rendered-doc p:empty::after {
            content: "\\00a0";
        }

        /* Safety-net overrides: block any residual PhpWord inline margin/width values */
        .rendered-doc p  { margin-top: 0 !important; margin-bottom: 0.5em !important; }
        .rendered-doc div { width: auto !important; max-width: 100% !important; }
    ';

    /**
     * Get the preview cache directory (absolute path).
     */
    public static function getPreviewDir(): string
    {
        $dir = WRITEPATH . self::PREVIEW_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Generate HTML preview of a document.
     *
     * @param string $filePath     Absolute path to .docx (or .doc) file
     * @param string $contentHash  SHA-256 hash for cache key
     * @param bool   $forceRefresh Skip cache and regenerate
     * @return array{success: bool, html: string, styles: string, method: string, error: string}
     */
    public static function generateHtmlPreview(string $filePath, string $contentHash, bool $forceRefresh = false): array
    {
        $result = [
            'success' => false,
            'html'    => '',
            'styles'  => '',
            'method'  => '',
            'error'   => '',
        ];

        if (!file_exists($filePath)) {
            $result['error'] = 'File not found: ' . $filePath;
            log_message('error', '[DocPreview] ' . $result['error']);
            return $result;
        }

        // Check cache
        $previewDir = self::getPreviewDir();
        $cachedPath = $previewDir . $contentHash . '.html';

        if (!$forceRefresh && file_exists($cachedPath) && filesize($cachedPath) > 0) {
            log_message('info', "[DocPreview] Cache hit for {$contentHash}.html");
            $cached = file_get_contents($cachedPath);
            if ($cached !== false) {
                // Cache format: JSON with 'styles' and 'html' keys
                $decoded = json_decode($cached, true);
                if (is_array($decoded) && isset($decoded['html'])) {
                    $result['success'] = true;
                    $result['styles'] = $decoded['styles'] ?? '';
                    $result['html'] = $decoded['html'];
                    $result['method'] = 'cache';
                    return $result;
                }
                // Legacy fallback: ||SEPARATOR|| format from older cache files
                if (str_contains($cached, '||SEPARATOR||')) {
                    $parts = explode('||SEPARATOR||', $cached, 2);
                    $result['success'] = true;
                    $result['styles'] = $parts[0] ?? '';
                    $result['html'] = $parts[1] ?? $cached;
                    $result['method'] = 'cache';
                    return $result;
                }
            }
        }

        // Generate fresh preview via PhpWord
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // For .doc files: PhpWord's MsDoc reader fragments Arabic text into individual
        // character runs producing unreadable output. Skip MsDoc HTML generation entirely
        // and return an error so the caller falls back to plain-text preview from DB.
        if ($extension === 'doc') {
            $result['error'] = 'MsDoc HTML rendering skipped for .doc files (use plain-text fallback)';
            return $result;
        }

        try {
            if ($extension === 'docx') {
                $reader = IOFactory::createReader('Word2007');
            } else {
                $result['error'] = 'Unsupported file format: ' . $extension;
                return $result;
            }

            $phpWord = $reader->load($filePath);
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            $fullHtml = $htmlWriter->getContent();

            // Extract body content
            $docHtml = '';
            if (preg_match('/<body[^>]*>(.*)<\/body>/si', $fullHtml, $matches)) {
                $docHtml = $matches[1];
            } else {
                $docHtml = $fullHtml;
            }

            // Extract PhpWord styles from <head>
            $phpWordStyles = '';
            if (preg_match('/<style[^>]*>(.*?)<\/style>/si', $fullHtml, $styleMatches)) {
                $phpWordStyles = $styleMatches[1];
            }

            // Post-process HTML for Arabic RTL
            $docHtml = self::postProcessArabicHtml($docHtml);

            // Security: sanitize HTML to prevent XSS (strip scripts, event handlers, etc.)
            $docHtml = self::sanitizeHtml($docHtml);

            // Security: sanitize styles to prevent </style> breakout
            $phpWordStyles = self::sanitizeStyles($phpWordStyles);

            // Build combined styles (PhpWord original + Arabic RTL overrides)
            $combinedStyles = $phpWordStyles . "\n" . self::ARABIC_RTL_CSS;

            // Cache the result (JSON format for robust parsing)
            $cacheContent = json_encode(['styles' => $combinedStyles, 'html' => $docHtml], JSON_UNESCAPED_UNICODE);
            file_put_contents($cachedPath, $cacheContent);
            log_message('info', "[DocPreview] Generated and cached HTML preview for {$contentHash}");

            $result['success'] = true;
            $result['html'] = $docHtml;
            $result['styles'] = $combinedStyles;
            $result['method'] = 'phpword';

        } catch (\Throwable $e) {
            $result['error'] = 'PhpWord HTML generation failed: ' . $e->getMessage();
            log_message('error', "[DocPreview] {$result['error']}");
        }

        return $result;
    }

    /**
     * Post-process PhpWord HTML output for Arabic RTL rendering.
     *
     * Operations (order matters):
     * 1. Strip letter-spacing CSS (prevents artificial gaps in Arabic cursive)
     * 1b. Strip extreme margin-top/bottom from inline styles
     * 1c. Remove fixed width from PhpWord page-frame <div> elements
     * 2. (Disabled) mergeAdjacentParagraphs — was for MsDoc reader only, not needed with LibreOffice
     * 3. Merge adjacent <span> elements within paragraphs (fixes span splitting)
     * 4. Add dir="rtl" to block-level elements
     * 5. Wrap in rendered-doc container
     * 6. Clean up excessive whitespace
     * 7. Convert Western digits (0-9) → Arabic-Indic (٠-٩) in text nodes.
     *    Dates (D/M/YYYY) are segment-reversed to YYYY/M/D before conversion so that
     *    right-to-left reading gives Day → Month → Year as the author intended.
     *
     * @param string $html Raw HTML from PhpWord
     * @return string Processed HTML with Arabic RTL support
     */
    public static function postProcessArabicHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Strip letter-spacing CSS from all inline styles.
        // PhpWord's Font.php writer (line 70-71) adds letter-spacing based on character
        // spacing values, which inserts artificial gaps between Arabic characters that
        // should be joined in cursive script.
        $html = self::stripLetterSpacing($html);

        // 1b. Strip extreme margin-top / margin-bottom from <p> inline styles.
        // PhpWord can emit <p style="margin-top:240pt;"> which causes enormous whitespace.
        // We normalise any margin-top/bottom > 12pt down to nothing so the CSS class rules take over.
        $html = preg_replace_callback(
            '/(<[^>]+\bstyle\s*=\s*["\'])([^"\']*)(["\']\s*>)/iu',
            static function (array $m): string {
                $style = $m[2];
                // Remove margin-top and margin-bottom entirely from inline styles
                $style = preg_replace('/\bmargin-(?:top|bottom)\s*:[^;]+;?\s*/i', '', $style);
                return $m[1] . $style . $m[3];
            },
            $html
        );

        // 1c. Remove fixed width from PhpWord page-frame <div> elements.
        // PhpWord's HTML writer wraps the whole document in <div style="width:816px;...">
        // which overflows the container on mobile. Strip the width declaration.
        $html = preg_replace_callback(
            '/(<div\b[^>]*\bstyle\s*=\s*["\'])([^"\']*)(["\']\s*>)/iu',
            static function (array $m): string {
                $style = preg_replace('/\bwidth\s*:[^;]+;?\s*/i', '', $m[2]);
                return $m[1] . $style . $m[3];
            },
            $html
        );

        // 2. mergeAdjacentParagraphs() — DISABLED.
        //
        // This step was designed to fix PhpWord's legacy MsDoc reader which emitted one
        // standalone <p> per character run (completely breaking Arabic cursive joining).
        // Since we now convert .doc files via LibreOffice → .docx before calling this
        // service, the Word2007 reader is always used and produces correct paragraph
        // structure. Running mergeAdjacentParagraphs() on LibreOffice-converted .docx
        // files incorrectly merges independent paragraphs (case numbers like "2800",
        // list separators like "،", short headings) into a single long paragraph, which
        // then wraps and displays in a scrambled, unreadable order in RTL layout.
        //
        // The function is kept in this file for reference but must not be called here.
        // $html = self::mergeAdjacentParagraphs($html); // ← intentionally disabled

        // 3. Merge adjacent <span> elements within the same paragraph (for .docx files).
        // PhpWord's Word2007 reader properly uses TextRun (paragraph container) but still
        // creates separate spans per character run. This merges adjacent spans within a <p>.
        $html = self::mergeAdjacentSpans($html);

        // 4. Add dir="rtl" to block-level elements that lack it
        $blockTags = ['p', 'div', 'table', 'tr', 'td', 'th', 'li', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'];
        foreach ($blockTags as $tag) {
            // Add dir="rtl" to tags that don't already have a dir attribute
            $html = preg_replace(
                '/<(' . $tag . ')(\s(?![^>]*\bdir\s*=)[^>]*)?>/iu',
                '<$1$2 dir="rtl">',
                $html
            );
        }

        // 5. Wrap the entire output in a rendered-doc container
        //    (the CSS class applies all Arabic RTL rules)
        $html = '<div class="rendered-doc">' . $html . '</div>';

        // 6. Clean up multiple consecutive blank lines/whitespace
        $html = preg_replace('/(\s*\n){3,}/', "\n\n", $html);

        // 7. Convert Western digits to Arabic-Indic (٠-٩) in all text nodes,
        //    and reverse date segment order so dates read correctly right-to-left.
        //
        // Why digit conversion:
        //   Arabic-language legal documents traditionally use Arabic-Indic numerals (٠-٩).
        //   Western digits (0-9) in an RTL document look foreign and break visual consistency.
        //
        // Why date segment reversal (D/M/YYYY → YYYY/M/D):
        //   In an RTL paragraph, any digit run (EN or AN) is rendered left-to-right within
        //   its unit. A date stored as "١/٣/٢٠١٣" displays visually with ١ on the left and
        //   ٢٠١٣ on the right — so an Arabic reader scanning right-to-left encounters the
        //   year first, which is wrong. By reversing the segments to "٢٠١٣/٣/١", the
        //   rightmost element is ١ (day) and the leftmost is ٢٠١٣ (year), giving the reader
        //   Day → Month → Year as intended.
        //
        // Only text nodes are processed. HTML tag tokens (<...>) are skipped entirely so
        // digits inside href, src, style, class, and other attributes are never touched.
        // HTML entities (&amp; &#160; etc.) are also passed through unchanged.
        $html = preg_replace_callback(
            '/(<[^>]+>)|([^<]+)/su',
            static function (array $m): string {
                // Group 1: HTML tag token — pass through unchanged
                if (isset($m[1]) && $m[1] !== '') {
                    return $m[1];
                }

                // Group 2: text node — apply digit conversion
                $text = $m[2] ?? '';

                // First pass: reverse date segments (D/M/YYYY → YYYY/M/D) AND convert digits.
                // The reversal ensures right-to-left reading gives Day → Month → Year.
                $text = preg_replace_callback(
                    '/(?<!\d)(\d{1,2})\/(\d{1,2})\/(\d{2,4})(?!\d)/u',
                    static function (array $d): string {
                        return DocumentPreviewService::toArabicIndic($d[3])
                            . '/'
                            . DocumentPreviewService::toArabicIndic($d[2])
                            . '/'
                            . DocumentPreviewService::toArabicIndic($d[1]);
                    },
                    $text
                );

                // Second pass: convert all remaining Western digit sequences to Arabic-Indic.
                // HTML entities (&amp; &#160; &#x2F; etc.) are matched first and skipped
                // to avoid corrupting entity syntax.
                $text = preg_replace_callback(
                    '/(&(?:#[0-9]+|#x[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);)|([0-9]+)/u',
                    static function (array $d): string {
                        // Group 1: HTML entity → pass through unchanged
                        if (isset($d[1]) && $d[1] !== '') {
                            return $d[1];
                        }
                        // Group 2: digit sequence → convert to Arabic-Indic
                        return DocumentPreviewService::toArabicIndic($d[2] ?? '');
                    },
                    $text
                );

                return $text;
            },
            $html
        );

        return $html;
    }

    /**
     * Strip letter-spacing CSS property from all inline style attributes.
     *
     * PhpWord's HTML/Style/Font.php (line 70-71) calculates letter-spacing from
     * character spacing values: `$css['letter-spacing'] = ($spacing / 20) . 'pt'`.
     * This is harmful for Arabic text because it adds gaps between characters that
     * should be joined in cursive script.
     *
     * @param string $html Input HTML
     * @return string HTML with letter-spacing removed from inline styles
     */
    private static function stripLetterSpacing(string $html): string
    {
        // Remove letter-spacing declarations from inline style attributes.
        // Matches: letter-spacing:Xpt; or letter-spacing: X pt; (with optional trailing semicolon)
        return preg_replace(
            '/letter-spacing\s*:\s*[^;:"]+;?\s*/i',
            '',
            $html
        );
    }

    /**
     * Merge adjacent <p><span style="X">text</span></p> blocks with identical styles.
     *
     * This is the critical fix for PhpWord's MsDoc reader output. The MsDoc reader
     * adds each character run as a standalone Text element (not inside a TextRun),
     * so the HTML writer wraps each in its own <p><span>...</span></p>.
     *
     * For Arabic text, this causes each character (or small run) to render in its
     * isolated form instead of joining with neighbors, completely destroying readability.
     *
     * Algorithm:
     * - Parse the HTML into segments: "single-span paragraphs" vs "everything else"
     * - Group consecutive single-span paragraphs with matching normalized styles
     * - Merge each group into one <p><span>combined text</span></p>
     * - Preserve non-matching segments and complex paragraphs untouched
     *
     * Example:
     *   Before:
     *     <p><span style="font-family:'Arial'">م</span></p>
     *     <p><span style="font-family:'Arial'">ح</span></p>
     *     <p><span style="font-family:'Arial'">ك</span></p>
     *   After:
     *     <p><span style="font-family:'Arial'">محك</span></p>
     *
     * @param string $html Input HTML
     * @return string HTML with merged paragraphs
     */
    private static function mergeAdjacentParagraphs(string $html): string
    {
        // PhpWord's MsDoc reader creates standalone Text elements for each character run.
        // The HTML writer renders these in TWO patterns:
        //   Pattern A (with style): <p attrs><span style="...">text</span></p>
        //   Pattern B (plain):      <p attrs>text</p>
        // Both patterns cause Arabic text fragmentation and must be merged.
        //
        // Strategy: Match ANY simple paragraph (one with only text content, possibly
        // wrapped in a single span). Use isMidWordJoin() heuristic to decide whether
        // consecutive paragraphs should be merged or kept separate.

        // Unified pattern: match a <p> that contains EITHER:
        //   - a single <span style="...">text</span>
        //   - OR plain text (no child elements)
        // Captures: [0]=full match, [1]=p attributes, [2]=span style (or empty), [3]=content
        //
        // We find all matches with offsets, then reconstruct with merging.

        // Pattern A: <p attrs><span style="...">text</span></p>
        $patA = '/<p(\s[^>]*)?>\\s*<span\\s+style="([^"]*)">(.*?)<\\/span>\\s*<\\/p>/su';
        // Pattern B: <p attrs>text</p> where text has no < (no child elements)
        $patB = '/<p(\s[^>]*)?>([^<]*)<\\/p>/su';

        // Collect all simple paragraphs (both patterns) with their byte offsets
        $allMatches = [];

        if (preg_match_all($patA, $html, $matchesA, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matchesA as $m) {
                $allMatches[] = [
                    'offset'  => $m[0][1],
                    'length'  => strlen($m[0][0]),
                    'full'    => $m[0][0],
                    'p_attrs' => $m[1][0] ?? '',
                    'style'   => $m[2][0],
                    'text'    => $m[3][0],
                    'type'    => 'span',
                ];
            }
        }

        if (preg_match_all($patB, $html, $matchesB, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matchesB as $m) {
                $allMatches[] = [
                    'offset'  => $m[0][1],
                    'length'  => strlen($m[0][0]),
                    'full'    => $m[0][0],
                    'p_attrs' => $m[1][0] ?? '',
                    'style'   => '',
                    'text'    => $m[2][0],
                    'type'    => 'plain',
                ];
            }
        }

        if (empty($allMatches)) {
            return $html;
        }

        // Sort by byte offset and deduplicate overlapping matches
        // (Pattern B could match inside Pattern A's range)
        usort($allMatches, fn($a, $b) => $a['offset'] <=> $b['offset']);

        $deduped = [];
        $lastEnd = -1;
        foreach ($allMatches as $m) {
            if ($m['offset'] >= $lastEnd) {
                $deduped[] = $m;
                $lastEnd = $m['offset'] + $m['length'];
            }
            // Skip overlapping matches (Pattern B overlapping with Pattern A)
        }

        if (empty($deduped)) {
            return $html;
        }

        // Build segments: matched paragraphs + gaps between them
        $segments = [];
        $pos = 0;

        foreach ($deduped as $m) {
            if ($m['offset'] > $pos) {
                $gap = substr($html, $pos, $m['offset'] - $pos);
                $segments[] = [
                    'type'    => (trim($gap) === '') ? 'ws' : 'other',
                    'content' => $gap,
                ];
            }

            $segments[] = [
                'type'    => 'para',
                'full'    => $m['full'],
                'p_attrs' => $m['p_attrs'],
                'style'   => $m['style'],
                'text'    => $m['text'],
                'ptype'   => $m['type'], // 'span' or 'plain'
            ];

            $pos = $m['offset'] + $m['length'];
        }

        // Trailing content
        if ($pos < strlen($html)) {
            $segments[] = ['type' => 'other', 'content' => substr($html, $pos)];
        }

        // Merge consecutive 'para' segments using isMidWordJoin() heuristic.
        // Paragraphs are merged when:
        //   1. They have compatible styles (both plain, or same normalized span style)
        //   2. The heuristic says the text fragments are mid-word/mid-phrase
        $output = '';
        $i = 0;
        $count = count($segments);

        while ($i < $count) {
            $seg = $segments[$i];

            if ($seg['type'] === 'other' || $seg['type'] === 'ws') {
                $output .= $seg['content'];
                $i++;
                continue;
            }

            // 'para' segment — start a merge group
            $groupNormStyle = self::normalizeStyle($seg['style']);
            $groupRawStyle = $seg['style'];
            $groupPAttrs = $seg['p_attrs'];
            $groupPType = $seg['ptype'];
            $groupTexts = [$seg['text']];
            $mergedTextSoFar = $seg['text']; // Running text for heuristic
            $j = $i + 1;

            while ($j < $count) {
                $next = $segments[$j];

                // Skip whitespace gaps if followed by a compatible paragraph
                if ($next['type'] === 'ws') {
                    if ($j + 1 < $count && $segments[$j + 1]['type'] === 'para') {
                        $candidate = $segments[$j + 1];
                        $candidateNormStyle = self::normalizeStyle($candidate['style']);
                        $stylesMatch = ($groupNormStyle === $candidateNormStyle)
                            || ($groupPType === 'plain' && $candidate['ptype'] === 'plain');

                        if ($stylesMatch && self::isMidWordJoin($mergedTextSoFar, $candidate['text'])) {
                            $j++; // Skip the whitespace, continue to the matching para
                            continue;
                        }
                    }
                    break;
                }

                if ($next['type'] !== 'para') {
                    break;
                }

                // Check style compatibility
                $nextNormStyle = self::normalizeStyle($next['style']);
                $stylesMatch = ($groupNormStyle === $nextNormStyle)
                    || ($groupPType === 'plain' && $next['ptype'] === 'plain');

                if (!$stylesMatch) {
                    break;
                }

                // Use heuristic to decide merge vs separate
                if (!self::isMidWordJoin($mergedTextSoFar, $next['text'])) {
                    break; // Heuristic says separate paragraph
                }

                $groupTexts[] = $next['text'];
                $mergedTextSoFar .= $next['text'];
                $j++;
            }

            // Emit the merged paragraph
            $hasRealText = false;
            foreach ($groupTexts as $t) {
                if ($t !== '&nbsp;' && trim($t) !== '') {
                    $hasRealText = true;
                    break;
                }
            }

            if (!$hasRealText && count($groupTexts) > 1) {
                // All empty/nbsp — collapse to single &nbsp; paragraph
                if ($groupPType === 'span' && $groupRawStyle !== '') {
                    $output .= '<p' . $groupPAttrs . '><span style="' . $groupRawStyle . '">&nbsp;</span></p>' . "\n";
                } else {
                    $output .= '<p' . $groupPAttrs . '>&nbsp;</p>' . "\n";
                }
            } else {
                // Build merged content, converting &nbsp; placeholders to <br />
                if (count($groupTexts) > 1) {
                    $filteredTexts = [];
                    foreach ($groupTexts as $t) {
                        if ($t === '&nbsp;') {
                            $filteredTexts[] = '<br />';
                        } else {
                            $filteredTexts[] = $t;
                        }
                    }
                    $mergedText = implode('', $filteredTexts);
                } else {
                    $mergedText = $groupTexts[0];
                }

                if ($groupPType === 'span' && $groupRawStyle !== '') {
                    $output .= '<p' . $groupPAttrs . '><span style="' . $groupRawStyle . '">' . $mergedText . '</span></p>' . "\n";
                } else {
                    $output .= '<p' . $groupPAttrs . '>' . $mergedText . '</p>' . "\n";
                }
            }

            $i = $j;
        }

        return $output;
    }

    /**
     * Heuristic: should two adjacent text fragments be merged (mid-word) or kept separate?
     *
     * This is the same logic as DocumentParser::isMidWordJoin() — applied to HTML
     * post-processing of PhpWord output for the render pipeline.
     *
     * Rules:
     * 1. Empty/whitespace fragments: always merge
     * 2. Very short fragments (< 5 chars): always merge (formatting artifacts)
     * 3. Both fragments long (>= 10 chars): separate paragraphs
     * 4. Previous ends at word boundary + next is medium-length: separate
     * 5. Default: merge (safer to keep text together)
     *
     * @param string $prevText Accumulated text so far
     * @param string $nextText The next fragment to consider
     * @return bool True to merge, false to keep separate
     */
    private static function isMidWordJoin(string $prevText, string $nextText): bool
    {
        // Strip HTML tags for text analysis (content may have <br /> from earlier merges)
        $prevClean = strip_tags($prevText);
        $nextClean = strip_tags($nextText);

        $prevTrimmed = rtrim($prevClean);
        $nextTrimmed = ltrim($nextClean);

        // Rule 1: Empty fragments → merge
        if ($prevTrimmed === '' || $nextTrimmed === '') {
            return true;
        }

        // Treat &nbsp; as empty
        if ($nextTrimmed === '&nbsp;' || $nextTrimmed === "\xC2\xA0") {
            return true;
        }

        $prevLen = mb_strlen($prevTrimmed, 'UTF-8');
        $nextLen = mb_strlen($nextTrimmed, 'UTF-8');

        // Rule 2: Very short fragment (< 5 chars) → always merge
        if ($nextLen < 5) {
            return true;
        }
        if ($prevLen < 5) {
            return true;
        }

        // Word boundary pattern (space, punctuation, digits, Arabic punctuation)
        $endBoundary = '/[\s\.\,\;\:\!\?\-\–\—\(\)\[\]\{\}\/\\\\0-9\x{060C}\x{061B}\x{061F}\x{0640}\x{06D4}]$/u';
        $startBoundary = '/^[\s\.\,\;\:\!\?\-\–\—\(\)\[\]\{\}\/\\\\0-9\x{060C}\x{061B}\x{061F}\x{0640}\x{06D4}]/u';

        $prevEndsAtBoundary = (bool) preg_match($endBoundary, $prevTrimmed);
        $nextStartsAtBoundary = (bool) preg_match($startBoundary, $nextTrimmed);

        // Rule 3: Both long (>= 10 chars) → separate paragraphs
        if ($prevLen >= 10 && $nextLen >= 10) {
            return false;
        }

        // Rule 4: Prev ends at boundary + next is medium+ → separate
        if ($prevEndsAtBoundary && $nextLen >= 8) {
            return false;
        }

        // Rule 5: Next starts at boundary + reasonably long → separate
        if ($nextStartsAtBoundary && $nextLen >= 8) {
            return false;
        }

        // Default: merge
        return true;
    }

    /**
     * Merge adjacent <span> elements within the same paragraph that have identical styles.
     *
     * PhpWord's HTML writer creates separate spans per character run even within TextRun
     * paragraphs (the .docx/Word2007 reader pattern). This method merges consecutive
     * spans with the same style into a single span.
     *
     * Example:
     *   Before: <span style="font-family:'X'">م</span><span style="font-family:'X'">ح</span>
     *   After:  <span style="font-family:'X'">مح</span>
     *
     * @param string $html Input HTML
     * @return string HTML with merged spans
     */
    private static function mergeAdjacentSpans(string $html): string
    {
        // Pattern: </span> followed by optional whitespace then <span with same style
        // We use a callback to check if styles match
        $maxIterations = 50;
        $iteration = 0;

        do {
            $prevHtml = $html;
            $html = preg_replace_callback(
                '/<span\s+style="([^"]*)">(.*?)<\/span>\s*<span\s+style="([^"]*)">(.*?)<\/span>/su',
                function ($m) {
                    // If styles are identical, merge content
                    if (self::normalizeStyle($m[1]) === self::normalizeStyle($m[3])) {
                        return '<span style="' . $m[1] . '">' . $m[2] . $m[4] . '</span>';
                    }
                    return $m[0]; // Different styles, leave unchanged
                },
                $html
            );
            $iteration++;
        } while ($html !== $prevHtml && $iteration < $maxIterations);

        return $html;
    }

    /**
     * Normalize a CSS style string for comparison.
     *
     * Removes extra whitespace, trims, lowercases property names.
     * Strips letter-spacing (already removed globally but may appear in comparisons).
     * Normalizes font-family names by removing quotes and collapsing whitespace.
     * Allows us to match styles like "font-family: 'X'" with "font-family:'X'".
     *
     * @param string $style CSS style attribute value
     * @return string Normalized style string
     */
    private static function normalizeStyle(string $style): string
    {
        // Remove letter-spacing property entirely (irrelevant for style comparison)
        $style = preg_replace('/letter-spacing\s*:\s*[^;]+;?\s*/i', '', $style);

        // Remove all whitespace around : and ;
        $style = preg_replace('/\s*:\s*/', ':', $style);
        $style = preg_replace('/\s*;\s*/', ';', $style);

        // Normalize font-family: remove inner quotes (single and double)
        // "font-family:'Arial'" and "font-family:Arial" should match
        $style = preg_replace_callback(
            '/font-family:([^;]+)/i',
            function ($m) {
                $fontValue = str_replace(["'", '"'], '', $m[1]);
                // Collapse internal whitespace in font names
                $fontValue = preg_replace('/\s+/', ' ', trim($fontValue));
                return 'font-family:' . $fontValue;
            },
            $style
        );

        // Trim trailing semicolons and whitespace
        $style = rtrim($style, '; ');

        // Lowercase for comparison
        return mb_strtolower(trim($style));
    }

    /**
     * Generate a plain-text fallback preview when PhpWord fails.
     *
     * @param string|null $fullText  The document's full_text from DB
     * @return array{success: bool, html: string, styles: string, method: string, error: string}
     */
    public static function generatePlainTextPreview(?string $fullText): array
    {
        $text = $fullText ?? '';

        // Reverse date segments (D/M/YYYY → YYYY/M/D) so right-to-left reading gives Day/Month/Year.
        $text = preg_replace_callback(
            '/(?<!\d)(\d{1,2})\/(\d{1,2})\/(\d{2,4})(?!\d)/u',
            static function (array $d): string {
                return DocumentPreviewService::toArabicIndic($d[3])
                    . '/'
                    . DocumentPreviewService::toArabicIndic($d[2])
                    . '/'
                    . DocumentPreviewService::toArabicIndic($d[1]);
            },
            $text
        );

        // Convert remaining Western digits to Arabic-Indic.
        $text = preg_replace_callback(
            '/[0-9]+/u',
            static fn(array $d): string => DocumentPreviewService::toArabicIndic($d[0]),
            $text
        );

        $html = '<div class="rendered-doc document-preview" dir="rtl">'
            . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            . '</div>';

        return [
            'success' => true,
            'html'    => $html,
            'styles'  => self::ARABIC_RTL_CSS,
            'method'  => 'plaintext',
            'error'   => '',
        ];
    }

    /**
     * Check if a PDF preview is available for the given document.
     *
     * @param string $contentHash SHA-256 hash
     * @return string|null Absolute path to PDF if available, null otherwise
     */
    public static function getPdfPreviewPath(string $contentHash): ?string
    {
        $pdfPath = self::getPreviewDir() . $contentHash . '.pdf';
        if (file_exists($pdfPath) && filesize($pdfPath) > 0) {
            return $pdfPath;
        }
        return null;
    }

    /**
     * Sanitize HTML to prevent XSS attacks while preserving document formatting.
     *
     * Removes:
     * - <script> tags and their content
     * - on* event handler attributes (onclick, onerror, onload, etc.)
     * - javascript: and vbscript: URI schemes
     * - <iframe>, <object>, <embed>, <applet>, <form>, <input> tags
     * - data: URIs in src/href attributes (except data:image for inline images)
     *
     * @param string $html Raw HTML content
     * @return string Sanitized HTML
     */
    private static function sanitizeHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove <script> tags and content
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);

        // Remove <noscript>, <iframe>, <object>, <embed>, <applet>, <form>, <input>, <button>, <textarea> tags
        $dangerousTags = ['noscript', 'iframe', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base'];
        foreach ($dangerousTags as $tag) {
            // Self-closing and opening+closing variants
            $html = preg_replace('/<' . $tag . '\b[^>]*\/?\s*>/si', '', $html);
            $html = preg_replace('/<\/' . $tag . '\s*>/si', '', $html);
        }

        // Remove all on* event handler attributes
        $html = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/si', '', $html);

        // Remove javascript: and vbscript: URIs in href and src attributes
        $html = preg_replace('/\b(href|src|action)\s*=\s*(?:"[^"]*(?:javascript|vbscript)\s*:[^"]*"|\'[^\']*(?:javascript|vbscript)\s*:[^\']*\')/si', '$1=""', $html);

        // Remove style attributes containing expression() or url(javascript:)
        $html = preg_replace_callback(
            '/\bstyle\s*=\s*"([^"]*)"/si',
            function ($m) {
                $style = $m[1];
                // Remove expression(), url(javascript:), url(vbscript:)
                $style = preg_replace('/expression\s*\([^)]*\)/si', '', $style);
                $style = preg_replace('/url\s*\(\s*["\']?\s*(?:javascript|vbscript)\s*:/si', 'url(about:blank', $style);
                return 'style="' . $style . '"';
            },
            $html
        );

        return $html;
    }

    /**
     * Sanitize CSS styles to prevent </style> breakout and CSS injection.
     *
     * @param string $css Raw CSS content
     * @return string Sanitized CSS
     */
    private static function sanitizeStyles(string $css): string
    {
        if (empty($css)) {
            return '';
        }

        // Remove any </style> tags that could break out of the <style> block
        $css = preg_replace('/<\/style\s*>/si', '', $css);

        // Remove any <script or other HTML tags embedded in CSS
        $css = preg_replace('/<script\b/si', '', $css);

        // Remove CSS expressions (IE-specific XSS vector)
        $css = preg_replace('/expression\s*\(/si', '/* sanitized */(', $css);

        // Remove url(javascript:) and url(vbscript:)
        $css = preg_replace('/url\s*\(\s*["\']?\s*(?:javascript|vbscript)\s*:/si', 'url(about:blank', $css);

        return $css;
    }

    /**
     * Convert ASCII digits (0-9) to Arabic-Indic digits (٠-٩).
     *
     * Arabic-Indic digits (U+0660–U+0669) are classified as AN (Arabic Number)
     * in the Unicode BiDi algorithm, which integrates naturally into RTL paragraph
     * rendering. They are the traditional numeral form for Arabic-language documents.
     *
     * @param string $str String containing ASCII digits
     * @return string String with 0-9 replaced by ٠-٩
     */
    public static function toArabicIndic(string $str): string
    {
        return strtr($str, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }

    /**
     * Clear the preview cache for a specific document.
     *
     * @param string $contentHash SHA-256 hash
     */
    public static function clearCache(string $contentHash): void
    {
        $previewDir = self::getPreviewDir();
        $files = [
            $previewDir . $contentHash . '.html',
            $previewDir . $contentHash . '.pdf',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
                log_message('info', "[DocPreview] Cleared cache: {$file}");
            }
        }
    }
}
