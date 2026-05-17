<?php

namespace App\Controllers;

use App\Models\LegalDocumentModel;
use App\Models\AuditLogModel;
use App\Models\SearchScopeModel;
use App\Models\UploadQueueModel;
use App\Libraries\DocumentParser;
use App\Libraries\ArabicTextNormalizer;
use App\Services\DocumentConversionService;
use App\Services\DocumentPreviewService;

class DocumentController extends BaseController
{
    protected LegalDocumentModel $docModel;
    protected AuditLogModel $auditModel;

    public function __construct()
    {
        $this->docModel   = new LegalDocumentModel();
        $this->auditModel = new AuditLogModel();
    }

    /**
     * Document list page.
     */
    public function index()
    {
        return view('documents/index', $this->viewData([
            'title' => lang('App.document_management'),
        ]));
    }

    /**
     * AJAX data endpoint for document table.
     *
     * B6 fix: explicit SELECT excludes full_text and normalized_text LONGTEXT columns.
     */
    public function data()
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 15);
        $search  = $this->request->getGet('search');
        $type    = $this->request->getGet('document_type');
        $court   = $this->request->getGet('court_level');
        $scopeId = $this->request->getGet('scope_id'); // null = all, 'none' = unscoped, int = specific scope

        $isAdmin   = $this->can('scopes.manage');
        $userId    = (int) session()->get('user_id');
        $roleId    = (int) ($this->currentUser['role_id'] ?? 0);
        $scopeModel = new SearchScopeModel();

        $builder = $this->docModel->builder();

        // B6: Explicit column list — no LONGTEXT
        $builder->select('id, scope_id, title, document_type, court_level, case_number, '
            . 'document_date, hijri_year, file_path, file_name, file_size, file_extension, '
            . 'page_count, word_count, char_count, content_hash, summary, keywords, '
            . 'is_indexed, indexed_by, created_at, updated_at');

        if ($search) {
            $builder->groupStart()
                ->like('title', $search)
                ->orLike('case_number', $search)
                ->orLike('keywords', $search)
                ->groupEnd();
        }
        if ($type) {
            $builder->where('document_type', $type);
        }
        if ($court) {
            $builder->where('court_level', $court);
        }

        if ($scopeId === 'none') {
            $builder->where('scope_id IS NULL', null, false);
        } elseif ($scopeId !== null && $scopeId !== '') {
            // Security: verify user may access the requested scope
            if (!$scopeModel->userCanAccessScope((int) $scopeId, $userId, $roleId, $isAdmin)) {
                return $this->jsonResponse(['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0]);
            }
            // Expand to include descendant scopes
            $allScopeIds = $scopeModel->expandScopeIds([(int) $scopeId]);
            // Further filter expanded IDs to only those the user can access
            if (!$isAdmin) {
                $allScopeIds = array_filter($allScopeIds, fn($sid) => $scopeModel->userCanAccessScope($sid, $userId, $roleId, false));
                $allScopeIds = array_values($allScopeIds);
            }
            $builder->whereIn('scope_id', $allScopeIds);
        } else {
            // No specific scope requested — restrict to accessible scopes only
            if (!$isAdmin) {
                // Get all visible scope IDs for this user
                $visibleTree = $scopeModel->getTreeForUser($userId, $roleId, false);
                $visibleIds  = $this->collectScopeIds($visibleTree);
                // Include unscoped docs + docs in visible scopes
                if (!empty($visibleIds)) {
                    $builder->groupStart()
                        ->where('scope_id IS NULL', null, false)
                        ->orWhereIn('scope_id', $visibleIds)
                    ->groupEnd();
                } else {
                    $builder->where('scope_id IS NULL', null, false);
                }
            }
        }

        $total = $builder->countAllResults(false);
        $docs  = $builder->orderBy('created_at', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->jsonResponse([
            'items'       => $docs,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /** Recursively collect all scope IDs from a tree. */
    private function collectScopeIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            $ids[] = (int) $node['id'];
            if (!empty($node['children'])) {
                $ids = array_merge($ids, $this->collectScopeIds($node['children']));
            }
        }
        return $ids;
    }

    /**
     * Upload form page.
     */
    public function upload()
    {
        // Pre-select scope if coming from documents/index with ?scope_id=X
        $scopeId   = (int) $this->request->getGet('scope_id') ?: null;
        $scopeName = null;
        if ($scopeId) {
            $scopeModel = new SearchScopeModel();
            $scope      = $scopeModel->find($scopeId);

            if (!$scope) {
                $scopeId = null; // invalid id — ignore
            } else {
                // Security: verify user may access this scope
                $isAdmin = $this->can('scopes.manage');
                $userId  = (int) session()->get('user_id');
                $roleId  = (int) ($this->currentUser['role_id'] ?? 0);

                if (!$scopeModel->userCanAccessScope($scopeId, $userId, $roleId, $isAdmin)) {
                    $scopeId = null; // no access — silently drop pre-selection
                } else {
                    $scopeName = $scope['name'];
                }
            }
        }

        return view('documents/upload', $this->viewData([
            'title'               => lang('App.upload_documents'),
            'preselectedScopeId'  => $scopeId,
            'preselectedScopeName'=> $scopeName,
            // Cloud storage provider keys (empty = not configured)
            'googlePickerApiKey'  => env('cloud.googlePickerApiKey', ''),
            'googlePickerClientId' => env('cloud.googlePickerClientId', ''),
            'googlePickerAppId'   => env('cloud.googlePickerAppId', ''),
            'dropboxAppKey'       => env('cloud.dropboxAppKey', ''),
            'onedriveClientId'    => env('cloud.onedriveClientId', ''),
        ]));
    }

    /**
     * Compute word and character counts from text.
     *
     * @param string|null $text Full text content
     * @return array{word_count: int, char_count: int}
     */
    private function computeTextCounts(?string $text): array
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
     * Handle file upload, parse, normalize, store.
     *
     * B3 fix: pre-computes word_count and char_count during upload.
     * Accepts optional scope_id for document-to-scope assignment.
     */
    public function doUpload()
    {
        $files = $this->request->getFiles();

        if (empty($files['documents'])) {
            return redirect()->back()->with('error', lang('App.error_occurred'));
        }

        $uploadedFiles = $files['documents'];
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        $uploadPath = WRITEPATH . 'uploads/documents/original/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        $successCount = 0;
        $duplicateCount = 0;
        $errorCount = 0;
        $errors = [];

        // Optional metadata from POST
        $documentType = $this->request->getPost('document_type') ?: null;
        $courtLevel   = $this->request->getPost('court_level') ?: null;
        $caseNumber   = $this->request->getPost('case_number') ?: null;
        $documentDate = $this->request->getPost('document_date') ?: null;
        $scopeId      = $this->request->getPost('scope_id') ?: null;
        if ($scopeId !== null) {
            $scopeId = (int) $scopeId ?: null;
        }

        foreach ($uploadedFiles as $file) {
            if (!$file->isValid()) {
                $errorCount++;
                $errors[] = $file->getName() . ': ' . $file->getErrorString();
                continue;
            }

            // Use getClientExtension() (original filename), NOT getExtension()
            // which MIME-guesses and can return 'zip' for .docx
            $extension = strtolower($file->getClientExtension());
            if (!DocumentParser::isSupported($extension)) {
                $errorCount++;
                $errors[] = $file->getName() . ': Unsupported format';
                continue;
            }

            // Move to temp location for processing
            $newName = $file->getRandomName();
            $file->move($uploadPath, $newName);
            $fullPath = $uploadPath . $newName;

            // Check for duplicates via content hash
            $hash = DocumentParser::computeHash($fullPath);
            if ($this->docModel->existsByHash($hash)) {
                @unlink($fullPath);
                $duplicateCount++;
                continue;
            }

            // Parse document
            $parseResult = DocumentParser::parse($fullPath);
            if (!$parseResult['success']) {
                @unlink($fullPath);
                $errorCount++;
                $errors[] = $file->getName() . ': ' . $parseResult['error'];
                continue;
            }

            // Normalize text for search
            $normalizedText = ArabicTextNormalizer::normalize($parseResult['full_text']);

            // B3: Pre-compute word and character counts
            $counts = $this->computeTextCounts($parseResult['full_text']);

            // Insert document record
            $insertData = [
                'title'           => $parseResult['title'] ?: pathinfo($file->getName(), PATHINFO_FILENAME),
                'document_type'   => $documentType,
                'court_level'     => $courtLevel,
                'case_number'     => $caseNumber,
                'document_date'   => $documentDate,
                'file_path'       => 'uploads/documents/original/' . $newName,
                'file_name'       => $file->getName(),
                'file_size'       => filesize($fullPath),
                'file_extension'  => $extension,
                'page_count'      => $parseResult['page_count'],
                'word_count'      => $counts['word_count'],
                'char_count'      => $counts['char_count'],
                'full_text'       => $parseResult['full_text'],
                'normalized_text' => $normalizedText,
                'content_hash'    => $hash,
                'is_indexed'      => 1,
                'indexed_by'      => session()->get('user_id'),
            ];

            if ($scopeId !== null) {
                $insertData['scope_id'] = $scopeId;
            }

            try {
                $docId = $this->docModel->insert($insertData);
            } catch (\Throwable $e) {
                @unlink($fullPath);
                log_message('error', 'Upload DB insert failed: ' . $e->getMessage());
                $errorCount++;
                $errors[] = $file->getName() . ': Database error';
                continue;
            }

            $this->auditModel->log('document_indexed', "Indexed: {$file->getName()}", 'document', $docId);
            $successCount++;
        }

        $message = lang('App.indexing_complete') . " ({$successCount} " . lang('App.documents') . ")";
        if ($duplicateCount > 0) {
            $message .= " | {$duplicateCount} " . lang('App.document_exists');
        }
        if ($errorCount > 0) {
            $message .= " | {$errorCount} errors";
        }

        $flashKey = ($successCount > 0) ? 'success' : 'error';

        return redirect()->to(site_url('documents'))->with($flashKey, $message);
    }

    /**
     * AJAX single-file upload endpoint.
     *
     * Saves the file to disk and inserts a 'pending' row in upload_queue.
     * Returns immediately with status='queued' — heavy parsing happens in the
     * background via QueueProcessorService triggered after this response.
     */
    public function doUploadSingle()
    {
        // Ensure this is an AJAX request
        if (!$this->request->isAJAX()) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Invalid request'], 400);
        }

        $file     = $this->request->getFile('document');
        $csrfHash = csrf_hash();

        if (!$file || !$file->isValid()) {
            $errorMsg = $file ? $file->getErrorString() : lang('App.error_occurred');
            return $this->jsonResponse([
                'status'    => 'error',
                'message'   => $errorMsg,
                'csrf_hash' => $csrfHash,
            ]);
        }

        $extension    = strtolower($file->getClientExtension());
        $originalName = $file->getClientName();

        // Reject Word temporary lock files (~$filename.doc / ~$filename.docx)
        // These are 0-byte or near-empty files created by Word while the real
        // document is open — they are not valid documents.
        if (str_starts_with($originalName, '~$')) {
            return $this->jsonResponse([
                'status'    => 'skip',
                'message'   => lang('App.upload_unsupported_format'),
                'file_name' => $originalName,
                'csrf_hash' => $csrfHash,
            ]);
        }

        if (!DocumentParser::isSupported($extension)) {
            return $this->jsonResponse([
                'status'    => 'error',
                'message'   => lang('App.upload_unsupported_format'),
                'file_name' => $originalName,
                'csrf_hash' => $csrfHash,
            ]);
        }

        // Early duplicate check on temp file (SHA-256, no parsing)
        $tempPath = $file->getTempName();
        $hash     = DocumentParser::computeHash($tempPath);

        if ($this->docModel->existsByHash($hash)) {
            return $this->jsonResponse([
                'status'    => 'duplicate',
                'message'   => lang('App.document_exists'),
                'file_name' => $originalName,
                'csrf_hash' => $csrfHash,
            ]);
        }

        // Move file to permanent storage — NO parsing here
        $uploadPath = WRITEPATH . 'uploads/documents/original/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        $newName      = $file->getRandomName();
        $file->move($uploadPath, $newName);
        $fullPath     = $uploadPath . $newName;
        $relativePath = 'uploads/documents/original/' . $newName;

        // Read optional metadata
        $documentType = $this->request->getPost('document_type') ?: null;
        $courtLevel   = $this->request->getPost('court_level') ?: null;
        $caseNumber   = $this->request->getPost('case_number') ?: null;
        $documentDate = $this->request->getPost('document_date') ?: null;
        $scopeId      = $this->request->getPost('scope_id') ?: null;
        if ($scopeId !== null) {
            $scopeId = (int) $scopeId ?: null;
        }

        // Enqueue for background processing
        $queueModel = new UploadQueueModel();
        try {
            $queueId = $queueModel->insert([
                'file_path'      => $relativePath,
                'original_name'  => $originalName,
                'file_size'      => filesize($fullPath),
                'file_extension' => $extension,
                'scope_id'       => $scopeId,
                'document_type'  => $documentType,
                'court_level'    => $courtLevel,
                'case_number'    => $caseNumber,
                'document_date'  => $documentDate,
                'status'         => 'pending',
                'uploaded_by'    => session()->get('user_id'),
            ]);
        } catch (\Throwable $e) {
            @unlink($fullPath);
            log_message('error', 'Queue insert failed: ' . $e->getMessage());
            return $this->jsonResponse([
                'status'    => 'error',
                'message'   => lang('App.error_occurred'),
                'file_name' => $originalName,
                'csrf_hash' => $csrfHash,
            ]);
        }

        // Schedule background processing after response is sent
        \App\Services\QueueProcessorService::scheduleAfterResponse();

        $this->auditModel->log('document_queued', "Queued: {$originalName}", 'upload_queue', $queueId);

        return $this->jsonResponse([
            'status'    => 'queued',
            'message'   => lang('App.upload_queued'),
            'file_name' => $originalName,
            'queue_id'  => $queueId,
            'csrf_hash' => $csrfHash,
        ]);
    }

    /**
     * Document detail page.
     */
    public function show(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            return redirect()->to(site_url('documents'))->with('error', lang('App.not_found'));
        }

        // Get related principles
        $principleModel = new \App\Models\LegalPrincipleModel();
        $principles = $principleModel->where('document_id', $id)->findAll();

        // Get related defenses (through legal_principles — defenses have principle_id, not document_id)
        $defenses = [];
        if (!empty($principles)) {
            $principleIds = array_column($principles, 'id');
            $defenses = (new \App\Models\DefenseModel())
                ->whereIn('principle_id', $principleIds)
                ->findAll();
        }

        return view('documents/show', $this->viewData([
            'title'      => $doc['title'],
            'document'   => $doc,
            'principles' => $principles,
            'defenses'   => $defenses,
        ]));
    }

    /**
     * Preview document text in browser.
     */
    public function preview(int $id)
    {
        $doc = $this->docModel->findForPreview($id);
        if (!$doc) {
            return $this->jsonResponse(['error' => lang('App.not_found')], 404);
        }

        return $this->jsonResponse([
            'title'          => $doc['title'],
            'full_text'      => $doc['full_text'] ?? '',
            'page_count'     => $doc['page_count'],
            'file_name'      => $doc['file_name'],
            'file_extension' => $doc['file_extension'],
            'document_type'  => $doc['document_type'],
            'court_level'    => $doc['court_level'],
            'case_number'    => $doc['case_number'],
            'document_date'  => $doc['document_date'],
        ]);
    }

    /**
     * Download original document file.
     * Supports both old flat directory and new original/ subdirectory.
     */
    public function download(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            return redirect()->to(site_url('documents'))->with('error', lang('App.not_found'));
        }

        $filePath = WRITEPATH . $doc['file_path'];
        if (!file_exists($filePath)) {
            // Try original/ subdirectory
            $altPath = WRITEPATH . 'uploads/documents/original/' . basename($doc['file_path']);
            if (file_exists($altPath)) {
                $filePath = $altPath;
            } else {
                return redirect()->to(site_url('documents'))->with('error', lang('App.not_found'));
            }
        }

        return $this->response->download($filePath, null)->setFileName($doc['file_name']);
    }

    /**
     * Stream the PDF version of a document for inline browser display.
     *
     * Pipeline:
     * 1. Resolve original file on disk (checks stored path + original/ subdirectory)
     * 2. Convert to PDF via LibreOffice (result is cached by content_hash)
     * 3. Stream PDF with Content-Disposition: inline so the browser renders it
     *
     * LibreOffice converts both .doc and .docx directly to PDF in one step,
     * preserving the original formatting, fonts, tables, and RTL layout exactly
     * as the document author intended.
     */
    public function servePdf(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }

        $contentHash = $doc['content_hash'] ?? '';
        $storedPath  = $doc['file_path'] ?? '';

        // Locate the original file on disk (no conversion at this stage)
        $filePath = WRITEPATH . $storedPath;
        if (!file_exists($filePath)) {
            $filePath = WRITEPATH . 'uploads/documents/original/' . basename($storedPath);
            if (!file_exists($filePath)) {
                return $this->response->setStatusCode(404)->setBody('File not found on disk');
            }
        }

        // Convert to PDF via LibreOffice (uses cache if already converted)
        $pdfResult = DocumentConversionService::convertToPdf($filePath, $contentHash);
        if (!$pdfResult['success']) {
            log_message('error', "[servePdf] ID {$id}: {$pdfResult['error']}");
            return $this->response
                ->setStatusCode(503)
                ->setHeader('Content-Type', 'text/plain; charset=utf-8')
                ->setBody('PDF conversion failed. LibreOffice may be busy — please retry.');
        }

        $pdfPath = $pdfResult['pdf_path'];
        $pdfSize = filesize($pdfPath);

        // Build a safe ASCII filename for Content-Disposition
        $baseName = pathinfo($doc['file_name'] ?? 'document', PATHINFO_FILENAME);
        $safeFile = preg_replace('/[^\w\-.]/', '_', $baseName) . '.pdf';

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $safeFile . '"')
            ->setHeader('Content-Length', (string) $pdfSize)
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody(file_get_contents($pdfPath));
    }

    /**
     * Render original DOCX/DOC as HTML with search highlights.
     *
     * Uses the Document Processing Pipeline:
     * 1. Resolve file path (supports both old flat + new original/ directories)
     * 2. For .doc files: convert to .docx via DocumentConversionService (LibreOffice)
     * 3. Generate HTML preview via DocumentPreviewService (with Arabic RTL post-processing)
     * 4. Falls back to plain text if file is missing or PhpWord pipeline fails
     *
     * Search terms from query parameters are passed to the view for client-side highlighting.
     *
     * Note: PDF mode (servePdf) is available via /documents/{id}/pdf but is NOT used here.
     * The HTML pipeline is always preferred for the render view (allows search highlighting).
     */
    public function render(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            return redirect()->to(site_url('documents'))->with('error', lang('App.not_found'));
        }

        $query      = $this->request->getGet('q') ?? '';
        $indocQuery = $this->request->getGet('q2') ?? '';

        $contentHash = $doc['content_hash'] ?? '';
        $storedPath  = $doc['file_path'] ?? '';

        // Step 1: Locate the original file on disk
        $filePath = WRITEPATH . $storedPath;
        if (!file_exists($filePath)) {
            $filePath = WRITEPATH . 'uploads/documents/original/' . basename($storedPath);
            if (!file_exists($filePath)) {
                $filePath = null;
            }
        }

        // Step 2: File not found — fall back to plain-text from DB
        if ($filePath === null) {
            log_message('warning', "Document render: file not found for ID {$id}, path: {$storedPath}");
            $preview = DocumentPreviewService::generatePlainTextPreview($doc['full_text'] ?? '');
            return view('documents/render', $this->viewData([
                'title'      => $doc['title'],
                'document'   => $doc,
                'docHtml'    => $preview['html'],
                'docStyles'  => $preview['styles'],
                'query'      => $query,
                'indocQuery' => $indocQuery,
                'theme'      => 'light',
                'forceLight' => true,
            ]));
        }

        // Step 3: HTML preview via PhpWord pipeline
        // For .doc files, resolveDocumentPath() converts to .docx via LibreOffice first.
        $resolved = DocumentConversionService::resolveDocumentPath($storedPath, $contentHash);
        $preview  = DocumentPreviewService::generateHtmlPreview($resolved['file_path'], $contentHash);

        if (!$preview['success']) {
            log_message('error', "Document render failed for ID {$id}: {$preview['error']}");
            $preview = DocumentPreviewService::generatePlainTextPreview($doc['full_text'] ?? '');
        }

        return view('documents/render', $this->viewData([
            'title'      => $doc['title'],
            'document'   => $doc,
            'docHtml'    => $preview['html'],
            'docStyles'  => $preview['styles'],
            'query'      => $query,
            'indocQuery' => $indocQuery,
            'theme'      => 'light',
            'forceLight' => true,
        ]));
    }

    /**
     * Edit document metadata form.
     */
    public function edit(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            return redirect()->to(site_url('documents'))->with('error', lang('App.not_found'));
        }

        return view('documents/form', $this->viewData([
            'title'    => lang('App.edit') . ': ' . $doc['title'],
            'document' => $doc,
        ]));
    }

    /**
     * Update document metadata.
     */
    public function update(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            return redirect()->to(site_url('documents'))->with('error', lang('App.not_found'));
        }

        $rules = [
            'title'         => 'required|max_length[500]',
            'document_type' => 'permit_empty|max_length[50]',
            'court_level'   => 'permit_empty|max_length[50]',
            'case_number'   => 'permit_empty|max_length[100]',
            'document_date' => 'permit_empty|valid_date',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->docModel->update($id, [
            'title'         => $this->request->getPost('title'),
            'document_type' => $this->request->getPost('document_type') ?: null,
            'court_level'   => $this->request->getPost('court_level') ?: null,
            'case_number'   => $this->request->getPost('case_number') ?: null,
            'document_date' => $this->request->getPost('document_date') ?: null,
            'keywords'      => $this->request->getPost('keywords') ?: null,
        ]);

        $this->auditModel->log('document_updated', "Updated: {$doc['title']}", 'document', $id);

        return redirect()->to(site_url("documents/{$id}"))->with('success', lang('App.saved_success'));
    }

    /**
     * Move a document to a different scope (or unscope it).
     * AJAX POST — returns JSON.
     */
    public function moveScope(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.not_found')], 404);
        }

        $rawScope   = $this->request->getPost('scope_id');
        $targetScope = ($rawScope === '' || $rawScope === null) ? null : (int) $rawScope;

        // If moving to a specific scope, verify the current user can access it
        if ($targetScope !== null) {
            $scopeModel = new SearchScopeModel();
            $isAdmin    = $this->can('scopes.manage');
            $userId     = (int) session()->get('user_id');
            $roleId     = (int) ($this->currentUser['role_id'] ?? 0);

            if (!$scopeModel->userCanAccessScope($targetScope, $userId, $roleId, $isAdmin)) {
                return $this->jsonResponse(['success' => false, 'message' => lang('App.forbidden')], 403);
            }
        }

        $oldScopeName = $doc['scope_id']
            ? (db_connect()->table('search_scopes')->select('name')->where('id', $doc['scope_id'])->get()->getRow()->name ?? '—')
            : '—';

        $this->docModel->update($id, ['scope_id' => $targetScope]);

        $newScopeName = $targetScope
            ? (db_connect()->table('search_scopes')->select('name')->where('id', $targetScope)->get()->getRow()->name ?? '—')
            : '—';

        $this->auditModel->log(
            'document_moved',
            "Moved \"{$doc['title']}\" from scope [{$oldScopeName}] to [{$newScopeName}]",
            'document',
            $id
        );

        return $this->jsonResponse([
            'success'   => true,
            'message'   => lang('App.doc_moved_success'),
            'scope_id'  => $targetScope,
        ]);
    }

    /**
     * Delete a document.
     * Cleans up original file, converted cache, preview cache, and extracted text cache.
     * Supports both AJAX (returns JSON) and normal form POST (returns redirect).
     */
    public function delete(int $id)
    {
        $doc = $this->docModel->find($id);
        if (!$doc) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => false, 'message' => lang('App.not_found')], 404);
            }
            return redirect()->to(site_url('documents'))->with('error', lang('App.not_found'));
        }

        $this->deleteDocumentFiles($doc);

        // Delete related records (CASCADE FK on defenses handles them automatically when principles are deleted)
        (new \App\Models\LegalPrincipleModel())->where('document_id', $id)->delete();

        $this->docModel->delete($id);
        $this->auditModel->log('document_deleted', "Deleted: {$doc['title']}", 'document', $id);

        if ($this->request->isAJAX()) {
            return $this->jsonResponse(['success' => true, 'message' => lang('App.deleted_success')]);
        }
        return redirect()->to(site_url('documents'))->with('success', lang('App.deleted_success'));
    }

    /**
     * Bulk delete multiple documents by ID array.
     * Accepts POST with ids[] array. Returns JSON with count of deleted documents.
     */
    public function bulkDelete()
    {
        $ids = $this->request->getPost('ids');

        if (empty($ids) || !is_array($ids)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.no_selection')], 400);
        }

        // Sanitize: ensure all IDs are positive integers
        $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);

        if (empty($ids)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.no_selection')], 400);
        }

        $principleModel = new \App\Models\LegalPrincipleModel();
        $deleted = 0;

        foreach ($ids as $id) {
            // Use find() (not findForPreview()) so file_path and content_hash
            // are included — deleteDocumentFiles() needs both to clean up disk
            // files and conversion/preview caches correctly.
            $doc = $this->docModel->find($id);
            if (!$doc) {
                continue;
            }

            $this->deleteDocumentFiles($doc);
            $principleModel->where('document_id', $id)->delete();
            $this->docModel->delete($id);
            $this->auditModel->log('document_deleted', "Deleted: {$doc['title']}", 'document', $id);
            $deleted++;
        }

        $message = lang('App.bulk_delete_success', [$deleted]);
        return $this->jsonResponse(['success' => true, 'message' => $message, 'deleted' => $deleted]);
    }

    /**
     * Bulk move documents to a different scope.
     * POST /documents/bulk-move-scope
     * Body: ids[] = int[], scope_id = int|'' (empty = unscoped)
     */
    public function bulkMoveScope()
    {
        $ids = $this->request->getPost('ids');

        if (empty($ids) || !is_array($ids)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.no_selection')], 400);
        }

        // Sanitize IDs
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));

        if (empty($ids)) {
            return $this->jsonResponse(['success' => false, 'message' => lang('App.no_selection')], 400);
        }

        $rawScope    = $this->request->getPost('scope_id');
        $targetScope = ($rawScope === '' || $rawScope === null) ? null : (int) $rawScope;

        // If moving to a real scope, verify the user can access it
        if ($targetScope !== null) {
            $scopeModel = new SearchScopeModel();
            $isAdmin    = $this->can('scopes.manage');
            $userId     = (int) session()->get('user_id');
            $roleId     = (int) ($this->currentUser['role_id'] ?? 0);

            if (!$scopeModel->userCanAccessScope($targetScope, $userId, $roleId, $isAdmin)) {
                return $this->jsonResponse(['success' => false, 'message' => lang('App.forbidden')], 403);
            }
        }

        $moved = 0;
        foreach ($ids as $id) {
            if (!$this->docModel->find($id)) continue;
            $this->docModel->update($id, ['scope_id' => $targetScope]);
            $moved++;
        }

        $newScopeName = $targetScope
            ? (db_connect()->table('search_scopes')->select('name')->where('id', $targetScope)->get()->getRow()->name ?? '—')
            : lang('App.unscoped');

        $this->auditModel->log(
            'documents_bulk_moved',
            "Bulk moved {$moved} documents to scope [{$newScopeName}]",
            'document',
            0
        );

        return $this->jsonResponse([
            'success' => true,
            'message' => lang('App.bulk_move_success', [$moved]),
            'moved'   => $moved,
        ]);
    }

    /**
     * Delete all documents belonging to a specific scope (or unscoped documents).
     *
     * POST body: scope_id (int|"null"|"")
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function deleteByScope()
    {
        $rawScope = $this->request->getPost('scope_id');

        // Determine whether we are deleting a specific scope or unscoped docs
        $isUnscoped = ($rawScope === 'null' || $rawScope === null || $rawScope === '');
        $scopeId    = $isUnscoped ? null : (int) $rawScope;

        // If a real scope_id was provided, verify the user can access it
        if (!$isUnscoped && $scopeId > 0) {
            $scopeModel = new SearchScopeModel();
            $isAdmin    = $this->can('scopes.manage');
            $userId     = (int) session()->get('user_id');
            $roleId     = (int) ($this->currentUser['role_id'] ?? 0);

            if (!$scopeModel->userCanAccessScope($scopeId, $userId, $roleId, $isAdmin)) {
                return $this->jsonResponse(['success' => false, 'message' => lang('App.forbidden')], 403);
            }
        }

        // Fetch documents for the given scope (or unscoped)
        $builder = $this->docModel->builder();
        if ($isUnscoped) {
            $builder->where('scope_id IS NULL', null, false);
        } else {
            $builder->where('scope_id', $scopeId);
        }
        $docs = $builder->get()->getResultArray();

        if (empty($docs)) {
            return $this->jsonResponse(['success' => true, 'message' => lang('App.no_docs_in_scope'), 'deleted' => 0]);
        }

        $principleModel = new \App\Models\LegalPrincipleModel();
        $deleted = 0;

        foreach ($docs as $doc) {
            $this->deleteDocumentFiles($doc);
            $principleModel->where('document_id', $doc['id'])->delete();
            $this->docModel->delete($doc['id']);
            $this->auditModel->log('document_deleted', "Deleted: {$doc['title']}", 'document', $doc['id']);
            $deleted++;
        }

        $message = lang('App.bulk_delete_success', [$deleted]);
        return $this->jsonResponse(['success' => true, 'message' => $message, 'deleted' => $deleted]);
    }

    /**
     * Remove all physical files and caches associated with a document record.
     *
     * @param array $doc Document row from DB
     */
    private function deleteDocumentFiles(array $doc): void
    {
        // Delete original file from disk (check both flat + original/ paths)
        $filePath = WRITEPATH . $doc['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        $altPath = WRITEPATH . 'uploads/documents/original/' . basename($doc['file_path']);
        if (file_exists($altPath)) {
            @unlink($altPath);
        }

        // Clear all cached files (converted, preview, extracted) for this document
        $contentHash = $doc['content_hash'] ?? '';
        if ($contentHash) {
            DocumentConversionService::clearCache($contentHash);
            DocumentPreviewService::clearCache($contentHash);
        }
    }
}
