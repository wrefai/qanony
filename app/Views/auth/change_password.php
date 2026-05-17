<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>

<h5 class="text-center mb-4"><?= lang('App.change_password') ?></h5>

<?php if (session()->get('force_password_change')): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> <?= lang('App.force_password_change') ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= site_url('auth/change-password') ?>" autocomplete="off">
    <?= csrf_field() ?>

    <!-- Current Password -->
    <div class="mb-3">
        <label for="current_password" class="form-label"><?= lang('App.current_password') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="current_password" id="current_password" class="form-control" required autofocus>
        </div>
    </div>

    <!-- New Password -->
    <div class="mb-3">
        <label for="new_password" class="form-label"><?= lang('App.new_password') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key"></i></span>
            <input type="password" name="new_password" id="new_password" class="form-control"
                   minlength="<?= (int) env('auth.minPasswordLength', 8) ?>" required>
        </div>
        <div class="form-text"><?= lang('App.password_requirements') ?></div>
    </div>

    <!-- Confirm Password -->
    <div class="mb-3">
        <label for="confirm_password" class="form-label"><?= lang('App.confirm_password') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        </div>
    </div>

    <!-- Submit -->
    <div class="d-grid mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> <?= lang('App.save') ?>
        </button>
    </div>
</form>

<?= $this->endSection() ?>
