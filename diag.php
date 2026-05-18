<?php
/**
 * Qanony Live-Site Diagnostic Tool
 * --------------------------------
 * Standalone — does NOT boot CodeIgniter, does NOT touch sessions.
 * Surfaces the real reason /auth/login is throwing "Whoops".
 *
 * Hit: https://stpbystp.com/q/diag.php
 *
 * DELETE THIS FILE after debugging — it leaks paths, DB creds, error
 * messages to anyone who can guess the URL.
 */

// Force display of every error, regardless of CI_ENVIRONMENT.
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: text/plain; charset=utf-8');

echo "==========================================\n";
echo " Qanony diag.php\n";
echo " Generated: " . date('Y-m-d H:i:s') . "\n";
echo "==========================================\n\n";

// ---------- 1. PHP environment ----------
echo "[1] PHP\n";
echo "    version       : " . PHP_VERSION . "\n";
echo "    sapi          : " . PHP_SAPI . "\n";
echo "    user (posix?) : " . (function_exists('posix_geteuid') ? posix_getpwuid(posix_geteuid())['name'] : 'n/a (no posix)') . "\n";
echo "    cwd           : " . getcwd() . "\n";
echo "    script        : " . __FILE__ . "\n";
echo "    opcache       : " . (function_exists('opcache_get_status') && opcache_get_status(false) ? 'ENABLED' : 'disabled') . "\n";
echo "    fastcgi_finish: " . (function_exists('fastcgi_finish_request') ? 'available' : 'NOT available') . "\n";
echo "\n";

// ---------- 2. Writable paths ----------
$paths = [
    __DIR__ . '/writable',
    __DIR__ . '/writable/cache',
    __DIR__ . '/writable/logs',
    __DIR__ . '/writable/session',
    __DIR__ . '/writable/uploads',
    __DIR__ . '/writable/uploads/documents',
    __DIR__ . '/writable/uploads/documents/original',
];
echo "[2] Writable directories\n";
foreach ($paths as $p) {
    $exists = is_dir($p);
    $perm   = $exists ? substr(sprintf('%o', fileperms($p)), -4) : '----';
    $write  = $exists ? (is_writable($p) ? 'YES' : 'NO ') : '---';
    echo "    [$perm] [w:$write] " . ($exists ? '' : '(missing) ') . $p . "\n";
}
echo "\n";

// ---------- 3. .env presence ----------
echo "[3] .env\n";
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    echo "    MISSING — no /.env on the server. Installer never completed?\n";
} else {
    echo "    found    : $envPath\n";
    echo "    size     : " . filesize($envPath) . " bytes\n";
    echo "    readable : " . (is_readable($envPath) ? 'YES' : 'NO') . "\n";
    $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($env === false) {
        echo "    parse    : FAILED (malformed .env)\n";
    } else {
        $show = ['CI_ENVIRONMENT', 'app.baseURL', 'app.indexPage', 'database.default.hostname',
                 'database.default.database', 'database.default.username', 'database.default.DBPrefix',
                 'session.driver', 'session.savePath'];
        foreach ($show as $k) {
            if (isset($env[$k])) {
                $v = $env[$k];
                if (stripos($k, 'password') !== false) $v = '***';
                echo "    $k = $v\n";
            }
        }
    }
}
echo "\n";

// ---------- 4. install.lock ----------
echo "[4] install.lock\n";
$lock = __DIR__ . '/writable/install.lock';
echo "    " . (file_exists($lock) ? "PRESENT — installer marked complete" : "absent — installer never finished") . "\n\n";

// ---------- 5. DB connectivity + ci_sessions table ----------
echo "[5] Database\n";
$env = file_exists($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];
$host = $env['database.default.hostname'] ?? 'localhost';
$name = $env['database.default.database'] ?? '';
$user = $env['database.default.username'] ?? '';
$pass = $env['database.default.password'] ?? '';
$prefix = $env['database.default.DBPrefix'] ?? '';

