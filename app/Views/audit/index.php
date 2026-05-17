<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= lang('App.audit_log') ?></h4>
</div>

<!-- Filters -->
<div class="filters-bar">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small"><?= lang('App.action') ?></label>
            <select id="auditAction" class="form-select form-select-sm">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= esc($action) ?>"><?= esc($action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small"><?= lang('App.username') ?></label>
            <select id="auditUser" class="form-select form-select-sm">
                <option value=""><?= lang('App.all') ?></option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= esc($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small"><?= lang('App.date_from') ?></label>
            <input type="date" id="auditDateFrom" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small"><?= lang('App.date_to') ?></label>
            <input type="date" id="auditDateTo" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small"><?= lang('App.search') ?></label>
            <input type="text" id="auditSearch" class="form-control form-control-sm" placeholder="...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-outline-secondary w-100" id="auditClearFilters">
                <i class="bi bi-x-lg"></i> <?= lang('App.clear') ?>
            </button>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th><?= lang('App.date') ?></th>
                        <th><?= lang('App.action') ?></th>
                        <th><?= lang('App.username') ?></th>
                        <th><?= lang('App.description') ?></th>
                        <th><?= lang('App.entity') ?></th>
                        <th><?= lang('App.ip_address') ?></th>
                    </tr>
                </thead>
                <tbody id="auditTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted"><?= lang('App.loading') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div class="pagination-wrapper">
            <small class="text-muted" id="auditInfo"></small>
            <div id="auditPagination"></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function() {
    const Q = window.Qanony;
    let currentPage = 1;

    function loadAudit(page) {
        currentPage = page || 1;
        const params = new URLSearchParams({
            page: currentPage,
            per_page: 25,
        });

        const action = document.getElementById('auditAction').value;
        const userId = document.getElementById('auditUser').value;
        const dateFrom = document.getElementById('auditDateFrom').value;
        const dateTo = document.getElementById('auditDateTo').value;
        const search = document.getElementById('auditSearch').value;

        if (action) params.set('action', action);
        if (userId) params.set('user_id', userId);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (search) params.set('search', search);

        Q.ajax(Q.siteUrl + '/audit/data?' + params.toString()).then(function(data) {
            const tbody = document.getElementById('auditTableBody');
            const info = document.getElementById('auditInfo');

            if (!data || !data.items || data.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><?= lang('App.no_data') ?></td></tr>';
                info.textContent = '';
                Q.buildPagination(document.getElementById('auditPagination'), 1, 1, loadAudit);
                return;
            }

            let html = '';
            data.items.forEach(function(entry) {
                html += '<tr>';
                html += '<td><small>' + Q.formatDateTime(entry.created_at) + '</small></td>';
                html += '<td><span class="badge bg-secondary">' + escHtml(entry.action) + '</span></td>';
                html += '<td>' + escHtml(entry.username || '-') + '</td>';
                html += '<td>' + escHtml(entry.description || '') + '</td>';
                html += '<td>';
                if (entry.entity_type) {
                    html += '<small class="text-muted">' + escHtml(entry.entity_type);
                    if (entry.entity_id) html += ' #' + entry.entity_id;
                    html += '</small>';
                }
                html += '</td>';
                html += '<td><small class="text-muted">' + escHtml(entry.ip_address || '') + '</small></td>';
                html += '</tr>';
            });

            tbody.innerHTML = html;

            const from = (data.page - 1) * data.per_page + 1;
            const to = Math.min(data.page * data.per_page, data.total);
            info.textContent = '<?= lang('App.showing', ['{0}', '{1}', '{2}']) ?>'
                .replace('{0}', from).replace('{1}', to).replace('{2}', data.total);

            Q.buildPagination(document.getElementById('auditPagination'), data.page, data.total_pages, loadAudit);
        });
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Bind filter events
    ['auditAction', 'auditUser'].forEach(function(id) {
        document.getElementById(id).addEventListener('change', function() { loadAudit(1); });
    });

    ['auditDateFrom', 'auditDateTo'].forEach(function(id) {
        document.getElementById(id).addEventListener('change', function() { loadAudit(1); });
    });

    let searchTimeout;
    document.getElementById('auditSearch').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { loadAudit(1); }, 300);
    });

    document.getElementById('auditClearFilters').addEventListener('click', function() {
        document.getElementById('auditAction').value = '';
        document.getElementById('auditUser').value = '';
        document.getElementById('auditDateFrom').value = '';
        document.getElementById('auditDateTo').value = '';
        document.getElementById('auditSearch').value = '';
        loadAudit(1);
    });

    // Initial load
    loadAudit(1);
})();
</script>
<?= $this->endSection() ?>
