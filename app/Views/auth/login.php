<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>

<h5 class="text-center mb-4"><?= lang('App.login') ?></h5>

<form method="post" action="<?= site_url('auth/login') ?>" autocomplete="off" id="loginForm">
    <?= csrf_field() ?>

    <!-- Username/Email -->
    <div class="mb-3">
        <label for="login" class="form-label"><?= lang('App.username') ?> / <?= lang('App.email') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="login" id="login" class="form-control"
                   value="<?= esc(old('login')) ?>" placeholder="<?= lang('App.username') ?>"
                   required autofocus>
        </div>
    </div>

    <!-- Password -->
    <div class="mb-3">
        <label for="password" class="form-label"><?= lang('App.password') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control"
                   placeholder="<?= lang('App.password') ?>" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <!-- Submit -->
    <div class="d-grid mt-4">
        <button type="submit" class="btn btn-primary btn-lg" id="btnLoginSubmit">
            <span id="btnLoginLabel"><i class="bi bi-box-arrow-in-right"></i> <?= lang('App.login_btn') ?></span>
            <span id="btnLoginSpinner" class="d-none">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                <?= lang('App.loading') ?>
            </span>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password show/hide toggle
    var toggle = document.getElementById('togglePassword');
    var pw = document.getElementById('password');
    if (toggle && pw) {
        toggle.addEventListener('click', function() {
            var type = pw.type === 'password' ? 'text' : 'password';
            pw.type = type;
            this.querySelector('i').className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    // Submit loading state
    var form = document.getElementById('loginForm');
    var btnSubmit = document.getElementById('btnLoginSubmit');
    var btnLabel = document.getElementById('btnLoginLabel');
    var btnSpinner = document.getElementById('btnLoginSpinner');
    if (form && btnSubmit) {
        form.addEventListener('submit', function() {
            btnSubmit.disabled = true;
            btnLabel.classList.add('d-none');
            btnSpinner.classList.remove('d-none');
        });
    }
});
</script>

<?= $this->endSection() ?>
