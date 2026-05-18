<?php
/**
 * Qanony — One-Click cPanel Installer
 * ─────────────────────────────────────────────────────────────────────
 * Place this file together with qanony.zip in the target directory
 * (e.g. public_html/q/) and visit it in your browser:
 *
 *     https://yourdomain.com/q/setup.php
 *
 * It will:
 *   1. Verify PHP version & required extensions
 *   2. Extract qanony.zip into this directory
 *   3. Recursively chmod files (0644) and directories (0755),
 *      and writable/ subtree (0775)
 *   4. Create writable/ subdirectories if missing
 *   5. Copy .env.example → .env (if no .env exists)
 *   6. Delete qanony.zip and this setup.php
 *   7. Redirect to ./install (the wizard)
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

// ── 0. Hardening ───────────────────────────────────────────────────
@set_time_limit(300);
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

$here = __DIR__;
$zip  = $here . DIRECTORY_SEPARATOR . 'qanony.zip';
$lock = $here . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'install.lock';

// Trigger the actual work only on POST; show a confirmation page on GET.
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';

if ($action !== 'run') {
    render_landing($zip);
    exit;
}

// ── 1. PHP version check ───────────────────────────────────────────
$logs = [];
$ok   = true;

$step = function (bool $cond, string $okMsg, string $failMsg) use (&$logs, &$ok): bool {
    if ($cond) {
        $logs[] = ['ok' => true, 'msg' => $okMsg];
        return true;
    }
    $logs[] = ['ok' => false, 'msg' => $failMsg];
    $ok = false;
    return false;
};

$step(
    version_compare(PHP_VERSION, '8.0', '>='),
    'PHP ' . PHP_VERSION . ' (OK, ≥ 8.0)',
    'PHP ' . PHP_VERSION . ' is too old. Requires PHP ≥ 8.0.'
);

$required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'xml', 'fileinfo', 'intl', 'zip'];
foreach ($required as $ext) {
    $step(
        extension_loaded($ext),
        "PHP extension '{$ext}' loaded",
        "Missing required PHP extension: '{$ext}'"
    );
}

// ── 2. ZIP present ─────────────────────────────────────────────────
$step(
    is_file($zip),
    'Found qanony.zip',
    'qanony.zip not found in ' . $here
);

if (! $ok) {
    render_result($logs, false, null);
    exit;
}

// ── 3. Extract ZIP ─────────────────────────────────────────────────
try {
    $zipObj = new ZipArchive();
    $res = $zipObj->open($zip);
    if ($res !== true) {
        throw new RuntimeException('Failed to open qanony.zip (code ' . $res . ')');
    }
    if (! $zipObj->extractTo($here)) {
        $zipObj->close();
        throw new RuntimeException('extractTo() returned false — check write permissions on ' . $here);
    }
    $count = $zipObj->numFiles;
    $zipObj->close();
    $logs[] = ['ok' => true, 'msg' => "Extracted {$count} entries from qanony.zip"];
} catch (Throwable $e) {
    $logs[] = ['ok' => false, 'msg' => 'Extract failed: ' . $e->getMessage()];
    render_result($logs, false, null);
    exit;
}

// ── 4. Ensure writable/ subdirs exist ──────────────────────────────
$writableDirs = [
    'writable',
    'writable/cache',
    'writable/logs',
    'writable/session',
    'writable/uploads',
    'writable/uploads/documents',
    'writable/uploads/documents/original',
    'writable/uploads/documents/converted',
    'writable/uploads/documents/extracted',
];

foreach ($writableDirs as $rel) {
    $dir = $here . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (! is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ph = $dir . DIRECTORY_SEPARATOR . 'index.html';
    if (! file_exists($ph)) {
        @file_put_contents($ph, '<!DOCTYPE html><html><body></body></html>');
    }
}
$logs[] = ['ok' => true, 'msg' => 'Writable directories ensured'];

// ── 5. Recursive chmod ─────────────────────────────────────────────
$fixed = 0;
$failed = 0;

// Chmod the project root itself
@chmod($here, 0755);

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($here, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$writablePrefix = $here . DIRECTORY_SEPARATOR . 'writable';

foreach ($iter as $item) {
    /** @var SplFileInfo $item */
    $path = $item->getPathname();
    $isWritable = str_starts_with($path, $writablePrefix);
    $mode = $item->isDir()
        ? ($isWritable ? 0775 : 0755)
        : ($isWritable ? 0664 : 0644);
    if (@chmod($path, $mode)) {
        $fixed++;
    } else {
        $failed++;
    }
}
$logs[] = ['ok' => true, 'msg' => "chmod applied to {$fixed} entries" . ($failed ? " ({$failed} failed)" : '')];

// ── 6. Create .env if missing ──────────────────────────────────────
$envPath     = $here . DIRECTORY_SEPARATOR . '.env';
$envExample  = $here . DIRECTORY_SEPARATOR . '.env.example';