if (!$name || !$user) {
    echo "    SKIP — DB creds not in .env\n\n";
} else {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        echo "    connect       : OK ($host / $name)\n";

        // ci_sessions table check
        $sessTable = $prefix . 'ci_sessions';
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($sessTable));
        $exists = $stmt && $stmt->fetch();
        echo "    table         : $sessTable " . ($exists ? "EXISTS" : "MISSING") . "\n";

        if ($exists) {
            $cnt = $pdo->query("SELECT COUNT(*) FROM `$sessTable`")->fetchColumn();
            echo "    row count     : $cnt\n";

            // Try writing a test row
            try {
                $testId = 'diag_' . bin2hex(random_bytes(8));
                $ins = $pdo->prepare("INSERT INTO `$sessTable` (id, ip_address, timestamp, data) VALUES (?, ?, ?, ?)");
                $ins->execute([$testId, '127.0.0.1', time(), '']);
                echo "    write test    : OK\n";
                $pdo->prepare("DELETE FROM `$sessTable` WHERE id = ?")->execute([$testId]);
            } catch (Throwable $e) {
                echo "    write test    : FAILED — " . $e->getMessage() . "\n";
            }

            // Schema
            $cols = $pdo->query("SHOW COLUMNS FROM `$sessTable`")->fetchAll(PDO::FETCH_ASSOC);
            echo "    columns       : ";
            foreach ($cols as $c) echo $c['Field'] . '(' . $c['Type'] . ') ';
            echo "\n";
        }

        // Users table
        $userTable = $prefix . 'users';
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($userTable));
        $exists = $stmt && $stmt->fetch();
        echo "    users table   : $userTable " . ($exists ? "EXISTS" : "MISSING") . "\n";
        if ($exists) {
            $cnt = $pdo->query("SELECT COUNT(*) FROM `$userTable`")->fetchColumn();
            echo "    user count    : $cnt\n";
        }
    } catch (Throwable $e) {
        echo "    FAILED        : " . $e->getMessage() . "\n";
    }
}
echo "\n";

// ---------- 6. CI4 view cache state ----------
echo "[6] CI4 view/data cache\n";
$cacheDir = __DIR__ . '/writable/cache';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    echo "    files in cache: " . count($files) . "\n";
    foreach (array_slice($files, 0, 10) as $f) {
        echo "      " . basename($f) . " (" . filesize($f) . " bytes, " . date('Y-m-d H:i', filemtime($f)) . ")\n";
    }
}
echo "\n";

// ---------- 7. Recent CI4 error logs ----------
echo "[7] Latest CI4 log entries\n";
$logDir = __DIR__ . '/writable/logs';
if (is_dir($logDir)) {
    $logs = glob($logDir . '/log-*.log');
    if ($logs) {
        rsort($logs);
        $latest = $logs[0];
        echo "    file: " . basename($latest) . " (" . filesize($latest) . " bytes)\n";
        $lines = @file($latest);
        if ($lines) {
            $tail = array_slice($lines, -120);
            echo "    --- last 120 lines ---\n";
            foreach ($tail as $line) echo "    " . rtrim($line) . "\n";
            echo "    --- end ---\n";
        }
    } else {
        echo "    no log files yet\n";
    }
} else {
    echo "    log dir missing\n";
}
echo "\n";

// ---------- 8. Live HTTP test of /auth/login ----------
echo "[8] Live HTTP GET /auth/login (via cURL to self)\n";
$env = file_exists($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];
$baseUrl = trim(($env['app.baseURL'] ?? ''), "'\" ");
if (!$baseUrl) {
    echo "    SKIP — app.baseURL not set in .env\n";
} else {
    $target = rtrim($baseUrl, '/') . '/auth/login';
    echo "    URL: $target\n";
    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_USERAGENT      => 'qanony-diag/1.0',
    ]);
    $start = microtime(true);
    $resp  = curl_exec($ch);
    $err   = curl_error($ch);
    $info  = curl_getinfo($ch);
    $dur   = round((microtime(true) - $start) * 1000);
    curl_close($ch);

    echo "    duration: {$dur}ms\n";
    echo "    http_code: " . ($info['http_code'] ?? '?') . "\n";
    echo "    content_type: " . ($info['content_type'] ?? '?') . "\n";
    if ($err) echo "    curl error: $err\n";
    if ($resp !== false) {
        $headerSize = $info['header_size'] ?? 0;
        $headers = substr($resp, 0, $headerSize);
        $body    = substr($resp, $headerSize);
        echo "    --- response headers ---\n";
        foreach (explode("\n", $headers) as $h) {
            $h = rtrim($h);
            if ($h !== '') echo "    $h\n";
        }
        echo "    --- response body (first 4000 chars) ---\n";
        echo substr($body, 0, 4000) . "\n";
        if (strlen($body) > 4000) echo "    ... [" . (strlen($body) - 4000) . " more chars truncated]\n";
        echo "    --- end ---\n";
    }
}
echo "\n";

