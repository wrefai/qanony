<!DOCTYPE html>
<html lang="<?= esc($locale) ?>" dir="<?= esc($direction) ?>" data-bs-theme="<?= esc($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= esc($title ?? '') ?> - <?= esc($appName) ?></title>

    <!-- PWA -->
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#1a5276">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= esc($appName) ?>">

    <?php if ($direction === 'rtl'): ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= base_url('public/assets/css/app.css') ?>" rel="stylesheet">
    <style>
        /* ── Auth page polish ── */
        body.auth-page {
            background: linear-gradient(135deg, #1a3a5c 0%, #1a5276 55%, #1a6a8a 100%) !important;
            min-height: 100dvh;
        }
        .auth-card {
            border-radius: 1rem !important;
            border: none !important;
        }
        .auth-brand-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a5276 0%, #2471a3 100%);
            color: #fff;
            font-size: 1.75rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 16px rgba(26,82,118,0.25);
        }
        .auth-card .form-control {
            font-size: 16px !important; /* prevent iOS auto-zoom on focus */
            border-radius: 0.5rem;
        }
        .auth-card .input-group-text {
            border-radius: 0.5rem 0 0 0.5rem;
            color: #1a5276;
        }
        html[dir="rtl"] .auth-card .input-group-text {
            border-radius: 0 0.5rem 0.5rem 0;
        }
        .auth-card .form-control:focus {
            border-color: #1a5276;
            box-shadow: 0 0 0 0.2rem rgba(26,82,118,0.2);
        }
        .auth-card .btn-primary {
            background: linear-gradient(135deg, #1a5276 0%, #2471a3 100%);
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            padding: 0.65rem 1.5rem;
            letter-spacing: 0.02em;
            transition: opacity 0.15s, box-shadow 0.15s;
        }
        .auth-card .btn-primary:hover {
            opacity: 0.92;
            box-shadow: 0 4px 12px rgba(26,82,118,0.35);
        }
        .auth-card .btn-primary:disabled {
            opacity: 0.65;
        }
        .auth-card .card-footer a {
            color: rgba(255,255,255,0.75);
        }
        .auth-card .card-footer {
            background: transparent !important;
        }
    </style>
</head>
<body class="auth-page d-flex align-items-center justify-content-center min-vh-100 bg-body-tertiary">

    <div class="auth-card card shadow-lg border-0" style="width: 100%; max-width: 440px;">
        <div class="card-body p-4 p-md-5">
            <!-- Logo / Brand -->
            <div class="text-center mb-4">
                <div class="auth-brand-icon mx-auto">
                    <i class="bi bi-book"></i>
                </div>
                <h4 class="fw-bold text-primary mb-1"><?= esc($appName) ?></h4>
                <p class="text-muted small mb-0"><?= esc($appSubtitle) ?></p>
            </div>

            <!-- Flash Messages -->
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
                    <ul class="mb-0">
                        <?php foreach (session()->getFlashdata('errors') as $err): ?>
                            <li><?= esc($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?= $this->renderSection('content') ?>
        </div>

        <!-- Language toggle in footer -->
        <div class="card-footer bg-transparent text-center py-3">
            <a href="<?= site_url('lang/' . $otherLocale) ?>" class="text-decoration-none">
                <i class="bi bi-translate"></i> <?= esc($otherLocaleName) ?>
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