if (! file_exists($envPath)) {
    if (file_exists($envExample)) {
        if (@copy($envExample, $envPath)) {
            @chmod($envPath, 0644);
            $logs[] = ['ok' => true, 'msg' => 'Created .env from .env.example'];
        } else {
            $logs[] = ['ok' => false, 'msg' => 'Failed to copy .env.example to .env'];
        }
    } else {
        // Minimal stub so DotEnv has something to read; wizard will overwrite it.
        @file_put_contents($envPath, "CI_ENVIRONMENT = development\n");
        @chmod($envPath, 0644);
        $logs[] = ['ok' => true, 'msg' => 'Created minimal .env stub'];
    }
} else {
    $logs[] = ['ok' => true, 'msg' => '.env already exists — left untouched'];
}

// ── 7. Self-cleanup ────────────────────────────────────────────────
@unlink($zip);
$logs[] = ['ok' => true, 'msg' => 'Removed qanony.zip'];

$selfPath = __FILE__;
// Schedule self-deletion AFTER response is sent
register_shutdown_function(static function () use ($selfPath): void {
    @unlink($selfPath);
});

// ── 8. Determine target URL for redirect ──────────────────────────
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/setup.php'), '/\\');
$installUrl = $proto . '://' . $host . $baseDir . '/install';

render_result($logs, true, $installUrl);
exit;


// ───────────────────────────────────────────────────────────────────
// Views
// ───────────────────────────────────────────────────────────────────

function render_landing(string $zip): void
{
    $hasZip = is_file($zip);
    $phpOk  = version_compare(PHP_VERSION, '8.0', '>=');
    ?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Qanony Setup</title>
<style>
    body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; max-width: 720px; margin: 3rem auto; padding: 0 1.5rem; color: #222; line-height: 1.55; }
    h1 { font-weight: 600; margin-bottom: 0.25rem; }
    .sub { color: #666; margin-bottom: 2rem; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; }
    .ok { color: #197a3d; }
    .err { color: #b00020; }
    .row { display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid #f0f0f0; }
    .row:last-child { border-bottom: 0; }
    button { background: #1a73e8; color: #fff; border: 0; padding: 0.75rem 1.75rem; border-radius: 6px; font-size: 1rem; cursor: pointer; font-weight: 500; }
    button:disabled { background: #aaa; cursor: not-allowed; }
    code { background: #f4f4f4; padding: 0.1rem 0.35rem; border-radius: 3px; font-size: 0.9em; }
</style>
</head>
<body>
<h1>Qanony Setup</h1>
<p class="sub">One-click installer for cPanel shared hosting</p>

<div class="card">
    <div class="row"><span>PHP version</span><span class="<?= $phpOk ? 'ok' : 'err' ?>"><?= htmlspecialchars(PHP_VERSION) ?> <?= $phpOk ? '✓' : '✗ (need ≥ 8.0)' ?></span></div>
    <div class="row"><span>qanony.zip present</span><span class="<?= $hasZip ? 'ok' : 'err' ?>"><?= $hasZip ? '✓ found' : '✗ missing' ?></span></div>
    <div class="row"><span>ZipArchive extension</span><span class="<?= extension_loaded('zip') ? 'ok' : 'err' ?>"><?= extension_loaded('zip') ? '✓ loaded' : '✗ missing' ?></span></div>
</div>

<?php if ($hasZip && $phpOk && extension_loaded('zip')): ?>
<form method="post">
    <input type="hidden" name="action" value="run">
    <p>Click the button below to extract the application, fix permissions and create the configuration file.</p>
    <button type="submit">Install Qanony</button>
</form>
<?php else: ?>
<div class="card err">
    <strong>Cannot proceed.</strong> Make sure <code>qanony.zip</code> is in the same folder as <code>setup.php</code>,
    PHP is ≥ 8.0, and the <code>zip</code> extension is enabled.
</div>
<?php endif; ?>

</body>
</html><?php
}

function render_result(array $logs, bool $ok, ?string $installUrl): void
{
    ?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Qanony Setup — <?= $ok ? 'Done' : 'Error' ?></title>
<?php if ($ok && $installUrl): ?>
<meta http-equiv="refresh" content="3;url=<?= htmlspecialchars($installUrl, ENT_QUOTES) ?>">
<?php endif; ?>
<style>
    body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; max-width: 720px; margin: 3rem auto; padding: 0 1.5rem; color: #222; line-height: 1.55; }
    h1 { font-weight: 600; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .ok { color: #197a3d; }
    .err { color: #b00020; }
    .log { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 0.88rem; }
    .log li { padding: 0.2rem 0; }
    a.btn { display: inline-block; background: #1a73e8; color: #fff; text-decoration: none; padding: 0.7rem 1.5rem; border-radius: 6px; font-weight: 500; }
</style>
</head>
<body>
<h1><?= $ok ? 'Installation Prepared ✓' : 'Setup Error ✗' ?></h1>

<div class="card">
    <ul class="log" style="list-style:none;padding:0;margin:0;">
    <?php foreach ($logs as $l): ?>
        <li class="<?= $l['ok'] ? 'ok' : 'err' ?>">
            <?= $l['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($l['msg']) ?>
        </li>
    <?php endforeach; ?>
    </ul>
</div>

<?php if ($ok && $installUrl): ?>
<p>Redirecting to the installation wizard in 3 seconds…</p>
<p><a class="btn" href="<?= htmlspecialchars($installUrl, ENT_QUOTES) ?>">Open install wizard now →</a></p>
<?php else: ?>
<p class="err">Setup could not complete. Review the log above and fix the indicated issues.</p>
<?php endif; ?>

</body>
</html><?php
}
