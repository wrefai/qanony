<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<!-- Mobile: Scopes Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="docsOffcanvas">
    <div class="offcanvas-header">
        <h6 class="offcanvas-title"><i class="bi bi-folder2"></i> <?= lang('App.search_scopes') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0" id="docsOffcanvasBody">
        <!-- Scope tree cloned here by JS -->
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <!-- Mobile: scope button -->
        <button type="button" class="btn btn-outline-secondary d-lg-none"
                data-bs-toggle="offcanvas" data-bs-target="#docsOffcanvas">
            <i class="bi bi-folder2"></i>
        </button>
        <h4 class="mb-0"><?= lang('App.document_management') ?></h4>
    </div>
    <?php if (in_array('documents.create', $currentPermissions)): ?>
        <a href="<?= site_url('documents/upload') ?>" class="btn btn-primary" id="btnUpload">
            <i class="bi bi-upload"></i> <span class="d-none d-sm-inline"><?= lang('App.upload_documents') ?></span>
        </a>
    <?php endif; ?>
</div>

<!-- Main Split Layout: Scopes sidebar + Documents table -->
<div class="docs-split-layout">

    <!-- ══ SCOPES SIDEBAR ══ -->
    <div class="docs-scope-panel" id="scopePanel">
        <div class="scope-panel-header">
            <span><?= lang('App.search_scopes') ?></span>
            <?php if (in_array('documents.create', $currentPermissions)): ?>
            <button class="btn btn-sm btn-outline-primary scope-add-root-btn" id="btnAddRootScope" title="<?= lang('App.add_scope') ?>">
                <i class="bi bi-plus-lg"></i>
            </button>
            <?php endif; ?>
        </div>
        <div class="scope-tree" id="scopeTree">
            <div class="scope-tree-item scope-all active" data-scope-id="" id="scopeItemAll">
                <i class="bi bi-hdd-stack"></i>
                <span class="scope-label"><?= lang('App.all_scopes') ?></span>
            </div>
            <div class="scope-tree-item scope-unscoped" data-scope-id="none" id="scopeItemNone">
                <i class="bi bi-folder-x text-secondary"></i>
                <span class="scope-label"><?= lang('App.unscoped') ?></span>
            </div>
            <div id="scopeTreeNodes"><!-- populated by JS --></div>
        </div>
    </div>

    <!-- ══ DOCUMENTS AREA ══ -->
    <div class="docs-main-area">

        <!-- Active Scope Header (shown when a scope is selected) -->
        <div id="activeScopeHeader" class="active-scope-header d-none">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-folder2-open text-primary fs-5" id="activeScopeIcon"></i>
                <span id="activeScopeName" class="fw-semibold fs-6"></span>
                <span id="activeScopeCount" class="badge bg-secondary"></span>
            </div>
            <?php if (in_array('documents.delete', $currentPermissions)): ?>
            <div class="d-flex gap-2">
                <!-- Shown only for real scopes (not unscoped) -->
                <button id="btnDeleteActiveScope" class="btn btn-danger btn-sm d-none">
                    <i class="bi bi-trash3"></i> <?= lang('App.delete_scope_with_docs') ?>
                </button>
                <!-- Shown for any active scope / unscoped: delete all documents inside -->
                <button id="btnDeleteAllInScope" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash3-fill"></i> <?= lang('App.delete_all_docs_in_scope') ?>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" id="docSearch" class="form-control form-control-sm"
                           placeholder="<?= lang('App.search') ?>...">
                </div>
                <div class="col-md-3">
                    <select id="docTypeFilter" class="form-select form-select-sm">
                        <option value=""><?= lang('App.all') ?> <?= lang('App.type') ?></option>
                        <option value="ruling"><?= lang('App.type_ruling') ?></option>
                        <option value="memorandum"><?= lang('App.type_memorandum') ?></option>
                        <option value="law"><?= lang('App.type_law') ?></option>
                        <option value="regulation"><?= lang('App.type_regulation') ?></option>
                        <option value="legal_opinion"><?= lang('App.type_legal_opinion') ?></option>
                        <option value="contract"><?= lang('App.type_contract') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="docCourtFilter" class="form-select form-select-sm">
                        <option value=""><?= lang('App.all') ?> <?= lang('App.court_level') ?></option>
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
                    <button class="btn btn-sm btn-outline-secondary w-100" id="docClearFilters">
                        <i class="bi bi-x-lg"></i> <?= lang('App.clear') ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="docs-table-wrapper table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <?php if (in_array('documents.delete', $currentPermissions)): ?>
                                <th style="width:40px">
                                    <input type="checkbox" class="form-check-input" id="selectAllCheck"
                                           title="<?= lang('App.select_all') ?>">
                                </th>
                                <?php endif; ?>
                                <th>#</th>
                                <th><?= lang('App.document_title') ?></th>
                                <th><?= lang('App.document_type') ?></th>
                                <th><?= lang('App.court_level') ?></th>
                                <th><?= lang('App.case_number') ?></th>
                                <th><?= lang('App.page_count') ?></th>
                                <th><?= lang('App.file_size') ?></th>
                                <th><?= lang('App.date') ?></th>
                                <th><?= lang('App.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="docsTableBody">
                            <tr>
                                <td colspan="<?= in_array('documents.delete', $currentPermissions) ? '10' : '9' ?>" class="text-center py-4 text-muted"><?= lang('App.loading') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Mobile Card List -->
                <div class="doc-card-list" id="docCardList">
                    <div class="search-card-empty">
                        <i class="bi bi-file-earmark-text"></i>
                        <p><?= lang('App.loading') ?></p>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="pagination-wrapper">
                    <small class="text-muted" id="docsInfo"></small>
                    <div id="docsPagination"></div>
                </div>
            </div>
        </div>

    </div><!-- /.docs-main-area -->
</div><!-- /.docs-split-layout -->

<!-- Bulk Selection Bar -->
<?php if (in_array('documents.delete', $currentPermissions) || in_array('documents.update', $currentPermissions)): ?>
<div id="bulkBar" class="bulk-action-bar d-none">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <span id="bulkCount" class="fw-semibold text-white"></span>
        <?php if (in_array('documents.delete', $currentPermissions)): ?>
        <button id="bulkDeleteBtn" class="btn btn-danger btn-sm">
            <i class="bi bi-trash"></i> <?= lang('App.delete_selected') ?>
        </button>
        <?php endif; ?>
        <?php if (in_array('documents.update', $currentPermissions)): ?>
        <button id="bulkMoveBtn" class="btn btn-warning btn-sm text-dark">
            <i class="bi bi-folder-symlink"></i> <?= lang('App.move_selected') ?>
        </button>
        <?php endif; ?>
        <button id="bulkSelectScopeBtn" class="btn btn-outline-light btn-sm d-none" title="">
            <i class="bi bi-check2-all"></i> <span id="bulkSelectScopeLabel"></span>
        </button>
        <button id="bulkCancelBtn" class="btn btn-outline-light btn-sm">
            <i class="bi bi-x"></i> <?= lang('App.cancel') ?>
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Add / Rename Scope Modal -->
<div class="modal fade" id="scopeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="scopeModalTitle"><?= lang('App.add_scope') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scopeModalId" value="">
                <input type="hidden" id="scopeModalParentId" value="">
                <div class="mb-3">
                    <label class="form-label small"><?= lang('App.name') ?></label>
                    <input type="text" id="scopeModalName" class="form-control form-control-sm"
                           placeholder="<?= lang('App.scope_name_placeholder') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                <button type="button" class="btn btn-sm btn-primary" id="scopeModalSave"><?= lang('App.save') ?></button>
            </div>
        </div>
    </div>
</div>

<?php if (in_array('scopes.manage', $currentPermissions)): ?>
<!-- Scope Access Management Modal -->
<div class="modal fade" id="scopeAccessModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-shield-lock"></i>
                    <?= lang('App.scope_access_manage') ?> — <span id="accessModalScopeName"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Restriction toggle -->
                <div class="d-flex align-items-center justify-content-between mb-3 p-3 rounded border" id="restrictionToggleRow">
                    <div>
                        <div class="fw-semibold" id="restrictionLabel"><?= lang('App.scope_open') ?></div>
                        <small class="text-muted"><?= lang('App.scope_now_open') ?></small>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="toggleRestricted" style="width:2.5em;height:1.3em">
                        <label class="form-check-label" for="toggleRestricted"></label>
                    </div>
                </div>

                <!-- Access list (shown only when restricted) -->
                <div id="accessListSection">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong><?= lang('App.access_list') ?></strong>
                    </div>

                    <!-- Current entries -->
                    <div id="accessEntriesContainer" class="mb-3">
                        <div class="text-muted small text-center py-2" id="noAccessMsg"><?= lang('App.no_access_entries') ?></div>
                    </div>

                    <!-- Add new entry -->
                    <div class="card card-body bg-light p-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1"><?= lang('App.grant_to') ?>:</label>
                                <select id="grantType" class="form-select form-select-sm">
                                    <option value="user"><?= lang('App.grant_to_user') ?></option>
                                    <option value="role"><?= lang('App.grant_to_role') ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">&nbsp;</label>
                                <select id="grantTargetUser" class="form-select form-select-sm">
                                    <option value=""><?= lang('App.select_user') ?></option>
                                </select>
                                <select id="grantTargetRole" class="form-select form-select-sm d-none">
                                    <option value=""><?= lang('App.select_role') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">&nbsp;</label>
                                <button type="button" class="btn btn-sm btn-success w-100" id="btnGrantAccess">
                                    <i class="bi bi-plus-circle"></i> <?= lang('App.grant_access') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewTitle"><?= lang('App.preview') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent" class="document-preview"></div>
            </div>
        </div>
    </div>
</div>

<?php if (in_array('documents.update', $currentPermissions)): ?>
<!-- Move Document Modal -->
<div class="modal fade" id="moveDocModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-folder-symlink"></i> <?= lang('App.doc_move_title') ?>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="moveDocName"></p>
                <label class="form-label small fw-semibold"><?= lang('App.doc_move_target') ?></label>
                <select id="moveDocScopeSelect" class="form-select form-select-sm">
                    <option value=""><?= lang('App.unscoped') ?></option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                <button type="button" class="btn btn-sm btn-primary" id="moveDocSaveBtn">
                    <i class="bi bi-check-lg"></i> <?= lang('App.doc_move_confirm') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Move to Scope Modal -->
<div class="modal fade" id="bulkMoveModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-folder-symlink"></i> <?= lang('App.bulk_move_scope') ?>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="bulkMoveCountLabel"></p>
                <label class="form-label small fw-semibold"><?= lang('App.doc_move_target') ?></label>
                <select id="bulkMoveScopeSelect" class="form-select form-select-sm">
                    <option value=""><?= lang('App.unscoped') ?></option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
                <button type="button" class="btn btn-sm btn-warning text-dark" id="bulkMoveSaveBtn">
                    <i class="bi bi-check-lg"></i> <?= lang('App.doc_move_confirm') ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
/* ── Split layout ───────────────────────── */
.docs-split-layout {
    display: flex;
    gap: 0;
    align-items: flex-start;
    min-height: 0;
}
.docs-scope-panel {
    width: 240px;
    min-width: 200px;
    flex-shrink: 0;
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    margin-inline-end: 14px;
    overflow: hidden;
}
.docs-main-area {
    flex: 1;
    min-width: 0;
}
/* Scope panel header */
.scope-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--bs-secondary-color);
    border-bottom: 1px solid var(--bs-border-color);
    background: var(--bs-tertiary-bg);
}
/* Tree base items (All / Unscoped) */
.scope-tree { padding: 4px 0; }
.scope-tree-item {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 7px 12px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.12s;
    user-select: none;
    position: relative;
    border-bottom: 1px solid transparent;
}
.scope-tree-item:hover { background: var(--bs-tertiary-bg); }
.scope-tree-item.active {
    background: var(--bs-primary-bg-subtle);
    color: var(--bs-primary);
    font-weight: 600;
    border-inline-start: 3px solid var(--bs-primary);
}
/* Dynamic scope nodes */
.scope-node-row {
    display: flex;
    align-items: center;
    padding: 0 8px 0 0;
    border-bottom: 1px solid var(--bs-border-color-translucent);
    transition: background 0.1s;
}
.scope-node-row:hover { background: var(--bs-tertiary-bg); }
.scope-node-row.active {
    background: var(--bs-primary-bg-subtle);
    border-inline-start: 3px solid var(--bs-primary);
}
.scope-node-label {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 1;
    padding: 7px 8px;
    font-size: 0.875rem;
    cursor: pointer;
    min-width: 0;
    overflow: hidden;
}
.scope-node-label span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.scope-node-row.active .scope-node-label { font-weight: 600; color: var(--bs-primary); }
.scope-node-count {
    font-size: 0.7rem;
    color: var(--bs-secondary-color);
    background: var(--bs-secondary-bg);
    border-radius: 10px;
    padding: 1px 7px;
    flex-shrink: 0;
    margin-inline-end: 4px;
}
/* Delete button — always visible */
.scope-node-delete {
    flex-shrink: 0;
    padding: 2px 5px;
    font-size: 0.7rem;
    line-height: 1.4;
    opacity: 0.45;
    transition: opacity 0.15s;
}
.scope-node-row:hover .scope-node-delete { opacity: 1; }
/* Children indented */
.scope-children-wrap { padding-inline-start: 12px; }

