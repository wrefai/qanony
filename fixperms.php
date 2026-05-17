<?php
/**
 * fixperms.php — One-time permission fixer for cPanel shared hosting
 *
 * Upload this file to the project root, visit it once in a browser,
 * then it deletes itself. Never leave this file on a production server.
 *
 * Sets: directories → 0755, files → 0644, writable/ dirs → 0775
 */

// Basic security: only allow from browser, not a blind scan
if (PHP_SAPI === 'cli') {
    exit("Run this via browser, not CLI.\n");
}

$root = __DIR__;

$targets = [
    'vendor'   => ['dir' => 0755, 'file' => 0644],
    'app'      => ['dir' => 0755, 'file' => 0644],
    'public'   => ['dir' => 0755, 'file' => 0644],
    'writable' => ['dir' => 0775, 'file' => 0664],
];

$results = [];

foreach ($targets as $folder => $perms) {
    $path = $root . DIRECTORY_SEPARATOR . $folder;
    if (! is_dir($path)) {
        $results[$folder] = 'SKIP (not found)';
        continue;
    }

    $count  = 0;
    $errors = 0;

    // Fix the top-level dir first so the iterator can open it
    if (! chmod($path, $perms['dir'])) {
        $errors++;
    }

    try {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            $mode = $item->isDir() ? $perms['dir'] : $perms['file'];
            if (chmod($item->getPathname(), $mode)) {
                $count++;
            } else {
                $errors++;
            }
        }
    } catch (Throwable $e) {
        $results[$folder] = 'ERROR: ' . $e->getMessage();
        continue;
    }

    $results[$folder] = "OK — {$count} items fixed" . ($errors ? ", {$errors} errors" : '');
}

// Also fix index.php and .htaccess in root
foreach (['.htaccess', 'index.php', 'spark'] as $f) {
    $fp = $root . DIRECTORY_SEPARATOR . $f;
    if (file_exists($fp)) {
        chmod($fp, 0644);
    }
}

// Self-delete
$selfDeleted = unlink(__FILE__);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>fixperms — Qanony</title>
<style>
  body { font-family: monospace; background: #1a1a2e; color: #eee; padding: 2rem; }
  h1   { color: #e94560; }
  table { border-collapse: collapse; width: 100%; max-width: 600px; }
  td, th { padding: 8px 12px; border: 1px solid #444; text-align: left; }
  th { background: #16213e; }
  .ok   { color: #4ecca3; }
  .skip { color: #aaa; }
  .err  { color: #e94560; }
  .next { margin-top: 2rem; background: #16213e; padding: 1rem; border-radius: 6px; }
  a { color: #4ecca3; }
</style>
</head>
<body>
<h1>Qanony — Permission Fix</h1>
<table>
  <tr><th>Folder</th><th>Result</th></tr>
  <?php foreach ($results as $folder => $result): ?>
  <tr>
    <td><?= htmlspecialchars($folder) ?></td>
    <td class="<?= strpos($result, 'OK') === 0 ? 'ok' : (strpos($result, 'SKIP') === 0 ? 'skip' : 'err') ?>">
      <?= htmlspecialchars($result) ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<div class="next">
  <?php if ($selfDeleted): ?>
    <p class="ok">✓ This script has deleted itself (secure).</p>
  <?php else: ?>
    <p class="err">⚠ Could not self-delete. Please delete fixperms.php manually from File Manager.</p>
  <?php endif; ?>
  <p>→ <a href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' ?>">Go to the install wizard</a></p>
</div>
</body>
</html>
