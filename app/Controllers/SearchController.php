<?php

namespace App\Controllers;

use App\Models\LegalDocumentModel;
use App\Models\SearchScopeModel;
use App\Models\AuditLogModel;
use App\Libraries\ArabicTextNormalizer;
use App\Libraries\ArabicSynonymDictionary;

class SearchController extends BaseController
{
    protected LegalDocumentModel $docModel;
    protected AuditLogModel $auditModel;

    public function __construct()
    {
        $this->docModel   = new LegalDocumentModel();
        $this->auditModel = new AuditLogModel();
    }

    /**
     * Search page with form.
     */
    public function index()
    {
        // Load scope tree for sidebar
        $scopeModel = new SearchScopeModel();
        $scopeTree  = $scopeModel->getTree(true);

        return view('search/index', $this->viewData([
            'title'     => lang('App.search'),
            'scopeTree' => $scopeTree,
        ]));
    }

    /**
     * Execute search and return results (AJAX or full page).
     *
     * Performance optimizations:
     * - B3: word_count/char_count read from DB columns (no runtime regex)
     * - B5: 60-second cache for identical queries
     * - Model handles B1 (no LONGTEXT), B4 (single count), B7 (3-col index)
     */
    public function results()
    {
        $query      = trim($this->request->getGet('q') ?? '');
        $mode       = $this->request->getGet('mode') ?? 'fulltext';
        $docType    = $this->request->getGet('document_type');
        $courtLevel = $this->request->getGet('court_level');
        $dateFrom   = $this->request->getGet('date_from');
        $dateTo     = $this->request->getGet('date_to');
        $page       = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage    = min(100, max(10, (int) ($this->request->getGet('per_page') ?? 50)));
        $knownTotal = max(0, (int) ($this->request->getGet('known_total') ?? 0));  // skip count on page>1
        $scopeIds   = $this->request->getGet('scope_ids');  // comma-separated or array
        $minSize    = $this->request->getGet('min_size');     // in KB (convert to bytes)
        $maxSize    = $this->request->getGet('max_size');     // in KB (convert to bytes)
        $docIds     = $this->request->getGet('doc_ids');      // comma-separated IDs (re-search within results)

        // document_types[] — checkbox array from DocFetcher sidebar
        $docTypes = $this->request->getGet('document_types');
        if (!empty($docTypes) && is_array($docTypes)) {
            $docType = $docTypes;
        }

        if (empty($query)) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 0]);
            }
            return redirect()->to(site_url('search'))->with('error', lang('App.search_placeholder'));
        }

        // Normalize the search query
        $normalizedQuery = ArabicTextNormalizer::normalize($query);

        // Build the MySQL FULLTEXT query based on mode
        $ftsQuery = $this->buildFtsQuery($normalizedQuery, $mode);

        // Build filters array
        $filters = array_filter([
            'document_type' => $docType,
            'court_level'   => $courtLevel,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
        ]);

        // Parse scope IDs
        if (!empty($scopeIds)) {
            if (is_string($scopeIds)) {
                $scopeIds = array_filter(array_map('intval', explode(',', $scopeIds)));
            }
            if (!empty($scopeIds)) {
                // Expand to include descendant scopes
                $scopeModel = new SearchScopeModel();
                $filters['scope_ids'] = $scopeModel->expandScopeIds($scopeIds);
            }
        }

        // Filesize: convert KB to bytes
        if (!empty($minSize) && is_numeric($minSize)) {
            $filters['min_size'] = (int) $minSize * 1024;
        }
        if (!empty($maxSize) && is_numeric($maxSize)) {
            $filters['max_size'] = (int) $maxSize * 1024;
        }

        // Document IDs constraint (re-search within existing results)
        if (!empty($docIds)) {
            if (is_string($docIds)) {
                $docIds = array_filter(array_map('intval', explode(',', $docIds)));
            }
            if (!empty($docIds)) {
                $filters['doc_ids'] = $docIds;
            }
        }

        // B5: Cache key for identical queries (60 seconds)
        $cache = \Config\Services::cache();
        $cacheKey = 'search_' . md5(serialize([
            'fts' => $ftsQuery, 'filters' => $filters,
            'page' => $page, 'perPage' => $perPage,
        ]));

        $results = $cache->get($cacheKey);
        if ($results === null) {
            $results = $this->docModel->fullTextSearch($ftsQuery, $filters, $page, $perPage, $knownTotal, $query);
            $cache->save($cacheKey, $results, 60);
        }

        // Log the search (outside cache — always log)
        $this->auditModel->log('search_performed', "Search: \"{$query}\" mode={$mode} results={$results['total']}");

        if ($this->request->isAJAX()) {
            // B3: word_count and char_count are already in DB columns —
            // no runtime preg_split needed. Also, full_text is not selected
            // by the model (excluded in LIST_COLUMNS), so no need to unset.
            // Generate snippet from keywords/summary since full_text is not available.
            foreach ($results['items'] as &$item) {
                $item['snippet'] = $item['summary'] ?? mb_substr($item['keywords'] ?? '', 0, 200);
            }
            return $this->jsonResponse($results);
        }

        return view('search/index', $this->viewData([
            'title'     => lang('App.search_results'),
            'query'     => $query,
            'mode'      => $mode,
            'filters'   => $filters,
            'results'   => $results,
            'scopeTree' => (new SearchScopeModel())->getTree(true),
        ]));
    }

    /**
     * Autocomplete / search suggestions (AJAX).
     */
    public function suggest()
    {
        $term = trim($this->request->getGet('q') ?? '');
        if (mb_strlen($term) < 2) {
            return $this->jsonResponse(['suggestions' => []]);
        }

        $normalized = ArabicTextNormalizer::normalizeTerm($term);

        // Search document titles AND file names matching the prefix
        $docs = $this->docModel->builder()
            ->select('id, title, file_name')
            ->groupStart()
                ->like('title', $normalized)
                ->orLike('file_name', $term)
            ->groupEnd()
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $suggestions = array_map(fn($d) => [
            'id'       => $d['id'],
            'title'    => $d['title'],
            'file_name'=> $d['file_name'],
        ], $docs);

        // Also add synonym hints
        $synonyms = ArabicSynonymDictionary::getSynonyms($normalized);
        if (!empty($synonyms)) {
            $suggestions[] = [
                'type'     => 'synonyms',
                'original' => $term,
                'synonyms' => array_slice($synonyms, 0, 5),
            ];
        }

        return $this->jsonResponse(['suggestions' => $suggestions]);
    }

    /**
     * Proxy Google Drive search and return results as JSON (AJAX).
     * Uses service-account or API-key from .env:
     *   cloud.googlePickerApiKey
     *   cloud.googleDriveServiceAccount  (optional JSON path)
     *
     * Returns items: [{id, name, mimeType, size, webViewLink, modifiedTime}]
     */
    public function driveResults()
    {
        $query  = trim($this->request->getGet('q') ?? '');
        $apiKey = env('cloud.googlePickerApiKey', '');

        if (empty($apiKey)) {
            return $this->jsonResponse(['not_configured' => true, 'items' => []]);
        }

        if (empty($query)) {
            return $this->jsonResponse(['items' => []]);
        }

        // Google Drive Files: list API v3
        $escapedQ = addslashes($query);
        $driveQ   = "fullText contains '{$escapedQ}' and trashed = false";
        $fields   = 'files(id,name,mimeType,size,webViewLink,modifiedTime,iconLink)';
        $url = 'https://www.googleapis.com/drive/v3/files'
            . '?q=' . urlencode($driveQ)
            . '&fields=' . urlencode($fields)
            . '&pageSize=20'
            . '&key=' . urlencode($apiKey);

        $client = \Config\Services::curlrequest();
        try {
            $response = $client->get($url, [
                'timeout' => 10,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['error'])) {
                log_message('warning', 'Drive API error: ' . ($body['error']['message'] ?? 'unknown'));
                return $this->jsonResponse(['items' => [], 'error' => $body['error']['message'] ?? 'API error']);
            }

            $items = [];
            foreach ($body['files'] ?? [] as $file) {
                $items[] = [
                    'id'           => $file['id'] ?? '',
                    'name'         => $file['name'] ?? '',
                    'mimeType'     => $file['mimeType'] ?? '',
                    'size'         => (int) ($file['size'] ?? 0),
                    'webViewLink'  => $file['webViewLink'] ?? '',
                    'modifiedTime' => $file['modifiedTime'] ?? '',
                    'iconLink'     => $file['iconLink'] ?? '',
                ];
            }

            return $this->jsonResponse(['items' => $items]);
        } catch (\Throwable $e) {
            log_message('error', 'Drive search exception: ' . $e->getMessage());
            return $this->jsonResponse(['items' => [], 'error' => 'Request failed'], 500);
        }
    }

    /**
     * Build a FULLTEXT MATCH query string based on search mode.
     *
     * MySQL FULLTEXT BOOLEAN MODE operators:
     * - Word: matches if present
     * - "phrase": exact phrase match
     * - +word: must be present
     * - -word: must not be present
     * - word*: prefix match
     *
     * @param string $normalizedQuery Normalized search text
     * @param string $mode            Search mode: fulltext|exact|fuzzy|synonym|boolean
     * @return string BOOLEAN MODE query string
     */
    private function buildFtsQuery(string $normalizedQuery, string $mode): string
    {
        switch ($mode) {
            case 'exact':
                // Exact phrase: wrap in double quotes
                $cleaned = str_replace('"', '', $normalizedQuery);
                return '"' . $cleaned . '"';

            case 'synonym':
                // Expand each word with its synonyms via OR
                return $this->expandWithSynonyms($normalizedQuery);

            case 'fuzzy':
                // Use prefix matching (word*) for each term
                $words = preg_split('/\s+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY);
                $parts = [];
                foreach ($words as $word) {
                    $escaped = str_replace('"', '""', $word);
                    $parts[] = '"' . $escaped . '"*';
                }
                return implode(' ', $parts);

            case 'boolean':
                // Pass through (user provides their own operators)
                return $normalizedQuery;

            case 'fulltext':
            default:
                // Parse quoted phrases and individual words.
                // Quoted phrases ("...") become mandatory exact phrase matches.
                // Unquoted words get synonym expansion + mandatory match.
                $tokens = $this->parseQueryTokens($normalizedQuery);
                $parts = [];
                foreach ($tokens as $token) {
                    if ($token['type'] === 'phrase') {
                        // Exact phrase: mandatory match
                        $escaped = str_replace('"', '""', $token['value']);
                        $parts[] = '+"' . $escaped . '"';
                    } else {
                        // Single word with optional synonym expansion
                        $word = $token['value'];
                        $synonyms = ArabicSynonymDictionary::getSynonyms($word);
                        if (!empty($synonyms)) {
                            $group = array_merge([$word], $synonyms);
                            $orParts = array_map(fn($t) => '"' . str_replace('"', '""', $t) . '"', $group);
                            $parts[] = '(' . implode(' ', $orParts) . ')';
                        } else {
                            $parts[] = '+' . '"' . str_replace('"', '""', $word) . '"';
                        }
                    }
                }
                return implode(' ', $parts);
        }
    }

    /**
     * Expand a multi-word query with synonym alternatives.
     */
    private function expandWithSynonyms(string $normalizedQuery): string
    {
        $words = preg_split('/\s+/u', $normalizedQuery, -1, PREG_SPLIT_NO_EMPTY);
        $allTerms = [];

        foreach ($words as $word) {
            $allTerms[] = '"' . str_replace('"', '""', $word) . '"';
            $synonyms = ArabicSynonymDictionary::getSynonyms($word);
            foreach ($synonyms as $syn) {
                $allTerms[] = '"' . str_replace('"', '""', $syn) . '"';
            }
        }

        return implode(' ', $allTerms);
    }

    /**
     * Parse a query string into tokens: quoted phrases and individual words.
     * Example: 'حكم "محكمة التمييز" قانون' =>
     *   [['type'=>'word','value'=>'حكم'], ['type'=>'phrase','value'=>'محكمة التمييز'], ['type'=>'word','value'=>'قانون']]
     *
     * @return array<array{type: string, value: string}>
     */
    private function parseQueryTokens(string $query): array
    {
        $tokens = [];
        $len = mb_strlen($query);
        $i = 0;

        while ($i < $len) {
            $char = mb_substr($query, $i, 1);

            // Skip whitespace
            if (trim($char) === '') {
                $i++;
                continue;
            }

            // Quoted phrase
            if ($char === '"' || $char === "\u{201C}" || $char === "\u{201D}") {
                $i++; // skip opening quote
                $phrase = '';
                while ($i < $len) {
                    $c = mb_substr($query, $i, 1);
                    if ($c === '"' || $c === "\u{201C}" || $c === "\u{201D}") {
                        $i++; // skip closing quote
                        break;
                    }
                    $phrase .= $c;
                    $i++;
                }
                $phrase = trim($phrase);
                if ($phrase !== '') {
                    $tokens[] = ['type' => 'phrase', 'value' => $phrase];
                }
            } else {
                // Unquoted word: read until whitespace or quote
                $word = '';
                while ($i < $len) {
                    $c = mb_substr($query, $i, 1);
                    if (trim($c) === '' || $c === '"' || $c === "\u{201C}" || $c === "\u{201D}") {
                        break;
                    }
                    $word .= $c;
                    $i++;
                }
                $word = trim($word);
                if ($word !== '') {
                    $tokens[] = ['type' => 'word', 'value' => $word];
                }
            }
        }

        return $tokens;
    }
}
