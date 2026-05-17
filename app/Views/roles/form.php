<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= esc($title) ?></h4>
    <a href="<?= site_url('roles') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post"
              action="<?= $role ? site_url('roles/' . $role['id'] . '/update') : site_url('roles/create') ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <!-- Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label"><?= lang('App.name') ?> <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control"
                           value="<?= esc(old('name', $role['name'] ?? '')) ?>" required minlength="2" maxlength="50"
                           <?= ($role && $role['is_system']) ? 'readonly' : '' ?>>
                </div>

                <!-- Description -->
                <div class="col-md-6">
                    <label for="description" class="form-label"><?= lang('App.description') ?></label>
                    <input type="text" name="description" id="description" class="form-control"
                           value="<?= esc(old('description', $role['description'] ?? '')) ?>" maxlength="255">
                </div>
            </div>

            <!-- Permissions -->
            <h5 class="mt-4 mb-3"><?= lang('App.assign_permissions') ?></h5>

            <div class="permission-grid">
                <?php foreach ($groupedPermissions as $group => $permissions): ?>
                <div class="permission-group">
                    <div class="permission-group-title">
                        <i class="bi bi-shield-check"></i> <?= esc(ucfirst($group)) ?>
                        <button type="button" class="btn btn-sm btn-link select-all-group"
                                data-group="<?= esc($group) ?>">
                            <?= lang('App.all') ?>
                        </button>
                    </div>
                    <div class="row">
                        <?php foreach ($permissions as $perm): ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>"
                                       class="form-check-input perm-check perm-group-<?= esc($group) ?>"
                                       id="perm_<?= $perm['id'] ?>"
                                       <?= in_array($perm['id'], $rolePermissions) ? 'checked' : '' ?>>
                                <label for="perm_<?= $perm['id'] ?>" class="form-check-label">
                                    <?= esc($perm['name']) ?>
                                    <?php if ($perm['description']): ?>
                                        <i class="bi bi-info-circle text-muted" title="<?= esc($perm['description']) ?>"></i>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($restrictedScopes)): ?>
            <!-- Restricted Scope Access -->
            <hr class="mt-4">
            <h5 class="mb-1">
                <i class="bi bi-lock-fill text-warning me-1"></i>
                <?= lang('App.role_scope_access_title') ?>
            </h5>
            <p class="text-muted small mb-3"><?= lang('App.role_scope_access_hint') ?></p>
            <div class="row g-2">
                <?php foreach ($restrictedScopes as $scope): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="form-check">
                        <input type="checkbox"
                               name="scope_access[]"
                               value="<?= $scope['id'] ?>"
                               id="rscope_<?= $scope['id'] ?>"
                               class="form-check-input"
                               <?= in_array($scope['id'], $roleScopeIds) ? 'checked' : '' ?>>
                        <label for="rscope_<?= $scope['id'] ?>" class="form-check-label">
                            <i class="bi bi-shield-lock text-danger me-1"></i>
                            <?= esc($scope['name']) ?>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Submit -->
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= lang('App.save') ?>
                </button>
                <a href="<?= site_url('roles') ?>" class="btn btn-outline-secondary">
                    <?= lang('App.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all permissions in a group
    document.querySelectorAll('.select-all-group').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const group = this.getAttribute('data-group');
            const checks = document.querySelectorAll('.perm-group-' + group);
            const allChecked = Array.from(checks).every(c => c.checked);
            checks.forEach(function(c) { c.checked = !allChecked; });
        });
    });
});
</script>
<?= $this->endSection() ?>
