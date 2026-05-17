<!DOCTYPE html>
<html lang="ar" dir="rtl" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث النظام — Qanony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            background: linear-gradient(135deg, #1a3a5c 0%, #1a5276 55%, #1a6a8a 100%);
            min-height: 100dvh;
            display: flex; align-items: flex-start; justify-content: center;
            padding: 2rem 1rem 3rem;
            font-family: 'Segoe UI', 'Cairo', Tahoma, sans-serif;
            color: #212529;
        }
        .upd-card {
            background: #fff; border-radius: 1rem;
            box-shadow: 0 8px 40px rgba(0,0,0,.28);
            width: 100%; max-width: 520px; overflow: hidden;
        }
        .upd-header {
            background: #1a2a4a; color: #fff;
            padding: 1.4rem 1.6rem 1.2rem;
            display: flex; align-items: center; gap: 1rem;
        }
        .upd-header-icon {
            width: 48px; height: 48px; background: rgba(255,255,255,.12);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .upd-header h1 { font-size: 1.2rem; font-weight: 700; margin: 0; line-height: 1.3; }
        .upd-header p  { font-size: .82rem; color: rgba(255,255,255,.7); margin: 0; }
        .upd-body { padding: 1.5rem 1.6rem; }

        .ver-row {
            display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;
        }
        .ver-box {
            flex: 1; text-align: center; padding: .8rem;
            border-radius: .5rem; border: 1px solid #dee2e6; background: #f8f9fa;
        }
        .ver-box .vb-lbl { font-size: .72rem; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .ver-box .vb-val { font-size: 1.4rem; font-weight: 700; color: #1a2a4a; font-family: monospace; }
        .ver-arrow { font-size: 1.5rem; color: #9ca3af; }

        .meta-row {
            display: flex; align-items: center; gap: .65rem;
            font-size: .82rem; color: #6c757d; margin-bottom: .4rem;
        }
        .meta-row i { font-size: .9rem; color: #1a5276; }

        .wz-alert {
            border-radius: .5rem; padding: .75rem 1rem; margin-bottom: 1rem;
            font-size: .88rem; display: flex; align-items: flex-start; gap: .5rem;
        }
        .wz-alert.success { background: #d1e7dd; color: #0a3622; border: 1px solid #badbcc; }
        .wz-alert.danger  { background: #f8d7da; color: #58151c; border: 1px solid #f1aeb5; }
        .wz-alert.info    { background: #cff4fc; color: #055160; border: 1px solid #b6effb; }
        .wz-alert.warning { background: #fff3cd; color: #664d03; border: 1px solid #ffe69c; }

        .item-row {
            display: flex; align-items: flex-start; gap: .7rem;
            padding: .55rem .8rem; border-radius: .45rem; margin-bottom: .4rem;
            font-size: .85rem; border: 1px solid #e9ecef; background: #f8f9fa;
        }
        .item-row.ok   { border-color: #d1e7dd; background: #f0fdf4; color: #146c43; }
        .item-row.fail { border-color: #f8d7da; background: #fff5f5; color: #842029; }
        .item-row .ir-icon { flex-shrink: 0; font-size: 1rem; margin-top: .05rem; }
        .item-row .ir-body { flex: 1; }
        .item-row .ir-label  { font-weight: 600; }
        .item-row .ir-detail { font-size: .77rem; opacity: .8; margin-top: .1rem; word-break: break-word; }

        .divider { height: 1px; background: #e5e7eb; margin: 1.2rem 0; }
        .text-xs { font-size: .78rem; color: #6c757d; }

        .btn-upd {
            background: #1a2a4a; color: #fff; border: none; border-radius: .5rem;
            padding: .6rem 1.6rem; font-size: .95rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: .5rem;
            transition: background .15s; text-decoration: none;
        }
        .btn-upd:hover { background: #253a60; color: #fff; }
        .btn-upd:disabled { background: #9ca3af; cursor: not-allowed; }
        .btn-upd.green { background: #198754; }
        .btn-upd.green:hover { background: #157347; }

        .spin { display: inline-block; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="upd-card">

    <!-- Header -->
    <div class="upd-header">
        <div class="upd-header-icon"><i class="bi bi-arrow-clockwise"></i></div>
        <div>
            <h1>تحديث النظام</h1>
            <p>تطبيق ترحيلات قاعدة البيانات ورفع الإصدار</p>
        </div>
    </div>

    <div class="upd-body">

        <!-- Version comparison -->
        <div class="ver-row">
            <div class="ver-box">
                <div class="vb-lbl">الإصدار المُثبَّت</div>
                <div class="vb-val">v<?= esc($installedVer) ?></div>
            </div>
            <div class="ver-arrow"><i class="bi bi-arrow-left"></i></div>
            <div class="ver-box" style="border-color:#1a5276;background:#eef4fa;">
                <div class="vb-lbl">الإصدار الجديد</div>
                <div class="vb-val" style="color:#1a5276;">v<?= esc($latestVer) ?></div>
            </div>
        </div>

        <!-- Meta info -->
        <?php if ($installedAt): ?>
        <div class="meta-row">
            <i class="bi bi-calendar-check"></i>
            تاريخ التثبيت: <strong><?= esc($installedAt) ?></strong>
        </div>
        <?php endif; ?>
        <?php if ($lastUpdatedAt && $lastUpdatedAt !== $installedAt): ?>
        <div class="meta-row">
            <i class="bi bi-clock-history"></i>
            آخر تحديث: <strong><?= esc($lastUpdatedAt) ?></strong>
        </div>
        <?php endif; ?>

        <div class="divider"></div>

        <?php if ($isUpToDate): ?>
        <!-- Already up to date -->
        <div class="wz-alert info">
            <i class="bi bi-check-circle-fill"></i>
            <div>النظام مُحدَّث ويعمل بأحدث إصدار (v<?= esc($latestVer) ?>).</div>
        </div>
        <a href="<?= site_url('auth/login') ?>" class="btn-upd green">
            <i class="bi bi-box-arrow-in-right"></i> الذهاب إلى تسجيل الدخول
        </a>
        <?php else: ?>
        <!-- Needs update -->
        <div class="wz-alert warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                يتطلب هذا الإصدار تطبيق ترحيلات قاعدة البيانات.
                انقر «تطبيق التحديث» للمتابعة.
            </div>
        </div>

        <!-- Result area -->
        <div id="upd-items"></div>
        <div id="upd-error" class="wz-alert danger" style="display:none;"></div>

        <div id="upd-success" style="display:none;">
            <div class="wz-alert success">
                <i class="bi bi-check-circle-fill"></i>
                <div><strong>تم التحديث بنجاح!</strong> النظام يعمل الآن بأحدث إصدار.</div>
            </div>
        </div>

        <button class="btn-upd" id="btn-update" onclick="runUpdate()">
            <i class="bi bi-arrow-clockwise"></i> تطبيق التحديث
        </button>
        <a href="<?= site_url('auth/login') ?>" class="btn-upd green" id="btn-login" style="display:none;text-decoration:none;margin-right:.5rem;">
            <i class="bi bi-box-arrow-in-right"></i> تسجيل الدخول
        </a>
        <?php endif; ?>

    </div>
</div>

<script>
(function () {
    'use strict';

    var CSRF_NAME = '<?= esc($csrfName) ?>';
    var CSRF_HASH = '<?= esc($csrfHash) ?>';

    function postJSON(url, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            try { callback(null, JSON.parse(xhr.responseText)); }
            catch (e) { callback(new Error('استجابة غير صالحة.')); }
        };
        xhr.onerror = function () { callback(new Error('فشل الاتصال بالخادم.')); };
        xhr.send(CSRF_NAME + '=' + encodeURIComponent(CSRF_HASH));
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function itemRow(ok, label, detail) {
        var div = document.createElement('div');
        div.className = 'item-row ' + (ok ? 'ok' : 'fail');
        div.innerHTML =
            '<span class="ir-icon"><i class="bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + '"></i></span>' +
            '<div class="ir-body"><div class="ir-label">' + escHtml(label) + '</div>' +
            (detail ? '<div class="ir-detail">' + escHtml(detail) + '</div>' : '') + '</div>';
        return div;
    }

    window.runUpdate = function () {
        var btn = document.getElementById('btn-update');
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> جارٍ التحديث…';

        var errEl   = document.getElementById('upd-error');
        var itemsEl = document.getElementById('upd-items');
        errEl.style.display   = 'none';
        itemsEl.innerHTML     = '';

        postJSON('<?= site_url('update/run') ?>', function (err, data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> تطبيق التحديث';

            if (err) {
                errEl.style.display = 'flex';
                errEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><div>' + escHtml(err.message) + '</div>';
                return;
            }

            if (data.items) {
                data.items.forEach(function (it) { itemsEl.appendChild(itemRow(it.ok, it.label, it.msg)); });
            }

            if (data.ok) {
                document.getElementById('upd-success').style.display = 'block';
                btn.style.display = 'none';
                var loginBtn = document.getElementById('btn-login');
                if (data.loginUrl) loginBtn.href = data.loginUrl;
                loginBtn.style.display = 'inline-flex';
            } else {
                errEl.style.display = 'flex';
                errEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><div>' + escHtml(data.message || 'فشل التحديث.') + '</div>';
            }
        });
    };
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
