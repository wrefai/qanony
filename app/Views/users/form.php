<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= esc($title) ?></h4>
    <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> <?= lang('App.back') ?>
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post"
              action="<?= $user ? site_url('users/' . $user['id'] . '/update') : site_url('users/create') ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <!-- Username -->
                <div class="col-md-6">
                    <label for="username" class="form-label"><?= lang('App.username') ?> <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control"
                           value="<?= esc(old('username', $user['username'] ?? '')) ?>" required minlength="3" maxlength="50">
                </div>

                <!-- Email -->
                <div class="col-md-6">
                    <label for="email" class="form-label"><?= lang('App.email') ?> <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control"
                           value="<?= esc(old('email', $user['email'] ?? '')) ?>" required>
                </div>

                <!-- Full Name -->
                <div class="col-md-6">
                    <label for="full_name" class="form-label"><?= lang('App.full_name') ?> <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control"
                           value="<?= esc(old('full_name', $user['full_name'] ?? '')) ?>" required minlength="2" maxlength="150">
                </div>

                <!-- Role -->
                <div class="col-md-6">
                    <label for="role_id" class="form-label"><?= lang('App.role') ?> <span class="text-danger">*</span></label>
                    <select name="role_id" id="role_id" class="form-select" required>
                        <option value=""><?= lang('App.all') ?></option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"
                                <?= old('role_id', $user['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                <?= esc($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Password (only for create) -->
                <?php if (!$user): ?>
                <div class="col-md-6">
                    <label for="password" class="form-label"><?= lang('App.password') ?> <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="password" class="form-control"
                           required minlength="<?= env('auth.minPasswordLength', 8) ?>">
                    <div class="form-text"><?= lang('App.password_requirements') ?></div>
                </div>
                <?php endif; ?>

                <!-- Active Status -->
                <div class="col-md-6">
                    <label class="form-label"><?= lang('App.status') ?></label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
                            <?= old('is_active', $user['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active" class="form-check-label"><?= lang('App.active') ?></label>
                    </div>
                </div>
            </div>

            <?php if ($user && !empty($restrictedScopes)): ?>
            <!-- Restricted Scope Access -->
            <div class="col-12 mt-2">
                <hr>
                <h6 class="mb-1">
                    <i class="bi bi-lock-fill text-warning me-1"></i>
                    <?= lang('App.user_scope_access_title') ?>
                </h6>
                <p class="text-muted small mb-3"><?= lang('App.user_scope_access_hint') ?></p>
                <div class="row g-2">
                    <?php foreach ($restrictedScopes as $scope): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check">
                            <input type="checkbox"
                                   name="scope_access[]"
                                   value="<?= $scope['id'] ?>"
                                   id="scope_<?= $scope['id'] ?>"
                                   class="form-check-input"
                                   <?= in_array($scope['id'], $userScopeIds) ? 'checked' : '' ?>>
                            <label for="scope_<?= $scope['id'] ?>" class="form-check-label">
                                <i class="bi bi-shield-lock text-danger me-1"></i>
                                <?= esc($scope['name']) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Submit -->
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= lang('App.save') ?>
                </button>
                <a href="<?= site_url('users') ?>" class="btn btn-outline-secondary">
                    <?= lang('App.cancel') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
