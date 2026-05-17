<!DOCTYPE html>
<html lang="<?= esc($locale) ?>" dir="<?= esc($direction) ?>" data-bs-theme="<?= esc($theme) ?>"<?= !empty($forceLight) ? ' data-force-light="1"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="<?= esc($csrf_token_name) ?>" content="<?= esc($csrf_hash) ?>">
    <title><?= esc($title ?? '') ?> - <?= esc($appName) ?></title>

    <!-- No-FOUC: apply saved theme instantly before CSS paints -->
    <script>(function(){try{var t=localStorage.getItem('qn-theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-bs-theme',t);}catch(e){}})();</script>

    <!-- PWA -->
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#1a5276">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= esc($appName) ?>">

    <!-- Bootstrap 5 RTL/LTR -->
    <?php if ($direction === 'rtl'): ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- App CSS (includes self-hosted Arabic fonts: Cairo + Noto Naskh Arabic) -->
    <link href="<?= base_url('public/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand fw-bold" href="<?= site_url('dashboard') ?>">
                <i class="bi bi-book"></i>
                <?= esc($appName) ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <!-- Main Nav Links -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('dashboard*') ? 'active' : '' ?>" href="<?= site_url('dashboard') ?>">
                            <i class="bi bi-speedometer2"></i> <?= lang('App.dashboard') ?>
                        </a>
                    </li>

                    <?php if (in_array('documents.read', $currentPermissions)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('documents*') ? 'active' : '' ?>" href="<?= site_url('documents') ?>">
                            <i class="bi bi-file-earmark-text"></i> <?= lang('App.documents') ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array('search.use', $currentPermissions)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('search*') ? 'active' : '' ?>" href="<?= site_url('search') ?>">
                            <i class="bi bi-search"></i> <?= lang('App.search') ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array('users.read', $currentPermissions)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('users*') ? 'active' : '' ?>" href="<?= site_url('users') ?>">
                            <i class="bi bi-people"></i> <?= lang('App.users') ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array('roles.read', $currentPermissions)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('roles*') ? 'active' : '' ?>" href="<?= site_url('roles') ?>">
                            <i class="bi bi-shield-lock"></i> <?= lang('App.roles') ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array('audit.read', $currentPermissions)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('audit*') ? 'active' : '' ?>" href="<?= site_url('audit') ?>">
                            <i class="bi bi-journal-text"></i> <?= lang('App.audit_logs') ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array('documents.create', $currentPermissions)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('queue*') ? 'active' : '' ?>" href="<?= site_url('queue') ?>">
                            <i class="bi bi-layers"></i> <?= lang('App.queue_monitor') ?>
                            <?php
                            try {
                                $qPending = (new \App\Models\UploadQueueModel())->where('status', 'pending')->countAllResults();
                                if ($qPending > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= $qPending ?></span>
                            <?php endif;
                            } catch (\Throwable $e) { /* table may not exist in test env */ }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array('settings.read', $currentPermissions)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= url_is('settings*') ? 'active' : '' ?>" href="<?= site_url('settings') ?>">
                            <i class="bi bi-gear"></i> <?= lang('App.settings') ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Right side: Language, Theme, User -->
                <ul class="navbar-nav">
                    <!-- Language Toggle -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= site_url('lang/' . $otherLocale) ?>" title="<?= esc($otherLocaleName) ?>">
                            <i class="bi bi-translate"></i> <?= esc($otherLocaleName) ?>
                        </a>
                    </li>

                    <!-- Theme Toggle -->
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="themeToggle" title="<?= lang('App.theme') ?>">
                            <i class="bi bi-<?= $theme === 'dark' ? 'sun' : 'moon-stars' ?>"></i>
                        </a>
                    </li>

                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?= esc($currentUser['full_name'] ?? $currentUser['username'] ?? '') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <span class="dropdown-item-text text-muted">
                                    <small><?= esc($currentUser['role_name'] ?? '') ?></small>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?= site_url('auth/change-password') ?>">
                                    <i class="bi bi-key"></i> <?= lang('App.change_password') ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= site_url('auth/logout') ?>">
                                    <i class="bi bi-box-arrow-right"></i> <?= lang('App.logout') ?>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container-fluid mt-2">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= esc(session()->getFlashdata('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= esc(session()->getFlashdata('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <ul class="mb-0">
                    <?php foreach (session()->getFlashdata('errors') as $err): ?>
                        <li><?= esc($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <main class="container-fluid py-3 flex-grow-1">
        <?= $this->renderSection('content') ?>
    </main>

    <!-- Footer -->
    <footer class="bg-body-tertiary border-top py-3 mt-auto">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">
                    &copy; <?= date('Y') ?> <?= esc($appName) ?> &mdash; <?= esc($appSubtitle) ?>
                </span>
                <span class="text-muted">
                    <small><?= lang('App.powered_by') ?> CodeIgniter <?= \CodeIgniter\CodeIgniter::CI_VERSION ?></small>
                </span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App JS -->
    <script>
        // Global config available to app.js
        window.Qanony = {
            baseUrl: '<?= rtrim(base_url(), '/') ?>',
            siteUrl: '<?= rtrim(site_url(), '/') ?>',
            csrfTokenName: '<?= esc($csrf_token_name) ?>',
            csrfHash: '<?= esc($csrf_hash) ?>',
            locale: '<?= esc($locale) ?>',
            direction: '<?= esc($direction) ?>',
            theme: '<?= esc($theme) ?>',
            lang: {
                are_you_sure: '<?= lang('App.are_you_sure') ?>',
                yes_delete: '<?= lang('App.yes_delete') ?>',
                cancel: '<?= lang('App.cancel') ?>',
                loading: '<?= lang('App.loading') ?>',
                error_occurred: '<?= lang('App.error_occurred') ?>',
                confirm: '<?= lang('App.confirm') ?>',
                selected_count: '<?= lang('App.selected_count') ?>',
                delete_selected: '<?= lang('App.delete_selected') ?>',
                confirm_bulk_delete: '<?= lang('App.confirm_bulk_delete') ?>',
                bulk_delete_success: '<?= lang('App.bulk_delete_success') ?>',
                select_all: '<?= lang('App.select_all') ?>',
                no_selection: '<?= lang('App.no_selection') ?>',
            }
        };
    </script>
    <script src="<?= base_url('public/assets/js/app.js') ?>"></script>

    <!-- PWA Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= base_url('sw.js') ?>');
            });
        }
    </script>

    <!-- Page-specific scripts -->
    <?= $this->renderSection('scripts') ?>
</body>
</html>
