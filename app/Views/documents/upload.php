<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <?= lang('App.upload_documents') ?>
        <?php if (!empty($preselectedScopeName)): ?>
            <span class="badge bg-primary fs-6 ms-2">
                <i class="bi bi-folder2-open"></i> <?= esc($preselectedScopeName) ?>
            </span>
        <?php endif; ?>
    </h4>
    <a href="<?= site_url('documents') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">

        <!-- ── Upload Zone ──────────────────────────────────────── -->
        <div class="upload-zone mb-3" id="uploadZone">
            <div class="upload-icon mb-2">
                <i class="bi bi-cloud-arrow-up"></i>
            </div>
            <h5 class="mb-1"><?= lang('App.drag_files_here') ?></h5>
            <p class="text-muted mb-3">.docx, .doc</p>

            <!-- Hidden file inputs -->
            <input type="file" id="fileInput" class="d-none" multiple accept=".docx,.doc">
            <input type="file" id="folderInput" class="d-none" webkitdirectory directory multiple>

            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSelectFiles">
                    <i class="bi bi-file-earmark-plus"></i> <?= lang('App.select_files') ?>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSelectFolder">
                    <i class="bi bi-folder2-open"></i> <?= lang('App.select_entire_folder') ?>
                </button>
            </div>

            <!-- Cloud Import Buttons -->
            <div class="mt-3 pt-3 border-top">
                <small class="text-muted d-block mb-2"><?= lang('App.or_import_from') ?></small>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <button type="button" class="btn btn-sm cloud-btn cloud-btn-google" id="btnGoogleDrive"
                            data-configured="<?= !empty($googlePickerApiKey) && !empty($googlePickerClientId) ? '1' : '0' ?>">
                        <i class="bi bi-google"></i> <?= lang('App.cloud_google_drive') ?>
                    </button>
                    <button type="button" class="btn btn-sm cloud-btn cloud-btn-dropbox" id="btnDropbox"
                            data-configured="<?= !empty($dropboxAppKey) ? '1' : '0' ?>">
                        <i class="bi bi-dropbox"></i> <?= lang('App.cloud_dropbox') ?>
                    </button>
                    <button type="button" class="btn btn-sm cloud-btn cloud-btn-onedrive" id="btnOneDrive"
                            data-configured="<?= !empty($onedriveClientId) ? '1' : '0' ?>">
                        <i class="bi bi-microsoft"></i> <?= lang('App.cloud_onedrive') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── File Queue (hidden until files selected) ─────────── -->
        <div id="fileQueueSection" class="d-none">

            <!-- Progress Bar -->
            <div class="mb-3" id="progressSection">
                <div class="d-flex justify-content-between mb-1">
                    <small class="fw-bold"><?= lang('App.upload_progress') ?></small>
                    <small id="progressText">0 / 0</small>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%"></div>
                </div>
                <!-- Summary counters -->
                <div class="d-flex gap-3 mt-2 upload-summary-counters">
                    <small class="text-success"><i class="bi bi-check-circle-fill"></i> <span id="countSuccess">0</span> <?= lang('App.upload_successful') ?></small>
                    <small class="text-warning"><i class="bi bi-arrow-repeat"></i> <span id="countDuplicate">0</span> <?= lang('App.upload_duplicate') ?></small>
                    <small class="text-danger"><i class="bi bi-x-circle-fill"></i> <span id="countError">0</span> <?= lang('App.upload_failed') ?></small>
                </div>
            </div>

            <!-- File List -->
            <div id="fileList" class="upload-file-list mb-3"></div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-primary btn-sm" id="btnStartUpload" disabled>
                    <i class="bi bi-upload"></i> <?= lang('App.start_upload') ?>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnCancelUpload">
                    <i class="bi bi-x-circle"></i> <?= lang('App.cancel_upload') ?>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnClearQueue">
                    <i class="bi bi-trash3"></i> <?= lang('App.clear') ?>
                </button>
            </div>
        </div>

        <!-- ── Optional Metadata (collapsible) ──────────────────── -->
        <div class="mt-2">
            <a class="text-decoration-none fw-bold" data-bs-toggle="collapse" href="#metadataSection" role="button" aria-expanded="false">
                <i class="bi bi-chevron-down"></i> <?= lang('App.optional_metadata') ?>
            </a>
            <div class="collapse mt-2" id="metadataSection">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="meta_scope_id" class="form-label"><?= lang('App.scope') ?></label>
                        <select id="meta_scope_id" class="form-select form-select-sm">
                            <option value=""><?= lang('App.no_scope') ?></option>
                            <?php if (!empty($preselectedScopeId) && !empty($preselectedScopeName)): ?>
                                <option value="<?= (int)$preselectedScopeId ?>" selected><?= esc($preselectedScopeName) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="meta_document_type" class="form-label"><?= lang('App.document_type') ?></label>
                        <select id="meta_document_type" class="form-select form-select-sm">
                            <option value="">-</option>
                            <option value="ruling"><?= lang('App.type_ruling') ?></option>
                            <option value="memorandum"><?= lang('App.type_memorandum') ?></option>
                            <option value="law"><?= lang('App.type_law') ?></option>
                            <option value="regulation"><?= lang('App.type_regulation') ?></option>
                            <option value="legal_opinion"><?= lang('App.type_legal_opinion') ?></option>
                            <option value="contract"><?= lang('App.type_contract') ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="meta_court_level" class="form-label"><?= lang('App.court_level') ?></label>
                        <select id="meta_court_level" class="form-select form-select-sm">
                            <option value="">-</option>
                            <option value="first_instance"><?= lang('App.court_first_instance') ?></option>
                            <option value="appeal"><?= lang('App.court_appeal') ?></option>
                            <option value="tamyeez"><?= lang('App.court_tamyeez') ?></option>
                            <option value="administrative"><?= lang('App.court_administrative') ?></option>
                            <option value="constitutional"><?= lang('App.court_constitutional') ?></option>
                            <option value="commercial"><?= lang('App.court_commercial') ?></option>
                            <option value="criminal"><?= lang('App.court_criminal') ?></option>
                            <option value="personal_status"><?= lang('App.court_personal_status') ?></option>
                            <option value="labor"><?= lang('App.court_labor') ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="meta_case_number" class="form-label"><?= lang('App.case_number') ?></label>
                        <input type="text" id="meta_case_number" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label for="meta_document_date" class="form-label"><?= lang('App.document_date') ?></label>
                        <input type="date" id="meta_document_date" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>

