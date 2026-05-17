<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= lang('App.settings') ?></h4>
</div>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= esc(session()->getFlashdata('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="post" action="<?= site_url('settings/update') ?>">
    <?= csrf_field() ?>

    <!-- ── Google Drive ─────────────────────────────────────────── -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <img src="https://www.gstatic.com/images/branding/product/1x/drive_2020q4_32dp.png"
                 width="20" height="20" alt="Google Drive">
            <strong>Google Drive</strong>
            <?php
                $gdConfigured = !empty($current['cloud.googlePickerApiKey'])
                             && !empty($current['cloud.googlePickerClientId']);
            ?>
            <span class="badge ms-auto <?= $gdConfigured ? 'bg-success' : 'bg-secondary' ?>">
                <?= $gdConfigured ? lang('App.settings_configured') : lang('App.settings_not_configured') ?>
            </span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                <?= lang('App.settings_google_help') ?>
                <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">
                    Google Cloud Console <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        API Key
                        <i class="bi bi-info-circle text-muted"
                           title="<?= lang('App.settings_google_api_key_hint') ?>"
                           data-bs-toggle="tooltip"></i>
                    </label>
                    <div class="input-group">
                        <input type="password"
                               name="cloud.googlePickerApiKey"
                               id="googlePickerApiKey"
                               class="form-control font-monospace"
                               value="<?= esc($current['cloud.googlePickerApiKey']) ?>"
                               autocomplete="off"
                               placeholder="AIza...">
                        <button type="button" class="btn btn-outline-secondary toggle-secret"
                                data-target="googlePickerApiKey">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        OAuth Client ID
                        <i class="bi bi-info-circle text-muted"
                           title="<?= lang('App.settings_google_client_id_hint') ?>"
                           data-bs-toggle="tooltip"></i>
                    </label>
                    <div class="input-group">
                        <input type="password"
                               name="cloud.googlePickerClientId"
                               id="googlePickerClientId"
                               class="form-control font-monospace"
                               value="<?= esc($current['cloud.googlePickerClientId']) ?>"
                               autocomplete="off"
                               placeholder="xxxx.apps.googleusercontent.com">
                        <button type="button" class="btn btn-outline-secondary toggle-secret"
                                data-target="googlePickerClientId">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        App ID <span class="text-muted fw-normal">(<?= lang('App.optional') ?>)</span>
                        <i class="bi bi-info-circle text-muted"
                           title="<?= lang('App.settings_google_app_id_hint') ?>"
                           data-bs-toggle="tooltip"></i>
                    </label>
                    <input type="text"
                           name="cloud.googlePickerAppId"
                           class="form-control font-monospace"
                           value="<?= esc($current['cloud.googlePickerAppId']) ?>"
                           placeholder="123456789">
                </div>
            </div>

            <?php if ($gdConfigured): ?>
            <div class="mt-3">
                <button type="button" class="btn btn-sm btn-outline-success" id="testGoogleBtn">
                    <i class="bi bi-wifi"></i> <?= lang('App.settings_test_connection') ?>
                </button>
                <span id="testGoogleResult" class="ms-2 small"></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Dropbox ──────────────────────────────────────────────── -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-dropbox text-primary fs-5"></i>
            <strong>Dropbox</strong>
            <?php $dbConfigured = !empty($current['cloud.dropboxAppKey']); ?>
            <span class="badge ms-auto <?= $dbConfigured ? 'bg-success' : 'bg-secondary' ?>">
                <?= $dbConfigured ? lang('App.settings_configured') : lang('App.settings_not_configured') ?>
            </span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                <?= lang('App.settings_dropbox_help') ?>
                <a href="https://www.dropbox.com/developers/apps" target="_blank" rel="noopener">
                    Dropbox App Console <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </p>
            <div class="col-md-6">
                <label class="form-label fw-semibold">App Key</label>
                <div class="input-group">
                    <input type="password"
                           name="cloud.dropboxAppKey"
                           id="dropboxAppKey"
                           class="form-control font-monospace"
                           value="<?= esc($current['cloud.dropboxAppKey']) ?>"
                           autocomplete="off"
                           placeholder="abc123xyz...">
                    <button type="button" class="btn btn-outline-secondary toggle-secret"
                            data-target="dropboxAppKey">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── OneDrive ─────────────────────────────────────────────── -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-microsoft text-primary fs-5"></i>
            <strong>OneDrive</strong>
            <?php $odConfigured = !empty($current['cloud.onedriveClientId']); ?>
            <span class="badge ms-auto <?= $odConfigured ? 'bg-success' : 'bg-secondary' ?>">
                <?= $odConfigured ? lang('App.settings_configured') : lang('App.settings_not_configured') ?>
            </span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                <?= lang('App.settings_onedrive_help') ?>
                <a href="https://portal.azure.com/" target="_blank" rel="noopener">
                    Azure Portal <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </p>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Client ID</label>
                <div class="input-group">
                    <input type="password"
                           name="cloud.onedriveClientId"
                           id="onedriveClientId"
                           class="form-control font-monospace"
                           value="<?= esc($current['cloud.onedriveClientId']) ?>"
                           autocomplete="off"
                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    <button type="button" class="btn btn-outline-secondary toggle-secret"
                            data-target="onedriveClientId">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Save -->
    <?php if (in_array('settings.update', $currentPermissions)): ?>
    <div class="mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> <?= lang('App.save') ?>
        </button>
    </div>
    <?php endif; ?>

</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    // Show/hide secret fields
    document.querySelectorAll('.toggle-secret').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(this.dataset.target);
            var icon  = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    });

    // Activate Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Test Google Drive connection
    var testBtn = document.getElementById('testGoogleBtn');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var result = document.getElementById('testGoogleResult');
            result.innerHTML = '<span class="text-muted"><?= lang('App.settings_testing') ?>...</span>';
            var Q = window.Qanony;
            Q.ajax(Q.siteUrl + '/search/drive-results?q=test').then(function (data) {
                if (data.not_configured) {
                    result.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> <?= lang('App.settings_test_not_configured') ?></span>';
                } else {
                    result.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> <?= lang('App.settings_test_ok') ?></span>';
                }
            }).catch(function () {
                result.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> <?= lang('App.settings_test_failed') ?></span>';
            });
        });
    }
})();
</script>
<?= $this->endSection() ?>
