<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= lang('App.role_management') ?></h4>
    <?php if (in_array('roles.create', $currentPermissions)): ?>
        <a href="<?= site_url('roles/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> <?= lang('App.add_role') ?>
        </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <?php foreach ($roles as $role): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <?= esc($role['name']) ?>
                    <?php if ($role['is_system']): ?>
                        <span class="badge bg-secondary ms-1"><i class="bi bi-lock"></i></span>
                    <?php endif; ?>
                </h6>
                <span class="badge bg-info"><?= $role['user_count'] ?> <?= lang('App.users') ?></span>
            </div>
            <div class="card-body">
                <?php if ($role['description']): ?>
                    <p class="text-muted mb-2"><?= esc($role['description']) ?></p>
                <?php endif; ?>

                <!-- Permission badges -->
                <div class="mb-2">
                    <?php foreach ($role['permissions'] as $perm): ?>
                        <span class="badge bg-light text-dark border me-1 mb-1"><?= esc($perm['name']) ?></span>
                    <?php endforeach; ?>
                    <?php if (empty($role['permissions'])): ?>
                        <span class="text-muted"><small><?= lang('App.no_data') ?></small></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <?php if (in_array('roles.update', $currentPermissions)): ?>
                    <a href="<?= site_url('roles/' . $role['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil"></i> <?= lang('App.edit') ?>
                    </a>
                <?php endif; ?>

                <?php if (in_array('roles.delete', $currentPermissions) && !$role['is_system']): ?>
                    <form method="post" action="<?= site_url('roles/' . $role['id'] . '/delete') ?>"
                          class="d-inline" onsubmit="return confirm('<?= lang('App.are_you_sure') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                <?= $role['user_count'] > 0 ? 'disabled title="' . lang('App.role_has_users') . '"' : '' ?>>
                            <i class="bi bi-trash"></i> <?= lang('App.delete') ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