<?php // ── Load cloud SDKs only if configured ── ?>
<?php if (!empty($dropboxAppKey)): ?>
<script type="text/javascript" src="https://www.dropbox.com/static/api/2/dropins.js" id="dropboxjs" data-app-key="<?= esc($dropboxAppKey) ?>"></script>
<?php endif; ?>
<?php if (!empty($googlePickerApiKey) && !empty($googlePickerClientId)): ?>
<script src="https://apis.google.com/js/api.js" async defer></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // ── Translation strings ──────────────────────────────────────
    const L = {
        pending:          '<?= lang('App.upload_pending') ?>',
        processing:       '<?= lang('App.upload_processing') ?>',
        queued:           '<?= lang('App.upload_queued_short') ?>',
        successful:       '<?= lang('App.upload_successful') ?>',
        duplicate:        '<?= lang('App.upload_duplicate') ?>',
        failed:           '<?= lang('App.upload_failed') ?>',
        cancelled:        '<?= lang('App.upload_cancelled') ?>',
        complete:         '<?= lang('App.upload_complete') ?>',
        noValidFiles:     '<?= lang('App.upload_no_valid_files') ?>',
        unsupported:      '<?= lang('App.upload_unsupported_format') ?>',
        notConfigured:    '<?= lang('App.cloud_not_configured') ?>',
        loadingPicker:    '<?= lang('App.cloud_loading_picker') ?>',
        authRequired:     '<?= lang('App.cloud_auth_required') ?>',
        fetchingFile:     '<?= lang('App.cloud_fetching_file') ?>',
    };

    // ── Cloud config ─────────────────────────────────────────────
    const cloudConfig = {
        google: {
            apiKey:   '<?= esc($googlePickerApiKey ?? '') ?>',
            clientId: '<?= esc($googlePickerClientId ?? '') ?>',
            appId:    '<?= esc($googlePickerAppId ?? '') ?>',
        },
        dropbox: {
            appKey: '<?= esc($dropboxAppKey ?? '') ?>',
        },
        onedrive: {
            clientId: '<?= esc($onedriveClientId ?? '') ?>',
        },
    };

    // ── DOM references ───────────────────────────────────────────
    const zone           = document.getElementById('uploadZone');
    const fileInput      = document.getElementById('fileInput');
    const folderInput    = document.getElementById('folderInput');
    const btnSelectFiles = document.getElementById('btnSelectFiles');
    const btnSelectFolder= document.getElementById('btnSelectFolder');
    const fileQueueSec   = document.getElementById('fileQueueSection');
    const fileListEl     = document.getElementById('fileList');
    const progressBar    = document.getElementById('progressBar');
    const progressText   = document.getElementById('progressText');
    const countSuccess   = document.getElementById('countSuccess');
    const countDuplicate = document.getElementById('countDuplicate');
    const countError     = document.getElementById('countError');
    const btnStart       = document.getElementById('btnStartUpload');
    const btnCancel      = document.getElementById('btnCancelUpload');
    const btnClear       = document.getElementById('btnClearQueue');

    // ── State ────────────────────────────────────────────────────
    let fileQueue = [];       // Array of { file: File, status: 'pending'|'success'|'duplicate'|'error'|'cancelled', message: '' }
    let isUploading = false;
    let cancelRequested = false;

    const VALID_EXTENSIONS = ['.docx', '.doc'];
    const MAX_FILE_SIZE = 60 * 1024 * 1024; // 60 MB client-side limit (PHP allows 64M)

    // ── Utility: filter valid files ──────────────────────────────
    function filterValidFiles(fileList) {
        const valid = [];
        let oversized = 0;
        for (let i = 0; i < fileList.length; i++) {
            const f = fileList[i];
            const ext = '.' + f.name.split('.').pop().toLowerCase();
            if (!VALID_EXTENSIONS.includes(ext)) continue;
            if (f.size > MAX_FILE_SIZE) {
                oversized++;
                continue;
            }
            valid.push(f);
        }
        if (oversized > 0) {
            Qanony.toast(oversized + ' file(s) exceeded 60 MB limit', 'warning');
        }
        return valid;
    }

    // ── Add files to queue ───────────────────────────────────────
    function addFilesToQueue(files) {
        const validFiles = filterValidFiles(files);
        if (validFiles.length === 0) {
            Qanony.toast(L.noValidFiles, 'warning');
            return;
        }
        for (const f of validFiles) {
            // Avoid adding the exact same file object twice
            const alreadyQueued = fileQueue.some(item =>
                item.file.name === f.name && item.file.size === f.size && item.file.lastModified === f.lastModified
            );
            if (!alreadyQueued) {
                fileQueue.push({ file: f, status: 'pending', message: '' });
            }
        }
        renderFileList();
        showQueue();
    }

    // ── Show/hide queue section ──────────────────────────────────
    function showQueue() {
        fileQueueSec.classList.remove('d-none');
        btnStart.disabled = fileQueue.length === 0 || isUploading;
    }

    function hideQueue() {
        fileQueueSec.classList.add('d-none');
    }

    // ── Render file list ─────────────────────────────────────────
    function renderFileList() {
        if (fileQueue.length === 0) {
            fileListEl.innerHTML = '';
            hideQueue();
            return;
        }

        let html = '';
        fileQueue.forEach(function(item, idx) {
            const sizeMB = (item.file.size / 1024 / 1024).toFixed(2);
            const statusBadge = getStatusBadge(item.status, item.message);
            html += '<div class="upload-file-item d-flex justify-content-between align-items-center" id="fq-' + idx + '">';
            html += '  <div class="text-truncate flex-grow-1">';
            html += '    <i class="bi bi-file-earmark-text me-1"></i>';
            html += '    <span class="upload-file-name">' + escHtml(item.file.name) + '</span>';
            if (item.file.webkitRelativePath) {
                html += ' <small class="text-muted">(' + escHtml(item.file.webkitRelativePath) + ')</small>';
            }
            html += '  </div>';
            html += '  <div class="d-flex align-items-center gap-2 flex-shrink-0">';
            html += '    <small class="text-muted">' + sizeMB + ' MB</small>';
            html += '    <span class="upload-file-status" id="fqs-' + idx + '">' + statusBadge + '</span>';
            html += '  </div>';
            html += '</div>';
        });
        fileListEl.innerHTML = html;
    }

    function getStatusBadge(status, message) {
        switch (status) {
            case 'pending':
                return '<span class="badge bg-secondary"><i class="bi bi-clock"></i> ' + L.pending + '</span>';
            case 'processing':
                return '<span class="badge bg-info"><span class="spinner-border spinner-border-sm" style="width:.75rem;height:.75rem"></span> ' + L.processing + '</span>';
            case 'queued':
                return '<span class="badge bg-primary"><i class="bi bi-hourglass-split"></i> ' + L.queued + '</span>';
            case 'success':
                return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> ' + L.successful + '</span>';
            case 'duplicate':
                return '<span class="badge bg-warning text-dark"><i class="bi bi-arrow-repeat"></i> ' + L.duplicate + '</span>';
            case 'error':
                return '<span class="badge bg-danger" title="' + escHtml(message) + '"><i class="bi bi-x-circle"></i> ' + L.failed + '</span>';
            case 'cancelled':
                return '<span class="badge bg-secondary"><i class="bi bi-dash-circle"></i> ' + L.cancelled + '</span>';
            default:
                return '';
        }
    }

    function updateFileStatus(idx, status, message) {
        fileQueue[idx].status = status;
        fileQueue[idx].message = message || '';
        const el = document.getElementById('fqs-' + idx);
        if (el) el.innerHTML = getStatusBadge(status, message);
    }

    function updateProgress() {
        const total = fileQueue.length;
        // 'queued' counts as done from the upload perspective (file is safely saved)
        const done = fileQueue.filter(f => !['pending', 'processing'].includes(f.status)).length;
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
        progressBar.style.width = pct + '%';
        progressBar.setAttribute('aria-valuenow', pct);
        progressText.textContent = done + ' / ' + total;

        const sCount = fileQueue.filter(f => f.status === 'queued' || f.status === 'success').length;
        const dCount = fileQueue.filter(f => f.status === 'duplicate').length;
        const eCount = fileQueue.filter(f => f.status === 'error').length;
        countSuccess.textContent = sCount;
        countDuplicate.textContent = dCount;
        countError.textContent = eCount;
    }

    // ── HTML escape helper ───────────────────────────────────────
    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    // ── Get metadata from form ───────────────────────────────────
    function getMetadata() {
        return {
            scope_id:      document.getElementById('meta_scope_id').value,
            document_type: document.getElementById('meta_document_type').value,
            court_level:   document.getElementById('meta_court_level').value,
            case_number:   document.getElementById('meta_case_number').value,
            document_date: document.getElementById('meta_document_date').value,
        };
    }

    // ── CSRF recovery: fetch a fresh token from the upload page ──
    async function fetchFreshCsrf() {
        try {
            const resp = await fetch(Qanony.siteUrl + '/documents/upload', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!resp.ok) return false;
            const html = await resp.text();
            // Use the actual token name from config (handles both fixed and randomized names)
            const tokenName = Qanony.csrfTokenName || 'csrf_token';
            const re = new RegExp('<meta\\s+name=["\']' + tokenName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '["\']\\s+content=["\']([^"\']+)["\']', 'i');
            const match = html.match(re);
            if (match && match[1]) {
                Qanony.csrfHash = match[1];
                // Also update the meta tag on the current page
                const meta = document.querySelector('meta[name="' + tokenName + '"]');
                if (meta) meta.setAttribute('content', match[1]);
                return true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    // ── Upload a single file (with 1 CSRF retry) ────────────────
    async function uploadOneFile(idx, uploadUrl, metadata) {
        updateFileStatus(idx, 'processing', '');

        // Scroll the file item into view
        const itemEl = document.getElementById('fq-' + idx);
        if (itemEl) itemEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        let retried = false;

        async function attempt() {
            const formData = new FormData();
            formData.append('document', fileQueue[idx].file);
            if (metadata.scope_id)      formData.append('scope_id', metadata.scope_id);
            if (metadata.document_type) formData.append('document_type', metadata.document_type);
            if (metadata.court_level)   formData.append('court_level', metadata.court_level);
            if (metadata.case_number)   formData.append('case_number', metadata.case_number);
            if (metadata.document_date) formData.append('document_date', metadata.document_date);

            try {
                const data = await Qanony.ajax(uploadUrl, {
                    method: 'POST',
                    body: formData,
                });

                if (data) {
                    // Check for CSRF error (403 — both HTML and JSON responses)
                    if (data._csrfError && !retried) {
                        retried = true;
                        const recovered = await fetchFreshCsrf();
                        if (recovered) return attempt(); // retry once
                    }
                    updateFileStatus(idx, data.status || 'error', data.message || '');
                } else {
                    updateFileStatus(idx, 'error', 'No response');
                }
            } catch (err) {
                // err.data may contain the synthetic error object from Q.ajax
                if (err.data && err.data._csrfError && !retried) {
                    retried = true;
                    const recovered = await fetchFreshCsrf();
                    if (recovered) return attempt(); // retry once
                }
                const msg = (err.data && err.data.message) ? err.data.message : 'Network error';
                updateFileStatus(idx, 'error', msg);
            }
        }

        await attempt();
        updateProgress();
    }

    // ── AJAX upload engine (3-concurrent parallel pool) ──────────
    async function startUpload() {
        if (isUploading) return;
        isUploading = true;
        cancelRequested = false;

        btnStart.disabled = true;
        btnCancel.classList.remove('d-none');
        btnClear.classList.add('d-none');
        btnSelectFiles.disabled = true;
        btnSelectFolder.disabled = true;

        const uploadUrl = Qanony.siteUrl + '/documents/upload-single';
        const metadata = getMetadata();
        const CONCURRENCY = 5;

        // Build list of indices that need uploading
        const pendingIndices = [];
        for (let i = 0; i < fileQueue.length; i++) {
            if (fileQueue[i].status === 'pending') pendingIndices.push(i);
        }

        let cursor = 0; // next index in pendingIndices to dispatch

        // Worker: picks next pending file, uploads it, repeats until done
        async function worker() {
            while (true) {
                if (cancelRequested) return;

                // Atomically grab next index
                const pos = cursor++;
                if (pos >= pendingIndices.length) return; // no more work

                const idx = pendingIndices[pos];

                // Check cancel again before starting this file
                if (cancelRequested) {
                    updateFileStatus(idx, 'cancelled', '');
                    updateProgress();
                    return;
                }

                await uploadOneFile(idx, uploadUrl, metadata);
            }
        }

        // Launch up to CONCURRENCY workers
        const workerCount = Math.min(CONCURRENCY, pendingIndices.length);
        const workers = [];
        for (let w = 0; w < workerCount; w++) {
            workers.push(worker());
        }

        // Wait for all workers to finish
        await Promise.all(workers);

        // If cancelled, mark any remaining pending as cancelled
        if (cancelRequested) {
            for (let i = 0; i < fileQueue.length; i++) {
                if (fileQueue[i].status === 'pending') {
                    updateFileStatus(i, 'cancelled', '');
                }
            }
            updateProgress();
        }

        // Upload finished
        isUploading = false;
        btnCancel.classList.add('d-none');
        btnClear.classList.remove('d-none');
        btnSelectFiles.disabled = false;
        btnSelectFolder.disabled = false;

        const sCount = fileQueue.filter(f => f.status === 'queued' || f.status === 'success').length;
        if (sCount > 0) {
            Qanony.toast(L.complete + ' (' + sCount + ' ' + L.queued + ')', 'success');
        } else if (cancelRequested) {
            Qanony.toast(L.cancelled, 'warning');
        }
    }

    // ── Load scopes dropdown when metadata section is expanded ───
    const preselectedScopeId = '<?= (int)($preselectedScopeId ?? 0) ?>';
    let scopesLoaded = false;

    function loadScopesDropdown() {
        if (scopesLoaded) return;
        scopesLoaded = true;
        Qanony.ajax(Qanony.siteUrl + '/scopes/dropdown').then(function(data) {
            const sel = document.getElementById('meta_scope_id');
            // Remove any previously-inserted pre-selected option (keep only the blank one)
            while (sel.options.length > 1) sel.remove(1);
            if (data && data.items) {
                data.items.forEach(function(item) {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name;
                    if (String(item.id) === preselectedScopeId) opt.selected = true;
                    sel.appendChild(opt);
                });
            }
            // If preselected not found in list, keep blank selected (no stray option)
        }).catch(function() { /* ignore */ });
    }

    document.getElementById('metadataSection').addEventListener('show.bs.collapse', loadScopesDropdown);

    // If a scope is pre-selected, auto-expand the metadata section so user sees it
    <?php if (!empty($preselectedScopeId)): ?>
    (function() {
        const el = document.getElementById('metadataSection');
        new bootstrap.Collapse(el, { toggle: false }).show();
        loadScopesDropdown();
    })();
    <?php endif; ?>

    // ── Event: Select Files button ───────────────────────────────
    btnSelectFiles.addEventListener('click', function() { fileInput.click(); });
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) addFilesToQueue(this.files);
        this.value = '';
    });

    // ── Event: Select Folder button ──────────────────────────────
    btnSelectFolder.addEventListener('click', function() { folderInput.click(); });
    folderInput.addEventListener('change', function() {
        if (this.files.length > 0) addFilesToQueue(this.files);
        this.value = '';
    });

    // ── Event: Drag and Drop ─────────────────────────────────────
    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove('dragover');
    });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove('dragover');

        // Try to get folder entries via DataTransferItem (supports recursive reading)
        const items = e.dataTransfer.items;
        if (items && items.length > 0 && items[0].webkitGetAsEntry) {
            const entries = [];
            for (let i = 0; i < items.length; i++) {
                const entry = items[i].webkitGetAsEntry();
                if (entry) entries.push(entry);
            }
            readEntriesRecursive(entries).then(function(files) {
                addFilesToQueue(files);
            });
        } else {
            // Fallback: flat file list
            addFilesToQueue(e.dataTransfer.files);
        }
    });

    // ── Recursive entry reader for drag-dropped folders ──────────
    // Per FileSystem API spec, readEntries() may return partial results
    // for large directories (typically >100 files). Must call repeatedly
    // until an empty array is returned to guarantee ALL files are read.
    function readEntriesRecursive(entries) {
        return new Promise(function(resolve) {
            const files = [];
            let pending = entries.length;
            if (pending === 0) { resolve(files); return; }

            function processEntry(entry) {
                if (entry.isFile) {
                    entry.file(function(f) {
                        files.push(f);
                        if (--pending === 0) resolve(files);
                    }, function() {
                        if (--pending === 0) resolve(files);
                    });
                } else if (entry.isDirectory) {
                    const reader = entry.createReader();
                    // readAllEntries: call readEntries() in a loop until
                    // it returns an empty array (spec requirement)
                    const allChildEntries = [];
                    (function readBatch() {
                        reader.readEntries(function(batch) {
                            if (batch.length === 0) {
                                // All entries read for this directory
                                if (allChildEntries.length === 0) {
                                    // Empty directory, just decrement
                                    if (--pending === 0) resolve(files);
                                } else {
                                    pending += allChildEntries.length;
                                    // Decrement for the directory entry itself
                                    if (--pending === 0) { resolve(files); return; }
                                    allChildEntries.forEach(processEntry);
                                }
                            } else {
                                // Accumulate this batch and read more
                                for (let i = 0; i < batch.length; i++) {
                                    allChildEntries.push(batch[i]);
                                }
                                readBatch(); // Keep reading until empty
                            }
                        }, function() {
                            // Error reading directory — process what we have
                            if (allChildEntries.length === 0) {
                                if (--pending === 0) resolve(files);
                            } else {
                                pending += allChildEntries.length;
                                if (--pending === 0) { resolve(files); return; }
                                allChildEntries.forEach(processEntry);
                            }
                        });
                    })();
                } else {
                    if (--pending === 0) resolve(files);
                }
            }

            entries.forEach(processEntry);
        });
    }

    // ── Prevent zone click from opening file dialog (buttons handle it) ──
    zone.addEventListener('click', function(e) {
        // Only trigger if clicking the zone background itself
        if (e.target === zone || e.target.closest('.upload-icon') || e.target.tagName === 'H5' || e.target.tagName === 'P') {
            fileInput.click();
        }
    });

    // ── Event: Start Upload ──────────────────────────────────────
    btnStart.addEventListener('click', function() { startUpload(); });

    // ── Event: Cancel Upload ─────────────────────────────────────
    btnCancel.addEventListener('click', function() { cancelRequested = true; });

    // ── Event: Clear Queue ───────────────────────────────────────
    btnClear.addEventListener('click', function() {
        fileQueue = [];
        renderFileList();
        updateProgress();
        hideQueue();
        btnClear.classList.add('d-none');
        progressBar.style.width = '0%';
        progressText.textContent = '0 / 0';
        countSuccess.textContent = '0';
        countDuplicate.textContent = '0';
        countError.textContent = '0';
    });

    // ═══════════════════════════════════════════════════════════════
    // ── CLOUD PROVIDERS ──────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════

    // ── Helper: upload a fetched blob as if it were a local file ──
    function blobToFile(blob, fileName) {
        return new File([blob], fileName, { type: blob.type, lastModified: Date.now() });
    }

    // ── GOOGLE DRIVE ─────────────────────────────────────────────
    document.getElementById('btnGoogleDrive').addEventListener('click', function() {
        if (this.dataset.configured !== '1') {
            Qanony.toast(L.notConfigured.replace('{0}', 'Google Drive'), 'warning');
            return;
        }
        openGooglePicker();
    });

    let googleAccessToken = null;

    function openGooglePicker() {
        Qanony.toast(L.loadingPicker, 'info', 2000);

        // Load the Google API client + picker
        gapi.load('picker', function() {
            // Get access token via GIS (Google Identity Services)
            const tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: cloudConfig.google.clientId,
                scope: 'https://www.googleapis.com/auth/drive.readonly',
                callback: function(response) {
                    if (response.error) {
                        Qanony.toast(L.authRequired.replace('{0}', 'Google'), 'danger');
                        return;
                    }
                    googleAccessToken = response.access_token;
                    createGooglePicker();
                },
            });
            tokenClient.requestAccessToken();
        });
    }

    function createGooglePicker() {
        const docsView = new google.picker.DocsView()
            .setIncludeFolders(true)
            .setSelectFolderEnabled(false)
            .setMimeTypes('application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/msword');

        const picker = new google.picker.PickerBuilder()
            .addView(docsView)
            .setOAuthToken(googleAccessToken)
            .setDeveloperKey(cloudConfig.google.apiKey)
            .setAppId(cloudConfig.google.appId)
            .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
            .setCallback(googlePickerCallback)
            .build();
        picker.setVisible(true);
    }

    async function googlePickerCallback(data) {
        if (data[google.picker.Response.ACTION] !== google.picker.Action.PICKED) return;

        const docs = data[google.picker.Response.DOCUMENTS];
        for (const doc of docs) {
            const fileId = doc[google.picker.Document.ID];
            const fileName = doc[google.picker.Document.NAME];

            Qanony.toast(L.fetchingFile.replace('{0}', 'Google Drive') + ' ' + fileName, 'info', 3000);

            try {
                const resp = await fetch('https://www.googleapis.com/drive/v3/files/' + fileId + '?alt=media', {
                    headers: { 'Authorization': 'Bearer ' + googleAccessToken }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const blob = await resp.blob();
                const file = blobToFile(blob, fileName);
                addFilesToQueue([file]);
            } catch (err) {
                Qanony.toast(fileName + ': ' + (err.message || L.failed), 'danger');
            }
        }
    }

    // ── DROPBOX ──────────────────────────────────────────────────
    document.getElementById('btnDropbox').addEventListener('click', function() {
        if (this.dataset.configured !== '1') {
            Qanony.toast(L.notConfigured.replace('{0}', 'Dropbox'), 'warning');
            return;
        }
        openDropboxChooser();
    });

    function openDropboxChooser() {
        if (typeof Dropbox === 'undefined' || !Dropbox.choose) {
            Qanony.toast(L.loadingPicker, 'warning');
            return;
        }

        Dropbox.choose({
            success: async function(files) {
                for (const f of files) {
                    Qanony.toast(L.fetchingFile.replace('{0}', 'Dropbox') + ' ' + f.name, 'info', 3000);
                    try {
                        const resp = await fetch(f.link);
                        if (!resp.ok) throw new Error('HTTP ' + resp.status);
                        const blob = await resp.blob();
                        const file = blobToFile(blob, f.name);
                        addFilesToQueue([file]);
                    } catch (err) {
                        Qanony.toast(f.name + ': ' + (err.message || L.failed), 'danger');
                    }
                }
            },
            cancel: function() {},
            linkType: 'direct',
            multiselect: true,
            extensions: ['.docx', '.doc'],
            folderselect: false,
        });
    }

    // ── ONEDRIVE ─────────────────────────────────────────────────
    document.getElementById('btnOneDrive').addEventListener('click', function() {
        if (this.dataset.configured !== '1') {
            Qanony.toast(L.notConfigured.replace('{0}', 'OneDrive'), 'warning');
            return;
        }
        openOneDrivePicker();
    });

    function openOneDrivePicker() {
        // OneDrive File Picker v8 uses a popup with postMessage
        const baseUrl = 'https://onedrive.live.com/picker';
        const params = new URLSearchParams({
            client_id: cloudConfig.onedrive.clientId,
            action: 'download',
            multiselect: 'true',
            advanced: JSON.stringify({
                filter: {
                    extension: { allow: ['.docx', '.doc'] }
                }
            }),
        });

        const pickerWindow = window.open(baseUrl + '?' + params.toString(), 'OneDrivePicker', 'width=800,height=600');

        window.addEventListener('message', async function handler(event) {
            if (event.source !== pickerWindow) return;

            let data;
            try {
                data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
            } catch (e) {
                return;
            }

            if (data.type === 'cancel') {
                window.removeEventListener('message', handler);
                return;
            }

            if (data.type === 'success' && data.items) {
                window.removeEventListener('message', handler);
                pickerWindow.close();

                for (const item of data.items) {
                    const fileName = item.name || 'document.docx';
                    const downloadUrl = item['@content.downloadUrl'] || (item.permissions && item.permissions[0] && item.permissions[0].link && item.permissions[0].link.webUrl);

                    if (!downloadUrl) {
                        Qanony.toast(fileName + ': No download URL', 'danger');
                        continue;
                    }

                    Qanony.toast(L.fetchingFile.replace('{0}', 'OneDrive') + ' ' + fileName, 'info', 3000);
                    try {
                        const resp = await fetch(downloadUrl);
                        if (!resp.ok) throw new Error('HTTP ' + resp.status);
                        const blob = await resp.blob();
                        const file = blobToFile(blob, fileName);
                        addFilesToQueue([file]);
                    } catch (err) {
                        Qanony.toast(fileName + ': ' + (err.message || L.failed), 'danger');
                    }
                }
            }
        });
    }

});
</script>
<?= $this->endSection() ?>
