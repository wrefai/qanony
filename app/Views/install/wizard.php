<!DOCTYPE html>
<html lang="ar" dir="rtl" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معالج الإعداد — Qanony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            background: linear-gradient(135deg, #1a3a5c 0%, #1a5276 55%, #1a6a8a 100%);
            min-height: 100dvh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem 3rem;
            font-family: 'Segoe UI', 'Cairo', Tahoma, sans-serif;
            color: #212529;
        }

        /* ── Card ── */
        .wizard-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 40px rgba(0,0,0,0.28);
            width: 100%;
            max-width: 620px;
            overflow: hidden;
        }

        /* ── Header ── */
        .wz-header {
            background: #1a2a4a;
            color: #fff;
            padding: 1.4rem 1.6rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .wz-header-icon {
            width: 48px; height: 48px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .wz-header h1 { font-size: 1.2rem; font-weight: 700; margin: 0; line-height: 1.3; }
        .wz-header p  { font-size: 0.82rem; color: rgba(255,255,255,0.7); margin: 0; }

        /* ── Step bar ── */
        .step-bar {
            display: flex;
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 0.5rem;
        }
        .step-bar-item {
            flex: 1;
            text-align: center;
            padding: 0.6rem 0.2rem;
            font-size: 0.7rem;
            color: #9ca3af;
            border-bottom: 3px solid transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.12rem;
            transition: color 0.2s, border-color 0.2s;
        }
        .step-bar-item .sbi-num {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #d1d5db;
            color: #6b7280;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, color 0.2s;
        }
        .step-bar-item.active   { color: #1a5276; border-bottom-color: #1a5276; }
        .step-bar-item.active .sbi-num { background: #1a5276; color: #fff; }
        .step-bar-item.done     { color: #198754; border-bottom-color: #198754; }
        .step-bar-item.done .sbi-num { background: #198754; color: #fff; }
        .step-bar-item.done .sbi-num::after { content: '✓'; font-size: 0.65rem; }
        .step-bar-item.done .sbi-num span { display: none; }

        /* ── Body ── */
        .wz-body { padding: 1.5rem 1.6rem; }

        /* ── Status/result rows ── */
        .item-row {
            display: flex; align-items: flex-start; gap: 0.7rem;
            padding: 0.55rem 0.8rem;
            border-radius: 0.45rem;
            margin-bottom: 0.4rem;
            font-size: 0.85rem;
            border: 1px solid #e9ecef;
            background: #f8f9fa;
        }
        .item-row.ok    { border-color: #d1e7dd; background: #f0fdf4; color: #146c43; }
        .item-row.fail  { border-color: #f8d7da; background: #fff5f5; color: #842029; }
        .item-row .ir-icon { flex-shrink: 0; font-size: 1rem; margin-top: 0.05rem; }
        .item-row .ir-body { flex: 1; }
        .item-row .ir-label { font-weight: 600; }
        .item-row .ir-detail { font-size: 0.77rem; opacity: 0.8; margin-top: 0.1rem; word-break: break-word; }

        /* ── Alert banners ── */
        .wz-alert {
            border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem;
            font-size: 0.88rem; display: flex; align-items: flex-start; gap: 0.5rem;
        }
        .wz-alert.success { background: #d1e7dd; color: #0a3622; border: 1px solid #badbcc; }
        .wz-alert.danger  { background: #f8d7da; color: #58151c; border: 1px solid #f1aeb5; }
        .wz-alert.info    { background: #cff4fc; color: #055160; border: 1px solid #b6effb; }

        /* ── Credentials box ── */
        .creds-box {
            background: #1a2a4a; color: #fff;
            border-radius: 0.5rem; padding: 0.9rem 1rem;
            font-size: 0.85rem; margin-top: 1rem;
        }
        .creds-box .cr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; }
        .creds-box .cr:last-child { margin-bottom: 0; }
        .creds-box .cr-lbl { color: rgba(255,255,255,0.65); }
        .creds-box .cr-val { font-family: monospace; font-size: 0.9rem; }

        /* ── Form ── */
        .wz-form-group { margin-bottom: 1rem; }
        .wz-form-group label {
            display: block; font-size: 0.83rem; font-weight: 600;
            color: #374151; margin-bottom: 0.3rem;
        }
        .wz-form-group label .req { color: #dc3545; margin-right: 2px; }
        .wz-form-group input {
            width: 100%; padding: 0.45rem 0.75rem;
            border: 1px solid #d1d5db; border-radius: 0.4rem;
            font-size: 0.88rem; color: #212529;
            transition: border-color 0.15s, box-shadow 0.15s;
            background: #fff;
        }
        .wz-form-group input:focus {
            outline: none; border-color: #1a5276;
            box-shadow: 0 0 0 3px rgba(26,82,118,.15);
        }
        .wz-form-group .field-hint { font-size: 0.75rem; color: #6b7280; margin-top: 0.2rem; }
        .wz-form-row { display: flex; gap: 0.75rem; }
        .wz-form-row .wz-form-group { flex: 1; }
        .wz-form-row .wz-form-group.narrow { flex: 0 0 110px; }

        .form-section-title {
            font-size: 0.75rem; font-weight: 700; color: #6b7280;
            text-transform: uppercase; letter-spacing: .06em;
            margin: 1.2rem 0 0.7rem; padding-bottom: 0.35rem;
            border-bottom: 1px solid #e5e7eb;
        }

        /* ── Buttons ── */
        .btn-wz {
            background: #1a2a4a; color: #fff; border: none; border-radius: 0.5rem;
            padding: 0.6rem 1.6rem; font-size: 0.95rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;
            transition: background 0.15s;
        }
        .btn-wz:hover { background: #253a60; }
        .btn-wz:disabled { background: #9ca3af; cursor: not-allowed; }
        .btn-wz.green { background: #198754; }
        .btn-wz.green:hover { background: #157347; }
        .btn-wz.warn { background: #dc3545; }
        .btn-wz.warn:hover { background: #b02a37; }

        .divider { height: 1px; background: #e5e7eb; margin: 1.2rem 0; }
        .text-xs  { font-size: 0.78rem; color: #6c757d; }

        /* ── Spinner ── */
        .spin { display: inline-block; width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Step panels ── */
        .step-panel { display: none; }
        .step-panel.active { display: block; }
    </style>
</head>
<body>

<div class="wizard-card">

    <!-- Header -->
    <div class="wz-header">
        <div class="wz-header-icon"><i class="bi bi-gear-wide-connected"></i></div>
        <div>
            <h1>معالج الإعداد</h1>
            <p>تهيئة Qanony v<?= esc($appVersion) ?> خطوة بخطوة</p>
        </div>
    </div>

    <!-- Step bar (5 steps) -->
    <div class="step-bar" id="stepBar">
        <div class="step-bar-item active" data-step="1">
            <div class="sbi-num"><span>1</span></div>
            <div>المتطلبات</div>
        </div>
        <div class="step-bar-item" data-step="2">
            <div class="sbi-num"><span>2</span></div>
            <div>الإعداد</div>
        </div>
        <div class="step-bar-item" data-step="3">
            <div class="sbi-num"><span>3</span></div>
            <div>الترحيلات</div>
        </div>
        <div class="step-bar-item" data-step="4">
            <div class="sbi-num"><span>4</span></div>
            <div>حساب المدير</div>
        </div>
        <div class="step-bar-item" data-step="5">
            <div class="sbi-num"><span>5</span></div>
            <div>إنهاء</div>
        </div>
    </div>

    <div class="wz-body">

        <?php if ($isInstalled): ?>
        <div class="wz-alert info">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <strong>النظام مُثبَّت بالفعل</strong> (v<?= esc($lockVersion ?? '—') ?>).
                يمكنك إعادة تشغيل الإعداد لتطبيق أي ترحيلات جديدة، أو
                <a href="<?= site_url('auth/login') ?>" style="color:inherit;font-weight:600;">الذهاب إلى تسجيل الدخول</a>.
            </div>
        </div>
        <?php endif; ?>

        <!-- ══ Step 1: Requirements ══ -->
        <div class="step-panel active" id="panel-1">
            <h6 class="text-xs text-uppercase fw-bold mb-3" style="letter-spacing:.05em;">الخطوة 1 — فحص المتطلبات</h6>
            <p style="font-size:.88rem;color:#495057;margin-bottom:1rem;">
                نتحقق من إصدار PHP والامتدادات المطلوبة ومجلدات الكتابة.
            </p>
            <div id="req-items"></div>
            <div id="req-loading" class="d-flex align-items-center gap-2 text-xs" style="color:#1a5276;">
                <span class="spin" style="border-color:rgba(26,82,118,.3);border-top-color:#1a5276;"></span>
                جارٍ الفحص…
            </div>
            <div id="req-error" class="wz-alert danger" style="display:none;margin-top:.75rem;"></div>
            <div class="divider"></div>
            <button class="btn-wz" id="btn-next-1" style="display:none;" onclick="gotoStep(2)">
                التالي <i class="bi bi-arrow-left"></i>
            </button>
        </div>

        <!-- ══ Step 2: Config (site URL + DB credentials) ══ -->
        <div class="step-panel" id="panel-2">
            <h6 class="text-xs text-uppercase fw-bold mb-3" style="letter-spacing:.05em;">الخطوة 2 — إعدادات الموقع وقاعدة البيانات</h6>
            <p style="font-size:.88rem;color:#495057;margin-bottom:.5rem;">
                أدخل رابط الموقع وبيانات قاعدة البيانات. سيتم اختبار الاتصال وحفظ الإعدادات في ملف <code>.env</code>.
            </p>

            <div class="form-section-title"><i class="bi bi-globe2"></i> الموقع</div>

            <div class="wz-form-group">
                <label>رابط الموقع الأساسي <span class="req">*</span></label>
                <input type="url" id="cfg-site-url" placeholder="http://localhost/qanony/"
                    value="<?= esc($envValues['site_url']) ?>">
                <div class="field-hint">بدون مسار نسبي: مثل <code>https://example.com/</code> — أو <code>http://localhost/qanony/</code> محلياً</div>
            </div>

            <div class="form-section-title"><i class="bi bi-database"></i> قاعدة البيانات</div>

            <div class="wz-form-row">
                <div class="wz-form-group">
                    <label>المضيف <span class="req">*</span></label>
                    <input type="text" id="cfg-db-host" placeholder="localhost"
                        value="<?= esc($envValues['db_hostname']) ?>">
                </div>
                <div class="wz-form-group narrow">
                    <label>المنفذ</label>
                    <input type="number" id="cfg-db-port" placeholder="3306" min="1" max="65535"
                        value="<?= esc($envValues['db_port']) ?>">
                </div>
            </div>

            <div class="wz-form-group">
                <label>اسم قاعدة البيانات <span class="req">*</span></label>
                <input type="text" id="cfg-db-name" placeholder="qanony"
                    value="<?= esc($envValues['db_database']) ?>">
            </div>

            <div class="wz-form-row">
                <div class="wz-form-group">
                    <label>اسم المستخدم</label>
                    <input type="text" id="cfg-db-user" placeholder="root"
                        value="<?= esc($envValues['db_username']) ?>">
                </div>
                <div class="wz-form-group">
                    <label>كلمة المرور</label>
                    <input type="password" id="cfg-db-pass" placeholder="(فارغ إذا لم تكن محددة)"
                        value="<?= esc($envValues['db_password']) ?>">
                </div>
            </div>

            <div id="cfg-items"></div>
            <div id="cfg-error" class="wz-alert danger" style="display:none;margin-top:.75rem;"></div>
            <div class="divider"></div>
            <div class="d-flex gap-2">
                <button class="btn-wz" style="background:#6c757d;" onclick="gotoStep(1)">
                    <i class="bi bi-arrow-right"></i> السابق
                </button>
                <button class="btn-wz" id="btn-save-cfg" onclick="runConfig()">
                    <i class="bi bi-floppy-fill"></i> اختبار وحفظ
                </button>
                <button class="btn-wz green" id="btn-next-2" style="display:none;" onclick="gotoStep(3)">
                    التالي <i class="bi bi-arrow-left"></i>
                </button>
            </div>
        </div>

        <!-- ══ Step 3: Migrations ══ -->
        <div class="step-panel" id="panel-3">
            <h6 class="text-xs text-uppercase fw-bold mb-3" style="letter-spacing:.05em;">الخطوة 3 — الترحيلات والبيانات الأولية</h6>
            <p style="font-size:.88rem;color:#495057;margin-bottom:1rem;">
                سيتم إنشاء جداول قاعدة البيانات وإضافة الأدوار والصلاحيات والمستخدم الإداري الافتراضي.
            </p>
            <div id="mig-items"></div>
            <div id="mig-error" class="wz-alert danger" style="display:none;margin-top:.75rem;"></div>
            <div class="divider"></div>
            <div class="d-flex gap-2">
                <button class="btn-wz" style="background:#6c757d;" onclick="gotoStep(2)">
                    <i class="bi bi-arrow-right"></i> السابق
                </button>
                <button class="btn-wz" id="btn-run-mig" onclick="runMigrate()">
                    <i class="bi bi-database-fill-up"></i> تطبيق الترحيلات
                </button>
                <button class="btn-wz green" id="btn-next-3" style="display:none;" onclick="gotoStep(4)">
                    التالي <i class="bi bi-arrow-left"></i>
                </button>
            </div>
        </div>

        <!-- ══ Step 4: Admin account ══ -->
        <div class="step-panel" id="panel-4">
            <h6 class="text-xs text-uppercase fw-bold mb-3" style="letter-spacing:.05em;">الخطوة 4 — حساب المدير</h6>
            <p style="font-size:.88rem;color:#495057;margin-bottom:.75rem;">
                حدِّد بيانات حساب المدير الرئيسي للنظام.
            </p>

            <div class="wz-form-row">
                <div class="wz-form-group">
                    <label>اسم المستخدم <span class="req">*</span></label>
                    <input type="text" id="adm-username" value="admin" placeholder="admin">
                </div>
                <div class="wz-form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" id="adm-full-name" value="مدير النظام" placeholder="مدير النظام">
                </div>
            </div>

            <div class="wz-form-group">
                <label>البريد الإلكتروني <span class="req">*</span></label>
                <input type="email" id="adm-email" placeholder="admin@example.com">
            </div>

            <div class="wz-form-row">
                <div class="wz-form-group">
                    <label>كلمة المرور <span class="req">*</span></label>
                    <input type="password" id="adm-password" placeholder="8 أحرف على الأقل">
                </div>
                <div class="wz-form-group">
                    <label>تأكيد كلمة المرور <span class="req">*</span></label>
                    <input type="password" id="adm-password-confirm" placeholder="أعد الكتابة">
                </div>
            </div>

            <div id="adm-items"></div>
            <div id="adm-error" class="wz-alert danger" style="display:none;margin-top:.75rem;"></div>
            <div class="divider"></div>
            <div class="d-flex gap-2">
                <button class="btn-wz" style="background:#6c757d;" onclick="gotoStep(3)">
                    <i class="bi bi-arrow-right"></i> السابق
                </button>
                <button class="btn-wz" id="btn-save-adm" onclick="runAdmin()">
                    <i class="bi bi-person-check-fill"></i> حفظ حساب المدير
                </button>
                <button class="btn-wz green" id="btn-next-4" style="display:none;" onclick="gotoStep(5)">
                    التالي <i class="bi bi-arrow-left"></i>
                </button>
            </div>
        </div>

        <!-- ══ Step 5: Finalize ══ -->
        <div class="step-panel" id="panel-5">
            <h6 class="text-xs text-uppercase fw-bold mb-3" style="letter-spacing:.05em;">الخطوة 5 — إنهاء التثبيت</h6>
            <p style="font-size:.88rem;color:#495057;margin-bottom:1rem;">
                آخر خطوة: كتابة ملف القفل وإنهاء إعداد النظام.
            </p>
            <div id="fin-items"></div>
            <div id="fin-error" class="wz-alert danger" style="display:none;margin-top:.75rem;"></div>

            <!-- Success block (hidden until done) -->
            <div id="fin-success" style="display:none;">
                <div class="wz-alert success">
                    <i class="bi bi-check-circle-fill"></i>
                    <div><strong>تم التثبيت بنجاح!</strong> النظام جاهز للاستخدام.</div>
                </div>
            </div>

            <div class="divider"></div>
            <div class="d-flex gap-2">
                <button class="btn-wz" style="background:#6c757d;" id="btn-back-5" onclick="gotoStep(4)">
                    <i class="bi bi-arrow-right"></i> السابق
                </button>
                <button class="btn-wz" id="btn-finalize" onclick="runFinalize()">
                    <i class="bi bi-check2-circle"></i> إنهاء التثبيت
                </button>
                <a class="btn-wz green" id="btn-login" style="display:none;text-decoration:none;" href="<?= site_url('auth/login') ?>">
                    <i class="bi bi-box-arrow-in-right"></i> تسجيل الدخول
                </a>
            </div>
        </div>

    </div><!-- /wz-body -->
</div><!-- /wizard-card -->

<script>
(function () {
    'use strict';

    var CSRF_NAME = '<?= esc($csrfName) ?>';
    var CSRF_HASH = '<?= esc($csrfHash) ?>';
    var TOTAL_STEPS = 5;

    // ── helpers ──────────────────────────────────────────────────

    /**
     * Post to a URL with optional params object.
     * If second arg is a function, treats it as callback (no extra params).
     */
    function postJSON(url, paramsOrCallback, callback) {
        var params = null;
        if (typeof paramsOrCallback === 'function') {
            callback = paramsOrCallback;
        } else {
            params = paramsOrCallback;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        // Send CSRF token as a header so ModSecurity does not block it in the POST body
        xhr.setRequestHeader('X-CSRF-TOKEN', CSRF_HASH);
        xhr.onload = function () {
            try {
                var resp = JSON.parse(xhr.responseText);
                callback(null, resp);
            } catch (e) {
                callback(new Error('استجابة غير صالحة من الخادم. [' + xhr.status + ']'));
            }
        };
        xhr.onerror = function () { callback(new Error('فشل الاتصال بالخادم.')); };

        // Keep CSRF in body too as fallback, but also send via header above
        var body = CSRF_NAME + '=' + encodeURIComponent(CSRF_HASH);
        if (params) {
            for (var k in params) {
                if (Object.prototype.hasOwnProperty.call(params, k)) {
                    body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                }
            }
        }
        xhr.send(body);
    }

    function refreshCsrf(data) {
        if (data && data.csrf_token_name) { CSRF_NAME = data.csrf_token_name; }
        if (data && data.csrf_hash)       { CSRF_HASH = data.csrf_hash;       }
    }

    function itemRow(ok, label, detail) {
        var cls  = ok ? 'ok' : 'fail';
        var icon = ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
        var row  = document.createElement('div');
        row.className = 'item-row ' + cls;
        row.innerHTML =
            '<span class="ir-icon"><i class="bi ' + icon + '"></i></span>' +
            '<div class="ir-body">' +
                '<div class="ir-label">' + escHtml(label) + '</div>' +
                (detail ? '<div class="ir-detail">' + escHtml(detail) + '</div>' : '') +
            '</div>';
        return row;
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function setError(id, msg) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display = msg ? 'flex' : 'none';
        el.innerHTML = msg
            ? '<i class="bi bi-exclamation-triangle-fill"></i><div>' + escHtml(msg) + '</div>'
            : '';
    }

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    // ── step bar ─────────────────────────────────────────────────

    var currentStep = 1;

    window.gotoStep = function (n) {
        if (n < 1 || n > TOTAL_STEPS) return;
        document.querySelectorAll('.step-panel').forEach(function (p) {
            p.classList.remove('active');
        });
        var panel = document.getElementById('panel-' + n);
        if (panel) panel.classList.add('active');

        document.querySelectorAll('.step-bar-item').forEach(function (it) {
            var s = parseInt(it.dataset.step, 10);
            it.classList.remove('active', 'done');
            if (s < n)       it.classList.add('done');
            else if (s === n) it.classList.add('active');
        });
        currentStep = n;
    };

    // ── Step 1: Requirements (auto-run on load) ──────────────────

    (function runRequirements() {
        setError('req-error', '');
        document.getElementById('req-items').innerHTML = '';
        document.getElementById('req-loading').style.display = 'flex';

        postJSON('<?= site_url('install/step/requirements') ?>', function (err, data) {
            document.getElementById('req-loading').style.display = 'none';
            if (err) { setError('req-error', err.message); return; }
            refreshCsrf(data);

            var container = document.getElementById('req-items');
            if (data.items) {
                data.items.forEach(function (it) {
                    container.appendChild(itemRow(it.ok, it.label, it.detail));
                });
            }

            var btn = document.getElementById('btn-next-1');
            if (data.ok) {
                btn.style.display = 'inline-flex';
            } else {
                setError('req-error', data.message || 'بعض المتطلبات غير مستوفاة.');
                btn.style.display = 'inline-flex';
                btn.className = 'btn-wz warn';
                btn.innerHTML = 'المتابعة رغم الأخطاء <i class="bi bi-arrow-left"></i>';
            }
        });
    })();

    // ── Step 2: Config ───────────────────────────────────────────

    window.runConfig = function () {
        var btn = document.getElementById('btn-save-cfg');
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> جارٍ الحفظ…';
        setError('cfg-error', '');
        document.getElementById('cfg-items').innerHTML = '';
        document.getElementById('btn-next-2').style.display = 'none';

        var siteUrl = val('cfg-site-url');
        var dbHost  = val('cfg-db-host') || 'localhost';
        var dbPort  = val('cfg-db-port') || '3306';
        var dbName  = val('cfg-db-name');
        var dbUser  = val('cfg-db-user') || 'root';
        var dbPass  = document.getElementById('cfg-db-pass').value; // don't trim passwords

        if (!siteUrl) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-floppy-fill"></i> اختبار وحفظ';
            setError('cfg-error', 'رابط الموقع مطلوب.');
            return;
        }
        if (!dbName) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-floppy-fill"></i> اختبار وحفظ';
            setError('cfg-error', 'اسم قاعدة البيانات مطلوب.');
            return;
        }

        postJSON(
            '<?= site_url('install/step/config') ?>',
            { site_url: siteUrl, db_hostname: dbHost, db_port: dbPort,
              db_database: dbName, db_username: dbUser, db_password: dbPass },
            function (err, data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-floppy-fill"></i> اختبار وحفظ';
                if (err) { setError('cfg-error', err.message); return; }
                refreshCsrf(data);

                var container = document.getElementById('cfg-items');
                container.appendChild(itemRow(data.ok,
                    data.ok ? 'الإعدادات والاتصال' : 'فشل الحفظ',
                    data.message));

                if (data.ok) {
                    document.getElementById('btn-next-2').style.display = 'inline-flex';
                } else {
                    setError('cfg-error', data.message);
                }
            }
        );
    };

    // ── Step 3: Migrations ───────────────────────────────────────

    window.runMigrate = function () {
        var btn = document.getElementById('btn-run-mig');
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> جارٍ التطبيق…';
        setError('mig-error', '');
        document.getElementById('mig-items').innerHTML = '';
        document.getElementById('btn-next-3').style.display = 'none';

        postJSON('<?= site_url('install/step/migrate') ?>', function (err, data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-database-fill-up"></i> تطبيق الترحيلات';
            if (err) { setError('mig-error', err.message); return; }
            refreshCsrf(data);

            var container = document.getElementById('mig-items');
            if (data.items) {
                data.items.forEach(function (it) {
                    container.appendChild(itemRow(it.ok, it.label, it.msg));
                });
            }

            if (data.ok) {
                document.getElementById('btn-next-3').style.display = 'inline-flex';
            } else {
                setError('mig-error', data.message);
            }
        });
    };

    // ── Step 4: Admin account ────────────────────────────────────

    window.runAdmin = function () {
        var btn = document.getElementById('btn-save-adm');
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> جارٍ الحفظ…';
        setError('adm-error', '');
        document.getElementById('adm-items').innerHTML = '';
        document.getElementById('btn-next-4').style.display = 'none';

        var username  = val('adm-username');
        var fullName  = val('adm-full-name');
        var email     = val('adm-email');
        var password  = document.getElementById('adm-password').value;
        var confirm   = document.getElementById('adm-password-confirm').value;

        if (!username || !email || !password) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-person-check-fill"></i> حفظ حساب المدير';
            setError('adm-error', 'جميع الحقول المطلوبة يجب تعبئتها.');
            return;
        }
        if (password !== confirm) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-person-check-fill"></i> حفظ حساب المدير';
            setError('adm-error', 'كلمتا المرور غير متطابقتين.');
            return;
        }

        postJSON(
            '<?= site_url('install/step/admin') ?>',
            { admin_username: username, admin_full_name: fullName,
              admin_email: email, admin_password: password,
              admin_password_confirm: confirm },
            function (err, data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-person-check-fill"></i> حفظ حساب المدير';
                if (err) { setError('adm-error', err.message); return; }
                refreshCsrf(data);

                var container = document.getElementById('adm-items');
                container.appendChild(itemRow(data.ok,
                    data.ok ? 'حساب المدير' : 'فشل التحديث',
                    data.message));

                if (data.ok) {
                    document.getElementById('btn-next-4').style.display = 'inline-flex';
                } else {
                    setError('adm-error', data.message);
                }
            }
        );
    };

    // ── Step 5: Finalize ─────────────────────────────────────────

    window.runFinalize = function () {
        var btn = document.getElementById('btn-finalize');
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> جارٍ الإنهاء…';
        setError('fin-error', '');
        document.getElementById('fin-items').innerHTML = '';

        postJSON('<?= site_url('install/step/finalize') ?>', function (err, data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle"></i> إنهاء التثبيت';
            if (err) { setError('fin-error', err.message); return; }
            refreshCsrf(data);

            if (data.ok) {
                btn.style.display = 'none';
                document.getElementById('btn-back-5').style.display = 'none';
                document.getElementById('fin-success').style.display = 'block';

                var loginBtn = document.getElementById('btn-login');
                if (data.loginUrl) loginBtn.href = data.loginUrl;
                loginBtn.style.display = 'inline-flex';

                // Mark step 5 done in bar
                var item5 = document.querySelector('.step-bar-item[data-step="5"]');
                if (item5) { item5.classList.remove('active'); item5.classList.add('done'); }

                // Auto-redirect to login after 3 seconds
                var redirectUrl = data.loginUrl || loginBtn.href;
                var countdown = 3;
                var countEl = document.createElement('span');
                countEl.id = 'redirect-countdown';
                countEl.style.cssText = 'display:block;margin-top:8px;font-size:0.9em;opacity:0.75;';
                countEl.textContent = 'سيتم التحويل خلال ' + countdown + ' ثوانٍ…';
                document.getElementById('fin-success').appendChild(countEl);
                var timer = setInterval(function () {
                    countdown--;
                    if (countdown <= 0) {
                        clearInterval(timer);
                        window.location.href = redirectUrl;
                    } else {
                        countEl.textContent = 'سيتم التحويل خلال ' + countdown + ' ثوانٍ…';
                    }
                }, 1000);
            } else {
                setError('fin-error', data.message);
            }
        });
    };

})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
