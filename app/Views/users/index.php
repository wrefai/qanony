<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= lang('App.user_management') ?></h4>
    <?php if (in_array('users.create', $currentPermissions)): ?>
        <a href="<?= site_url('users/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> <?= lang('App.add_user') ?>
        </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="filters-bar">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" id="userSearch" class="form-control form-control-sm"
                   placeholder="<?= lang('App.search') ?>...">
        </div>
        <div class="col-md-3">
            <select id="userRoleFilter" class="form-select form-select-sm">
                <option value=""><?= lang('App.all') ?> <?= lang('App.roles') ?></option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>"><?= esc($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select id="userStatusFilter" class="form-select form-select-sm">
                <option value=""><?= lang('App.all') ?></option>
                <option value="1"><?= lang('App.active') ?></option>
                <option value="0"><?= lang('App.inactive') ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-sm btn-outline-secondary w-100" id="userClearFilters">
                <i class="bi bi-x-lg"></i> <?= lang('App.clear') ?>
            </button>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= lang('App.username') ?></th>
                        <th><?= lang('App.full_name') ?></th>
                        <th><?= lang('App.email') ?></th>
                        <th><?= lang('App.role') ?></th>
                        <th><?= lang('App.status') ?></th>
                        <th><?= lang('App.created_at') ?></th>
                        <th><?= lang('App.actions') ?></th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted"><?= lang('App.loading') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div class="pagination-wrapper">
            <small class="text-muted" id="usersInfo"></small>
            <div id="usersPagination"></div>
        </div>
    </div>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><?= lang('App.reset_password') ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p><?= lang('App.password_reset_done') ?></p>
                <div class="alert alert-info">
                    <strong id="tempPassword"></strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= lang('App.close') ?></button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function() {
    const Q = window.Qanony;
    let currentPage = 1;
    const canUpdate = <?= in_array('users.update', $currentPermissions) ? 'true' : 'false' ?>;
    const canDelete = <?= in_array('users.delete', $currentPermissions) ? 'true' : 'false' ?>;

    function loadUsers(page) {
        currentPage = page || 1;
        const params = new URLSearchParams({
            page: currentPage,
            per_page: 15,
        });

        const search = document.getElementById('userSearch').value;
        const role = document.getElementById('userRoleFilter').value;
        const status = document.getElementById('userStatusFilter').value;

        if (search) params.set('search', search);
        if (role) params.set('role_id', role);
        if (status !== '') params.set('status', status);

        Q.ajax(Q.siteUrl + '/users/data?' + params.toString()).then(function(data) {
            const tbody = document.getElementById('usersTableBody');
            const info = document.getElementById('usersInfo');

            if (!data || !data.items || data.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">' + Q.lang.loading.replace('...', '') + '</td></tr>';
                info.textContent = '';
                Q.buildPagination(document.getElementById('usersPagination'), 1, 1, loadUsers);
                return;
            }

            let html = '';
            data.items.forEach(function(user) {
                const statusBadge = user.is_active == 1
                    ? '<span class="badge bg-success"><?= lang('App.active') ?></span>'
                    : '<span class="badge bg-danger"><?= lang('App.inactive') ?></span>';

                html += '<tr>';
                html += '<td>' + user.id + '</td>';
                html += '<td><strong>' + escHtml(user.username) + '</strong></td>';
                html += '<td>' + escHtml(user.full_name) + '</td>';
                html += '<td>' + escHtml(user.email) + '</td>';
                html += '<td><span class="badge bg-primary">' + escHtml(user.role_name) + '</span></td>';
                html += '<td>' + statusBadge + '</td>';
                html += '<td><small>' + Q.formatDate(user.created_at) + '</small></td>';
                html += '<td>';

                if (canUpdate) {
                    html += '<a href="' + Q.siteUrl + '/users/' + user.id + '/edit" class="btn btn-sm btn-outline-primary me-1" title="<?= lang('App.edit') ?>"><i class="bi bi-pencil"></i></a>';
                    html += '<button class="btn btn-sm btn-outline-warning me-1" onclick="toggleUser(' + user.id + ')" title="<?= lang('App.toggle_status') ?>"><i class="bi bi-toggle-on"></i></button>';
                    html += '<button class="btn btn-sm btn-outline-info me-1" onclick="resetPw(' + user.id + ')" title="<?= lang('App.reset_password') ?>"><i class="bi bi-key"></i></button>';
                }
                if (canDelete) {
                    html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteUser(' + user.id + ')" title="<?= lang('App.delete') ?>"><i class="bi bi-trash"></i></button>';
                }

                html += '</td>';
                html += '</tr>';
            });

            tbody.innerHTML = html;

            const from = (data.page - 1) * data.per_page + 1;
            const to = Math.min(data.page * data.per_page, data.total);
            info.textContent = '<?= lang('App.showing', ['{0}', '{1}', '{2}']) ?>'
                .replace('{0}', from).replace('{1}', to).replace('{2}', data.total);

            Q.buildPagination(document.getElementById('usersPagination'), data.page, data.total_pages, loadUsers);
        });
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.toggleUser = function(id) {
        Q.ajax(Q.siteUrl + '/users/' + id + '/toggle-status', { method: 'POST' }).then(function(data) {
            if (data && data.success) {
                Q.toast('<?= lang('App.saved_success') ?>', 'success');
                loadUsers(currentPage);
            }
        }).catch(function(err) {
            if (err.data) Q.toast(err.data.error, 'danger');
        });
    };

    window.resetPw = function(id) {
        Q.confirm('<?= lang('App.are_you_sure') ?>', function() {
            Q.ajax(Q.siteUrl + '/users/' + id + '/reset-password', { method: 'POST' }).then(function(data) {
                if (data && data.success) {
                    document.getElementById('tempPassword').textContent = data.temp_password;
                    new bootstrap.Modal(document.getElementById('passwordModal')).show();
                }
            });
        });
    };

    window.deleteUser = function(id) {
        Q.postAction(Q.siteUrl + '/users/' + id + '/delete', function() {
            loadUsers(currentPage);
        });
    };

    // Debounced search
    let searchTimeout;
    document.getElementById('userSearch').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { loadUsers(1); }, 300);
    });

    document.getElementById('userRoleFilter').addEventListener('change', function() { loadUsers(1); });
    document.getElementById('userStatusFilter').addEventListener('change', function() { loadUsers(1); });
    document.getElementById('userClearFilters').addEventListener('click', function() {
        document.getElementById('userSearch').value = '';
        document.getElementById('userRoleFilter').value = '';
        document.getElementById('userStatusFilter').value = '';
        loadUsers(1);
    });

    // Initial load
    loadUsers(1);
})();
</script>
<?= $this->endSection() ?>