/* Active scope header (above table) */
.active-scope-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background: var(--bs-primary-bg-subtle);
    border: 1px solid var(--bs-primary-border-subtle);
    border-radius: 8px;
    margin-bottom: 10px;
    gap: 10px;
}

/* Bulk bar */
.bulk-action-bar {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: #1a1a2e;
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 12px;
    padding: 12px 24px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.35);
    z-index: 1050;
    min-width: 320px;
}
[data-bs-theme="light"] .bulk-action-bar { background: #212529; }
</style>
<script>
(function() {
    const Q = window.Qanony;
    const canUpdate = <?= in_array('documents.update', $currentPermissions) ? 'true' : 'false' ?>;
    const canDelete = <?= in_array('documents.delete', $currentPermissions) ? 'true' : 'false' ?>;
    const canBulk   = canDelete || canUpdate;
    const canCreate = <?= in_array('documents.create', $currentPermissions) ? 'true' : 'false' ?>;
    const canManageAccess = <?= in_array('scopes.manage', $currentPermissions) ? 'true' : 'false' ?>;
    const colSpan   = canDelete ? 10 : 9;
    let currentPage = 1;
    let activeScopeId = ''; // '' = all, 'none' = unscoped, number string = specific

    // Selected IDs (persists across pages)
    const selected = new Set();

    const typeLabels = {
        ruling: '<?= lang('App.type_ruling') ?>',
        memorandum: '<?= lang('App.type_memorandum') ?>',
        law: '<?= lang('App.type_law') ?>',
        regulation: '<?= lang('App.type_regulation') ?>',
        legal_opinion: '<?= lang('App.type_legal_opinion') ?>',
        contract: '<?= lang('App.type_contract') ?>'
    };
    const courtLabels = {
        first_instance: '<?= lang('App.court_first_instance') ?>',
        appeal: '<?= lang('App.court_appeal') ?>',
        tamyeez: '<?= lang('App.court_tamyeez') ?>',
        administrative: '<?= lang('App.court_administrative') ?>',
        constitutional: '<?= lang('App.court_constitutional') ?>',
        commercial: '<?= lang('App.court_commercial') ?>',
        criminal: '<?= lang('App.court_criminal') ?>',
        personal_status: '<?= lang('App.court_personal_status') ?>',
        labor: '<?= lang('App.court_labor') ?>'
    };

    // ══ SCOPE TREE ════════════════════════════════════════════
    // scopeMap: { id -> { name, document_count } }
    const scopeMap = {};

    function loadScopeTree() {
        Q.ajax(Q.siteUrl + '/scopes/tree').then(function(data) {
            if (!data || !data.tree) return;
            const container = document.getElementById('scopeTreeNodes');
            container.innerHTML = renderScopeNodes(data.tree, 0);
            bindScopeClicks();
            // Refresh active scope header count if a scope is selected
            if (activeScopeId && activeScopeId !== 'none' && scopeMap[activeScopeId]) {
                updateActiveScopeHeader();
            }
        });
    }

    function collectScopeMap(nodes) {
        nodes.forEach(function(n) {
            scopeMap[String(n.id)] = { name: n.name, document_count: parseInt(n.document_count) || 0 };
            if (n.children && n.children.length) collectScopeMap(n.children);
        });
    }

    function renderScopeNodes(nodes, depth) {
        if (!nodes || nodes.length === 0) return '';
        collectScopeMap(nodes);
        let html = '';
        nodes.forEach(function(node) {
            const pad = 10 + depth * 14;
            const hasChildren = node.children && node.children.length > 0;
            const count = parseInt(node.document_count) || 0;
            const sid = String(node.id);
            const isActive = activeScopeId === sid;

            html += '<div class="scope-node-wrap" data-scope-id="' + sid + '">';

            // Row: label + count + delete button
            html += '<div class="scope-node-row' + (isActive ? ' active' : '') + '" data-scope-id="' + sid + '">';

            // Clickable label area
            html += '<div class="scope-node-label" style="padding-inline-start:' + pad + 'px" data-scope-id="' + sid + '">';
            html += '<i class="bi ' + (hasChildren ? 'bi-folder2' : 'bi-folder') + '"></i>';
            // Show lock icon if scope is restricted
            if (node.is_restricted && parseInt(node.is_restricted)) {
                html += '<i class="bi bi-lock-fill text-warning ms-1" title="<?= lang('App.scope_restricted') ?>" style="font-size:.75rem"></i>';
            }
            html += '<span title="' + escAttr(node.name) + '">' + escHtml(node.name) + '</span>';
            html += '</div>';

            // Count badge
            html += '<span class="scope-node-count">' + count + '</span>';

            // Delete button (always visible, red on hover)
            if (canDelete) {
                html += '<button class="btn btn-outline-danger scope-node-delete" '
                    + 'data-id="' + sid + '" '
                    + 'data-name="' + escAttr(node.name) + '" '
                    + 'data-count="' + count + '" '
                    + 'title="<?= lang('App.delete_scope_with_docs') ?>">'
                    + '<i class="bi bi-trash3"></i>'
                    + '</button>';
            }
            // Access management button (lock/unlock icon)
            if (canManageAccess) {
                const lockIcon = (node.is_restricted && parseInt(node.is_restricted)) ? 'bi-lock-fill text-warning' : 'bi-unlock';
                html += '<button class="btn btn-outline-secondary scope-node-access" '
                    + 'data-id="' + sid + '" '
                    + 'data-name="' + escAttr(node.name) + '" '
                    + 'title="<?= lang('App.scope_access_manage') ?>">'
                    + '<i class="bi ' + lockIcon + '"></i>'
                    + '</button>';
            }

            html += '</div>'; // /.scope-node-row

            // Children
            if (hasChildren) {
                html += '<div class="scope-children-wrap">' + renderScopeNodes(node.children, depth + 1) + '</div>';
            }
            html += '</div>'; // /.scope-node-wrap
        });
        return html;
    }

    function bindScopeClicks() {
        // Label click → filter
        document.querySelectorAll('.scope-node-label').forEach(function(el) {
            el.addEventListener('click', function() {
                setActiveScope(this.getAttribute('data-scope-id'));
            });
        });

        // Delete button
        document.querySelectorAll('.scope-node-delete').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id    = this.getAttribute('data-id');
                const name  = this.getAttribute('data-name');
                const count = this.getAttribute('data-count');
                confirmDeleteScope(id, name, count);
            });
        });

        // Access management button
        if (canManageAccess) {
            document.querySelectorAll('.scope-node-access').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openAccessModal(this.getAttribute('data-id'), this.getAttribute('data-name'));
                });
            });
        }
    }

    function confirmDeleteScope(id, name, count) {
        const msg = '<?= lang('App.confirm_delete_scope_with_docs', ['{name}', '{count}']) ?>'
            .replace('{name}', name).replace('{count}', count);
        Q.confirm(msg, function() {
            postJson(Q.siteUrl + '/scopes/' + id + '/delete-with-docs', {}).then(function(resp) {
                if (resp.status === 'success') {
                    Q.toast(resp.message, 'success');
                    if (activeScopeId === id) setActiveScope('');
                    loadScopeTree();
                    loadDocs(currentPage);
                } else {
                    Q.toast(resp.message || '<?= lang('App.error_occurred') ?>', 'danger');
                }
            }).catch(function() {
                Q.toast('<?= lang('App.error_occurred') ?>', 'danger');
            });
        });
    }

    function confirmDeleteAllInScope(scopeId, scopeName) {
        const msg = '<?= lang('App.confirm_delete_all_docs_in_scope', ['{name}']) ?>'
            .replace('{name}', scopeName);
        Q.confirm(msg, function() {
            const payload = { scope_id: scopeId === 'none' ? 'null' : scopeId };
            postJson(Q.siteUrl + '/documents/delete-by-scope', payload).then(function(resp) {
                if (resp.success) {
                    Q.toast(resp.message, 'success');
                    loadDocs(1);
                    loadScopeTree();
                } else {
                    Q.toast(resp.message || '<?= lang('App.error_occurred') ?>', 'danger');
                }
            });
        });
    }

    function setActiveScope(scopeId) {
        activeScopeId = scopeId || '';
        // Update sidebar active class
        document.querySelectorAll('.scope-tree-item').forEach(function(el) {
            el.classList.toggle('active', el.getAttribute('data-scope-id') === activeScopeId);
        });
        document.querySelectorAll('.scope-node-row').forEach(function(el) {
            el.classList.toggle('active', el.getAttribute('data-scope-id') === activeScopeId);
        });
        // Update upload button URL to carry the active scope
        const btnUpload = document.getElementById('btnUpload');
        if (btnUpload) {
            const base = Q.siteUrl + '/documents/upload';
            btnUpload.href = (activeScopeId && activeScopeId !== 'none')
                ? base + '?scope_id=' + encodeURIComponent(activeScopeId)
                : base;
        }
        updateActiveScopeHeader();
        loadDocs(1);
    }

    function updateActiveScopeHeader() {
        const header = document.getElementById('activeScopeHeader');
        const btnDel      = document.getElementById('btnDeleteActiveScope');
        const btnDelAll   = document.getElementById('btnDeleteAllInScope');
        const icon        = document.getElementById('activeScopeIcon');

        // "All documents" — hide header entirely
        if (activeScopeId === '') {
            header.classList.add('d-none');
            return;
        }

        // "Unscoped" section
        if (activeScopeId === 'none') {
            document.getElementById('activeScopeName').textContent = '<?= lang('App.unscoped') ?>';
            document.getElementById('activeScopeCount').textContent = '';
            if (icon) { icon.className = 'bi bi-folder-x text-secondary fs-5'; }
            if (btnDel)    btnDel.classList.add('d-none');
            if (btnDelAll) {
                btnDelAll.classList.remove('d-none');
                btnDelAll.onclick = function() { confirmDeleteAllInScope('none', '<?= lang('App.unscoped') ?>'); };
            }
            header.classList.remove('d-none');
            return;
        }

        // Real scope
        const info = scopeMap[activeScopeId];
        if (!info) { header.classList.add('d-none'); return; }

        document.getElementById('activeScopeName').textContent = info.name;
        document.getElementById('activeScopeCount').textContent = info.document_count + ' <?= lang('App.document') ?>';
        if (icon) { icon.className = 'bi bi-folder2-open text-primary fs-5'; }
        header.classList.remove('d-none');

        // Wire "delete scope + docs" button
        if (btnDel) {
            btnDel.classList.remove('d-none');
            btnDel.onclick = function() {
                confirmDeleteScope(activeScopeId, info.name, info.document_count);
            };
        }
        // Wire "delete all docs in scope" button
        if (btnDelAll) {
            btnDelAll.classList.remove('d-none');
            btnDelAll.onclick = function() { confirmDeleteAllInScope(activeScopeId, info.name); };
        }
    }

    // Fixed items: All + Unscoped
    document.getElementById('scopeItemAll').addEventListener('click', function() { setActiveScope(''); });
    document.getElementById('scopeItemNone').addEventListener('click', function() { setActiveScope('none'); });

    // Add root scope button
    if (canCreate) {
        const btnRoot = document.getElementById('btnAddRootScope');
        if (btnRoot) {
            btnRoot.addEventListener('click', function() { openScopeModal(null, ''); });
        }
    }

    // ══ SCOPE MODAL ═══════════════════════════════════════════
    function openScopeModal(id, parentId, existingName) {
        document.getElementById('scopeModalId').value = id || '';
        document.getElementById('scopeModalParentId').value = parentId || '';
        document.getElementById('scopeModalName').value = existingName || '';
        document.getElementById('scopeModalTitle').textContent = id
            ? '<?= lang('App.rename') ?>'
            : '<?= lang('App.add_scope') ?>';
        new bootstrap.Modal(document.getElementById('scopeModal')).show();
        setTimeout(function() { document.getElementById('scopeModalName').focus(); }, 300);
    }

    document.getElementById('scopeModalSave').addEventListener('click', function() {
        const id       = document.getElementById('scopeModalId').value;
        const parentId = document.getElementById('scopeModalParentId').value;
        const name     = document.getElementById('scopeModalName').value.trim();
        if (!name) return;

        let url, body;
        if (id) {
            url  = Q.siteUrl + '/scopes/' + id + '/update';
            body = { name };
        } else {
            url  = Q.siteUrl + '/scopes/create';
            body = { name, parent_id: parentId };
        }
        postJson(url, body).then(function(data) {
            if (data.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('scopeModal')).hide();
                Q.toast(data.message, 'success');
                loadScopeTree();
            } else {
                Q.toast(data.message || '<?= lang('App.error_occurred') ?>', 'danger');
            }
        });
    });

    // Enter key in modal
    document.getElementById('scopeModalName').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('scopeModalSave').click();
    });

    // ══ BULK BAR ══════════════════════════════════════════════
    // totalInScope: total docs in the current active scope (set by loadDocs)
    let totalInScope = 0;
    let selectAllScopeActive = false; // true when user chose "select all in scope"

    function updateBulkBar() {
        if (!canBulk) return;
        const bar   = document.getElementById('bulkBar');
        const count = document.getElementById('bulkCount');
        if (selected.size > 0) {
            bar.classList.remove('d-none');
            const label = selectAllScopeActive
                ? '<?= lang('App.selected_count') ?>'.replace('{0}', selected.size) + ' (<?= lang('App.select_all') ?>)'
                : '<?= lang('App.selected_count') ?>'.replace('{0}', selected.size);
            count.textContent = label;
        } else {
            bar.classList.add('d-none');
            selectAllScopeActive = false;
        }

        // "Select all in scope" button — only show when a real scope is active
        // and there are more docs than what's currently selected on this page
        const btnSelScope = document.getElementById('bulkSelectScopeBtn');
        if (btnSelScope) {
            const scopeActive = activeScopeId !== '' && totalInScope > 0;
            if (scopeActive && !selectAllScopeActive && selected.size > 0) {
                btnSelScope.classList.remove('d-none');
                document.getElementById('bulkSelectScopeLabel').textContent =
                    '<?= lang('App.select_all_in_scope') ?>'.replace('{0}', totalInScope);
            } else {
                btnSelScope.classList.add('d-none');
            }
        }

        const allCheck = document.getElementById('selectAllCheck');
        if (!allCheck) return;
        const pageBoxes = document.querySelectorAll('.doc-check');
        if (pageBoxes.length > 0 && [...pageBoxes].every(cb => selected.has(parseInt(cb.value)))) {
            allCheck.checked = true;
            allCheck.indeterminate = false;
        } else if ([...pageBoxes].some(cb => selected.has(parseInt(cb.value)))) {
            allCheck.checked = false;
            allCheck.indeterminate = true;
        } else {
            allCheck.checked = false;
            allCheck.indeterminate = false;
        }
    }

    // ══ LOAD DOCUMENTS ════════════════════════════════════════
    function loadDocs(page) {
        currentPage = page || 1;
        const params = new URLSearchParams({ page: currentPage, per_page: 15 });

        const search = document.getElementById('docSearch').value;
        const type   = document.getElementById('docTypeFilter').value;
        const court  = document.getElementById('docCourtFilter').value;

        if (search) params.set('search', search);
        if (type)   params.set('document_type', type);
        if (court)  params.set('court_level', court);
        if (activeScopeId !== '') params.set('scope_id', activeScopeId);

        Q.ajax(Q.siteUrl + '/documents/data?' + params.toString()).then(function(data) {
            const tbody = document.getElementById('docsTableBody');
            const info  = document.getElementById('docsInfo');

            if (!data || !data.items || data.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center py-4 text-muted"><?= lang('App.no_data') ?></td></tr>';
                info.textContent = '';
                totalInScope = 0;
                Q.buildPagination(document.getElementById('docsPagination'), 1, 1, loadDocs);
                return;
            }

            totalInScope = data.total;

            let html = '';
            data.items.forEach(function(doc) {
                const isChecked = selected.has(doc.id) ? 'checked' : '';
                html += '<tr>';
                if (canDelete) {
                    html += '<td><input type="checkbox" class="form-check-input doc-check" value="' + doc.id + '" ' + isChecked + '></td>';
                }
                html += '<td>' + doc.id + '</td>';
                html += '<td><a href="' + Q.siteUrl + '/documents/' + doc.id + '">' + escHtml(doc.title || '-') + '</a></td>';
                html += '<td>' + (typeLabels[doc.document_type] || doc.document_type || '-') + '</td>';
                html += '<td>' + (courtLabels[doc.court_level] || doc.court_level || '-') + '</td>';
                html += '<td>' + escHtml(doc.case_number || '-') + '</td>';
                html += '<td>' + (doc.page_count || '-') + '</td>';
                html += '<td>' + Q.formatFileSize(doc.file_size) + '</td>';
                html += '<td><small>' + Q.formatDate(doc.document_date || doc.created_at) + '</small></td>';
                html += '<td>';
                html += '<button class="btn btn-sm btn-outline-info me-1" onclick="previewDoc(' + doc.id + ')" title="<?= lang('App.preview') ?>"><i class="bi bi-eye"></i></button>';
                html += '<a href="' + Q.siteUrl + '/documents/' + doc.id + '/download" class="btn btn-sm btn-outline-success me-1" title="<?= lang('App.download') ?>"><i class="bi bi-download"></i></a>';
                if (canUpdate) {
                    html += '<a href="' + Q.siteUrl + '/documents/' + doc.id + '/edit" class="btn btn-sm btn-outline-primary me-1" title="<?= lang('App.edit') ?>"><i class="bi bi-pencil"></i></a>';
                    html += '<button class="btn btn-sm btn-outline-secondary me-1" onclick="openMoveModal(' + doc.id + ',' + escAttr(JSON.stringify(doc.title)) + ',' + (doc.scope_id || 'null') + ')" title="<?= lang('App.doc_move_title') ?>"><i class="bi bi-folder-symlink"></i></button>';
                }
                if (canDelete) {
                    html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteDoc(' + doc.id + ')" title="<?= lang('App.delete') ?>"><i class="bi bi-trash"></i></button>';
                }
                html += '</td></tr>';
            });

            tbody.innerHTML = html;

            // Render mobile card list
            renderDocCards(data.items);
            tbody.querySelectorAll('.doc-check').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    const id = parseInt(this.value);
                    if (this.checked) selected.add(id);
                    else              selected.delete(id);
                    updateBulkBar();
                });
            });
            updateBulkBar();

            const from = (data.page - 1) * data.per_page + 1;
            const to   = Math.min(data.page * data.per_page, data.total);
            info.textContent = '<?= lang('App.showing', ['{0}', '{1}', '{2}']) ?>'
                .replace('{0}', from).replace('{1}', to).replace('{2}', data.total);

            Q.buildPagination(document.getElementById('docsPagination'), data.page, data.total_pages, loadDocs);
        });
    }

    // ── Render mobile doc cards ───────────────────────────────
    function renderDocCards(items) {
        var container = document.getElementById('docCardList');
        if (!container) return;
        if (!items || items.length === 0) {
            container.innerHTML = '<div class="search-card-empty"><i class="bi bi-file-earmark-text"></i><p><?= lang('App.no_data') ?></p></div>';
            return;
        }
        var html = '';
        items.forEach(function(doc) {
            var titleStr = escHtml(doc.title || '-');
            var typeStr  = typeLabels[doc.document_type] || doc.document_type || '';
            var courtStr = courtLabels[doc.court_level]  || doc.court_level  || '';
            var dateStr  = Q.formatDate(doc.document_date || doc.created_at);
            html += '<div class="doc-card-item">';
            html += '<div class="doc-card-title">' + titleStr + '</div>';
            html += '<div class="doc-card-meta">';
            if (typeStr)  html += '<span class="badge bg-primary bg-opacity-75">' + escHtml(typeStr) + '</span>';
            if (courtStr) html += '<span class="badge bg-secondary bg-opacity-75">' + escHtml(courtStr) + '</span>';
            if (dateStr)  html += '<span><i class="bi bi-calendar3 me-1"></i>' + escHtml(dateStr) + '</span>';
            html += '</div>';
            html += '<div class="doc-card-actions">';
            html += '<button class="btn btn-sm btn-outline-info" onclick="previewDoc(' + doc.id + ')"><i class="bi bi-eye"></i> <?= lang('App.preview') ?></button>';
            html += '<a href="' + Q.siteUrl + '/documents/' + doc.id + '/download" class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i></a>';
            if (canUpdate) {
                html += '<a href="' + Q.siteUrl + '/documents/' + doc.id + '/edit" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>';
            }
            html += '</div>';
            html += '</div>';
        });
        container.innerHTML = html;
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    function escAttr(str) {
        return escHtml(str).replace(/"/g, '&quot;');
    }

    // ── postJson helper (with CSRF) ───────────────────────────
    function postJson(url, body) {
        body[Q.csrfTokenName] = Q.csrfHash;
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams(body).toString()
        }).then(function(r) { return r.json(); });
    }

    // ── Select-all ────────────────────────────────────────────
    if (canBulk) {
        const selectAllCheck = document.getElementById('selectAllCheck');
        if (selectAllCheck) {
            selectAllCheck.addEventListener('change', function() {
                const boxes = document.querySelectorAll('.doc-check');
                boxes.forEach(function(cb) {
                    const id = parseInt(cb.value);
                    if (this.checked) { cb.checked = true;  selected.add(id); }
                    else              { cb.checked = false; selected.delete(id); }
                }.bind(this));
                selectAllScopeActive = false;
                updateBulkBar();
            });
        }
    }

    if (canDelete) {
        document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
            if (selected.size === 0) { Q.toast(Q.lang.no_selection, 'warning'); return; }
            const msg = '<?= lang('App.confirm_bulk_delete') ?>'.replace('{0}', selected.size);
            Q.confirm(msg, function() {
                const ids  = Array.from(selected);
                const body = new URLSearchParams();
                ids.forEach(function(id) { body.append('ids[]', id); });
                body.append(Q.csrfTokenName, Q.csrfHash);
                fetch(Q.siteUrl + '/documents/bulk-delete', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(r => r.json()).then(function(data) {
                    if (data.success) {
                        selected.clear(); selectAllScopeActive = false; updateBulkBar();
                        Q.toast(data.message, 'success');
                        loadDocs(currentPage);
                        loadScopeTree();
                    } else {
                        Q.toast(data.message || Q.lang.error_occurred, 'danger');
                    }
                }).catch(function() { Q.toast(Q.lang.error_occurred, 'danger'); });
            });
        });
    }

    // ── "Select all in scope" button ─────────────────────────
    const btnSelScope = document.getElementById('bulkSelectScopeBtn');
    if (btnSelScope) {
        btnSelScope.addEventListener('click', function() {
            // Fetch ALL ids in the current scope/filter
            const params = new URLSearchParams({ page: 1, per_page: 999999 });
            const search = document.getElementById('docSearch').value;
            const type   = document.getElementById('docTypeFilter').value;
            const court  = document.getElementById('docCourtFilter').value;
            if (search) params.set('search', search);
            if (type)   params.set('document_type', type);
            if (court)  params.set('court_level', court);
            if (activeScopeId !== '') params.set('scope_id', activeScopeId);

            Q.ajax(Q.siteUrl + '/documents/data?' + params.toString()).then(function(data) {
                if (!data || !data.items) return;
                selected.clear();
                data.items.forEach(function(doc) { selected.add(doc.id); });
                selectAllScopeActive = true;
                // Tick visible checkboxes
                document.querySelectorAll('.doc-check').forEach(function(cb) {
                    cb.checked = selected.has(parseInt(cb.value));
                });
                updateBulkBar();
                Q.toast('<?= lang('App.selected_count') ?>'.replace('{0}', selected.size), 'success');
            });
        });
    }

    document.getElementById('bulkCancelBtn').addEventListener('click', function() {
        selected.clear();
        selectAllScopeActive = false;
        document.querySelectorAll('.doc-check').forEach(cb => { cb.checked = false; });
        updateBulkBar();
    });

    // ── Preview / Delete ──────────────────────────────────────
    window.previewDoc = function(id) {
        Q.ajax(Q.siteUrl + '/documents/' + id + '/preview').then(function(data) {
            document.getElementById('previewTitle').textContent = data.title || '<?= lang('App.preview') ?>';
            document.getElementById('previewContent').textContent = data.full_text || '';
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        });
    };

    window.deleteDoc = function(id) {
        Q.postAction(Q.siteUrl + '/documents/' + id + '/delete', function() {
            selected.delete(id);
            updateBulkBar();
            loadScopeTree(); // refresh counts
            loadDocs(currentPage);
        });
    };

    // ── Move Document to Scope ────────────────────────────────
    <?php if (in_array('documents.update', $currentPermissions)): ?>
    let moveDocId = null;
    let scopeDropdownLoaded = false;

    function loadScopeDropdown() {
        return loadScopeDropdownInto(document.getElementById('moveDocScopeSelect'));
    }

    window.openMoveModal = function(id, title, currentScopeId) {
        moveDocId = id;
        document.getElementById('moveDocName').textContent = title;
        loadScopeDropdown().then(function() {
            const sel = document.getElementById('moveDocScopeSelect');
            sel.value = currentScopeId !== null ? String(currentScopeId) : '';
            new bootstrap.Modal(document.getElementById('moveDocModal')).show();
        });
    };

    document.getElementById('moveDocSaveBtn').addEventListener('click', function() {
        if (!moveDocId) return;
        const scopeId = document.getElementById('moveDocScopeSelect').value;
        postJson(Q.siteUrl + '/documents/' + moveDocId + '/move-scope', { scope_id: scopeId })
            .then(function(data) {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('moveDocModal')).hide();
                    Q.toast(data.message, 'success');
                    loadScopeTree();
                    loadDocs(currentPage);
                } else {
                    Q.toast(data.message || '<?= lang('App.error_occurred') ?>', 'danger');
                }
            })
            .catch(function() { Q.toast('<?= lang('App.error_occurred') ?>', 'danger'); });
    });

    // ── Bulk Move to Scope ────────────────────────────────────
    const bulkMoveBtn = document.getElementById('bulkMoveBtn');
    if (bulkMoveBtn) {
        bulkMoveBtn.addEventListener('click', function() {
            if (selected.size === 0) { Q.toast('<?= lang('App.no_selection') ?>', 'warning'); return; }
            document.getElementById('bulkMoveCountLabel').textContent =
                '<?= lang('App.bulk_move_confirm') ?>'.replace('{0}', selected.size);
            loadScopeDropdownInto(document.getElementById('bulkMoveScopeSelect')).then(function() {
                new bootstrap.Modal(document.getElementById('bulkMoveModal')).show();
            });
        });
    }

    // Populate a select element with scope list (reuses cache)
    let bulkMoveScopeLoaded = false;
    function loadScopeDropdownInto(sel) {
        if (bulkMoveScopeLoaded) return Promise.resolve();
        return Q.ajax(Q.siteUrl + '/scopes/dropdown').then(function(data) {
            const first = sel.options[0];
            sel.innerHTML = '';
            sel.appendChild(first);
            (data.items || []).forEach(function(s) {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                sel.appendChild(opt);
            });
            bulkMoveScopeLoaded = true;
            // Also populate single-move dropdown if not yet loaded
            if (!scopeDropdownLoaded) {
                const singleSel = document.getElementById('moveDocScopeSelect');
                if (singleSel) {
                    const sFirst = singleSel.options[0];
                    singleSel.innerHTML = '';
                    singleSel.appendChild(sFirst);
                    (data.items || []).forEach(function(s) {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.name;
                        singleSel.appendChild(opt);
                    });
                    scopeDropdownLoaded = true;
                }
            }
        });
    }

    document.getElementById('bulkMoveSaveBtn').addEventListener('click', function() {
        if (selected.size === 0) return;
        const scopeId = document.getElementById('bulkMoveScopeSelect').value;
        const ids = Array.from(selected);
        const body = new URLSearchParams();
        ids.forEach(function(id) { body.append('ids[]', id); });
        body.append('scope_id', scopeId);
        body.append(Q.csrfTokenName, Q.csrfHash);
        fetch(Q.siteUrl + '/documents/bulk-move-scope', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(r => r.json()).then(function(data) {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('bulkMoveModal')).hide();
                selected.clear(); selectAllScopeActive = false; updateBulkBar();
                Q.toast(data.message, 'success');
                loadDocs(currentPage);
                loadScopeTree();
            } else {
                Q.toast(data.message || '<?= lang('App.error_occurred') ?>', 'danger');
            }
        }).catch(function() { Q.toast('<?= lang('App.error_occurred') ?>', 'danger'); });
    });
    <?php endif; ?>

    // ── Filters ───────────────────────────────────────────────
    let searchTimeout;
    document.getElementById('docSearch').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { loadDocs(1); }, 300);
    });
    document.getElementById('docTypeFilter').addEventListener('change', function() { loadDocs(1); });
    document.getElementById('docCourtFilter').addEventListener('change', function() { loadDocs(1); });
    document.getElementById('docClearFilters').addEventListener('click', function() {
        document.getElementById('docSearch').value = '';
        document.getElementById('docTypeFilter').value = '';
        document.getElementById('docCourtFilter').value = '';
        loadDocs(1);
    });

    // ══ SCOPE ACCESS MODAL ════════════════════════════════════
    let accessModalScopeId = null;

    function openAccessModal(scopeId, scopeName) {
        if (!canManageAccess) return;
        accessModalScopeId = scopeId;
        document.getElementById('accessModalScopeName').textContent = scopeName;

        // Reset UI
        document.getElementById('accessEntriesContainer').innerHTML =
            '<div class="text-center py-2"><span class="spinner-border spinner-border-sm"></span></div>';

        new bootstrap.Modal(document.getElementById('scopeAccessModal')).show();
        loadAccessList();
    }

    function loadAccessList() {
        if (!accessModalScopeId) return;
        Q.ajax(Q.siteUrl + '/scopes/' + accessModalScopeId + '/access').then(function(data) {
            // Update restriction toggle
            const toggle = document.getElementById('toggleRestricted');
            const label  = document.getElementById('restrictionLabel');
            const note   = document.querySelector('#restrictionToggleRow small');
            toggle.checked = !!data.scope.is_restricted;
            label.textContent = data.scope.is_restricted
                ? '<?= lang('App.scope_restricted') ?>'
                : '<?= lang('App.scope_open') ?>';
            note.textContent = data.scope.is_restricted
                ? '<?= lang('App.scope_now_restricted') ?>'
                : '<?= lang('App.scope_now_open') ?>';

            // Populate user/role dropdowns for grant form
            const userSel = document.getElementById('grantTargetUser');
            const roleSel = document.getElementById('grantTargetRole');
            userSel.innerHTML = '<option value=""><?= lang('App.select_user') ?></option>';
            roleSel.innerHTML = '<option value=""><?= lang('App.select_role') ?></option>';
            (data.users || []).forEach(function(u) {
                const o = document.createElement('option');
                o.value = u.id; o.textContent = u.full_name + ' (@' + u.username + ')';
                userSel.appendChild(o);
            });
            (data.roles || []).forEach(function(r) {
                const o = document.createElement('option');
                o.value = r.id; o.textContent = r.name;
                roleSel.appendChild(o);
            });

            // Render entries
            renderAccessEntries(data.entries || []);
        }).catch(function() {
            document.getElementById('accessEntriesContainer').innerHTML =
                '<div class="text-danger small"><?= lang('App.error_occurred') ?></div>';
        });
    }

    function renderAccessEntries(entries) {
        const container = document.getElementById('accessEntriesContainer');
        if (!entries.length) {
            container.innerHTML = '<div class="text-muted small text-center py-2"><?= lang('App.no_access_entries') ?></div>';
            return;
        }
        let html = '<table class="table table-sm table-bordered mb-0"><thead><tr>'
            + '<th><?= lang('App.grant_to') ?></th>'
            + '<th><?= lang('App.name') ?></th>'
            + '<th style="width:60px"></th>'
            + '</tr></thead><tbody>';
        entries.forEach(function(e) {
            const type = e.user_id ? '<?= lang('App.grant_to_user') ?>' : '<?= lang('App.grant_to_role') ?>';
            const name = e.user_id ? (escHtml(e.full_name) + ' <small class="text-muted">@' + escHtml(e.username) + '</small>') : escHtml(e.role_name);
            html += '<tr>'
                + '<td><span class="badge bg-secondary">' + type + '</span></td>'
                + '<td>' + name + '</td>'
                + '<td><button class="btn btn-sm btn-outline-danger revoke-btn" data-access-id="' + e.id + '">'
                + '<i class="bi bi-x"></i></button></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;

        container.querySelectorAll('.revoke-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const aid = this.getAttribute('data-access-id');
                postJson(Q.siteUrl + '/scopes/' + accessModalScopeId + '/revoke-access/' + aid, {})
                    .then(function(r) {
                        if (r.status === 'success') { Q.toast(r.message, 'success'); loadAccessList(); loadScopeTree(); }
                        else Q.toast(r.message || '<?= lang('App.error_occurred') ?>', 'danger');
                    });
            });
        });
    }

    if (canManageAccess) {
        // Restriction toggle
        document.getElementById('toggleRestricted').addEventListener('change', function() {
            postJson(Q.siteUrl + '/scopes/' + accessModalScopeId + '/set-restricted', { is_restricted: this.checked ? '1' : '0' })
                .then(function(r) {
                    if (r.status === 'success') { Q.toast(r.message, 'success'); loadAccessList(); loadScopeTree(); }
                    else Q.toast(r.message || '<?= lang('App.error_occurred') ?>', 'danger');
                });
        });

        // Grant type switcher
        document.getElementById('grantType').addEventListener('change', function() {
            const isUser = this.value === 'user';
            document.getElementById('grantTargetUser').classList.toggle('d-none', !isUser);
            document.getElementById('grantTargetRole').classList.toggle('d-none', isUser);
        });

        // Grant button
        document.getElementById('btnGrantAccess').addEventListener('click', function() {
            const type = document.getElementById('grantType').value;
            const userId = type === 'user' ? document.getElementById('grantTargetUser').value : '';
            const roleId = type === 'role' ? document.getElementById('grantTargetRole').value : '';
            if (!userId && !roleId) { Q.toast('<?= lang('App.error_occurred') ?>', 'warning'); return; }

            postJson(Q.siteUrl + '/scopes/' + accessModalScopeId + '/grant-access', { user_id: userId, role_id: roleId })
                .then(function(r) {
                    if (r.status === 'success') { Q.toast(r.message, 'success'); loadAccessList(); loadScopeTree(); }
                    else Q.toast(r.message || '<?= lang('App.error_occurred') ?>', 'danger');
                });
        });
    }

    // ── Init ──────────────────────────────────────────────────
    loadScopeTree();
    loadDocs(1);

    // ── Mobile Offcanvas: clone scope tree into offcanvas body ──
    var docsOffcanvasEl = document.getElementById('docsOffcanvas');
    if (docsOffcanvasEl) {
        docsOffcanvasEl.addEventListener('show.bs.offcanvas', function() {
            var body   = document.getElementById('docsOffcanvasBody');
            var source = document.getElementById('scopePanel');
            if (body && source) {
                body.innerHTML = source.innerHTML;
                // Wire up click handlers in cloned tree
                body.querySelectorAll('.scope-node-label').forEach(function(el) {
                    el.addEventListener('click', function() {
                        var sid = this.getAttribute('data-scope-id');
                        setActiveScope(sid);
                        bootstrap.Offcanvas.getInstance(docsOffcanvasEl).hide();
                    });
                });
                body.querySelectorAll('.scope-tree-item').forEach(function(el) {
                    el.removeAttribute('id'); // avoid duplicate IDs
                    el.style.cursor = 'pointer';
                    el.addEventListener('click', function() {
                        var sid = this.getAttribute('data-scope-id');
                        setActiveScope(sid);
                        bootstrap.Offcanvas.getInstance(docsOffcanvasEl).hide();
                    });
                });
            }
        });
    }
})();
</script>
<?= $this->endSection() ?>
