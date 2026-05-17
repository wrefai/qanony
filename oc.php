<?php
/**
 * OPcache Reset Utility
 *
 * Upload this file to the server root (/q/oc.php) and visit it ONCE.
 * It clears the OPcache so that newly uploaded PHP files are compiled
 * from disk instead of being served from the stale in-memory cache.
 *
 * DELETE THIS FILE after the install wizard completes successfully.
 */

// Only allow if not yet installed (no install.lock)
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$locked = file_exists(__DIR__ . '/writable/install.lock');

$resetOk    = false;
$resetMsg   = 'opcache_reset() is not available on this server.';
$statusInfo = [];

if (function_exists('opcache_reset')) {
    $resetOk  = opcache_reset();
    $resetMsg = $resetOk
        ? 'OPcache cleared successfully — all PHP files will be recompiled on next request.'
        : 'opcache_reset() returned false (may already be empty or disabled).';
}

if (function_exists('opcache_get_status')) {
    $s = @opcache_get_status(false);
    if (is_array($s)) {
        $statusInfo = [
            'enabled'        => $s['opcache_enabled'] ? 'Yes' : 'No',
            'cached_scripts' => $s['opcache_statistics']['num_cached_scripts'] ?? '?',
            'hits'           => $s['opcache_statistics']['hits'] ?? '?',
            'validate_ts'    => ini_get('opcache.validate_timestamps') ?: '(not readable)',
            'revalidate_freq'=> ini_get('opcache.revalidate_freq') ?: '(not readable)',
        ];
    }
}

if (function_exists('opcache_get_configuration')) {
    $cfg = @opcache_get_configuration();
    if (isset($cfg['directives'])) {
        $statusInfo['validate_ts_cfg']    = $cfg['directives']['opcache.validate_timestamps'] ? 'On' : 'Off';
        $statusInfo['revalidate_freq_cfg']= $cfg['directives']['opcache.revalidate_freq'] ?? '?';
    }
}

$installUrl = ($base ?: '') . '/install';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OPcache Reset</title>
<style>
  body{font-family:monospace;background:#fff;color:#222;padding:2rem;max-width:700px}
  h1{color:#1a5276}
  .ok{color:green;font-weight:bold}
  .warn{color:orange;font-weight:bold}
  .err{color:red;font-weight:bold}
  table{border-collapse:collapse;margin-top:1rem;width:100%}
  td,th{border:1px solid #ccc;padding:.4rem .8rem;text-align:left}
  th{background:#eaf2ff}
  a.btn{display:inline-block;margin-top:1.5rem;padding:.6rem 1.4rem;
        background:#1a5276;color:#fff;text-decoration:none;border-radius:4px}
</style>
</head>
<body>
<h1>OPcache Reset</h1>
<p class="<?= $resetOk ? 'ok' : 'warn' ?>"><?= htmlspecialchars($resetMsg) ?></p>

<?php if ($statusInfo): ?>
<table>
  <tr><th>Setting / Stat</th><th>Value</th></tr>
  <?php foreach ($statusInfo as $k => $v): ?>
  <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars((string)$v) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<p style="margin-top:1.5rem">
  <?php if ($locked): ?>
    <span class="warn">App is already installed (install.lock exists).</span>
  <?php else: ?>
    <span class="ok">App is not yet installed — ready to run wizard.</span>
  <?php endif; ?>
</p>

<a class="btn" href="<?= htmlspecialchars($installUrl) ?>">Go to Install Wizard &rarr;</a>

<p style="margin-top:2rem;color:#888;font-size:.85em">
  <strong>Security:</strong> Delete <code>oc.php</code> from the server after installation completes.
</p>
</body>
</html>