// ---------- 9. Boot CI4 in-process and try to render login ----------
echo "[9] In-process boot + render attempt\n";
try {
    // Force display of exceptions even in production for THIS process only.
    ini_set('display_errors', '1');
    if (!defined('FCPATH')) define('FCPATH', __DIR__ . '/public/');

    $pathsPath = __DIR__ . '/app/Config/Paths.php';
    if (!file_exists($pathsPath)) throw new RuntimeException("app/Config/Paths.php missing");
    require_once $pathsPath;
    $paths = new Config\Paths();
    require_once $paths->systemDirectory . '/Boot.php';

    // Manually init CI without running it (avoids sending headers).
    \CodeIgniter\Boot::preloadDotEnv($paths);
    \CodeIgniter\Boot::loadEnvironmentBootstrap($paths, false);

    echo "    bootstrap: OK\n";
    echo "    CI_ENVIRONMENT: " . (defined('ENVIRONMENT') ? ENVIRONMENT : '?') . "\n";

    // Try to instantiate AuthController and call login()
    $request = \Config\Services::request();
    $response = \Config\Services::response();
    $logger = \Config\Services::logger();

    $auth = new \App\Controllers\AuthController();
    $auth->initController($request, $response, $logger);
    echo "    AuthController instantiated: YES\n";

    $result = $auth->login();
    echo "    login() returned: " . (is_object($result) ? get_class($result) : gettype($result)) . "\n";
    if (is_object($result) && method_exists($result, 'getBody')) {
        $body = (string) $result->getBody();
        echo "    body length: " . strlen($body) . " chars\n";
        echo "    body preview (first 600):\n    " . substr(str_replace("\n", "\n    ", $body), 0, 600) . "\n";
    } elseif (is_string($result)) {
        echo "    body length: " . strlen($result) . " chars\n";
        echo "    body preview (first 600):\n    " . substr(str_replace("\n", "\n    ", $result), 0, 600) . "\n";
    }
} catch (Throwable $e) {
    echo "    EXCEPTION: " . get_class($e) . "\n";
    echo "    message  : " . $e->getMessage() . "\n";
    echo "    file     : " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "    --- trace ---\n";
    echo "    " . str_replace("\n", "\n    ", $e->getTraceAsString()) . "\n";
    if ($e->getPrevious()) {
        $p = $e->getPrevious();
        echo "    --- previous: " . get_class($p) . " ---\n";
        echo "    message: " . $p->getMessage() . "\n";
        echo "    file   : " . $p->getFile() . ":" . $p->getLine() . "\n";
    }
}
echo "\n";

// ---------- 10. Key files presence + mtime ----------
echo "[10] Recently-changed key files\n";
$files = [
    'app/Controllers/BaseController.php',
    'app/Controllers/AuthController.php',
    'app/Controllers/LanguageController.php',
    'app/Controllers/DocumentController.php',
    'app/Views/auth/login.php',
    'app/Views/layouts/auth.php',
    'app/Views/documents/upload.php',
    'app/Config/App.php',
    'app/Config/Session.php',
    'app/Config/Security.php',
    'app/Language/ar/App.php',
    'app/Language/ar/Validation.php',
    'app/Language/en/App.php',
];
foreach ($files as $f) {
    $p = __DIR__ . '/' . $f;
    if (file_exists($p)) {
        echo "    [" . date('Y-m-d H:i:s', filemtime($p)) . "] " . filesize($p) . "B  $f\n";
    } else {
        echo "    [MISSING                ] $f\n";
    }
}
echo "\n";

echo "==========================================\n";
echo " End of diagnostic. DELETE this file when done.\n";
echo "==========================================\n";
