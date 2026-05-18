<?php
/**
 * Qanony — WordPress-Style Single-Screen Installer
 * ─────────────────────────────────────────────────────────────────────
 *
 * Workflow
 * ────────
 *   GET  /installer.php   → render the one-page setup form
 *   POST /installer.php   → run the installation pipeline, stream
 *                           progress as NDJSON, set the auth session,
 *                           redirect to /dashboard.
 *
 *   AJAX POST /installer.php?action=test-db → JSON connectivity test.
 *
 * The installer is fully self-contained: it does not require CodeIgniter
 * to be bootable until AFTER `qanony.zip` has been extracted in step 6.
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ini_set('display_errors', '1');
@error_reporting(E_ALL);

// ── Constants ──────────────────────────────────────────────────────
const QY_ROOT = __DIR__;
const QY_ZIP  = __DIR__ . DIRECTORY_SEPARATOR . 'qanony.zip';
const QY_LOCK = __DIR__ . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'install.lock';
const QY_ENV  = __DIR__ . DIRECTORY_SEPARATOR . '.env';

// ── Dispatch ───────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST' && $action === 'test-db') {
    qy_action_test_db();
    exit;
}

if ($method === 'POST' && $action === 'install') {
    qy_action_install();
    exit;
}

qy_action_render_form();
exit;


// ───────────────────────────────────────────────────────────────────
// Action: render the installer form
// ───────────────────────────────────────────────────────────────────

function qy_action_render_form(): void
{
    // If the lock file already exists the application has been installed.
    if (is_file(QY_LOCK)) {
        qy_render_already_installed();
        return;
    }

    $defaultSiteUrl = qy_detect_site_url();

    // If qanony.zip is missing, the bundle was uploaded incorrectly.
    $zipMissing = ! is_file(QY_ZIP);
    $phpOk      = version_compare(PHP_VERSION, '8.0', '>=');
    $zipExt     = extension_loaded('zip');

    qy_render_form([
        'site_url'        => $defaultSiteUrl,
        'db_hostname'     => 'localhost',
        'db_port'         => '3306',
        'db_database'     => '',
        'db_username'     => '',
        'db_password'     => '',
        'admin_username'  => 'admin',
        'admin_email'     => '',
        'admin_full_name' => 'مدير النظام',
    ], [
        'zipMissing' => $zipMissing,
        'phpOk'      => $phpOk,
        'zipExt'     => $zipExt,
        'error'      => $_GET['error'] ?? '',
    ]);
}


// ───────────────────────────────────────────────────────────────────
// Action: AJAX DB connectivity test
// ───────────────────────────────────────────────────────────────────

function qy_action_test_db(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $host = trim((string) ($_POST['db_hostname'] ?? 'localhost'));
    $port = max(1, (int) ($_POST['db_port'] ?? 3306));
    $name = trim((string) ($_POST['db_database'] ?? ''));
    $user = trim((string) ($_POST['db_username'] ?? ''));
    $pass = (string) ($_POST['db_password'] ?? '');

    if ($name === '') {
        echo json_encode(['ok' => false, 'message' => 'Database name is required.']);
        return;
    }

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        new PDO($dsn, $user, $pass, [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo json_encode(['ok' => true, 'message' => "Connected to '{$name}'."]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
}


// ───────────────────────────────────────────────────────────────────
// Action: run the full installation pipeline (streams NDJSON)
// ───────────────────────────────────────────────────────────────────

function qy_action_install(): void
{
    // ── Pre-output: reserve a session ID and emit the session cookie
    // BEFORE we start streaming progress, because once we've echoed
    // the first line of NDJSON no more headers (cookies) can be sent.
    // We will INSERT the matching ci_sessions row at the auto-login step.
    $sessionId = bin2hex(random_bytes(20)); // 40 hex chars, fits VARCHAR(128)
    $cookieParams = [
        'expires'  => time() + 7200,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie('ci_session', $sessionId, $cookieParams);

    // Disable any output buffering so the progress log is streamed live.
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Accel-Buffering: no');

    // Track files we created so we can roll them back on failure.
    $rollback = [
        'env'  => false,
        'lock' => false,
    ];

    $send = static function (string $level, string $msg, array $extra = []) {
        $row = array_merge(['level' => $level, 'msg' => $msg], $extra);
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        @ob_flush();
        @flush();
    };

    $fail = static function (string $msg) use ($send, &$rollback) {
        $send('error', $msg);
        if ($rollback['env'] && is_file(QY_ENV))   { @unlink(QY_ENV); }
        if ($rollback['lock'] && is_file(QY_LOCK)) { @unlink(QY_LOCK); }
        $send('done', 'Installation failed. Fix the issue above and click "Install Qanony" again.', ['ok' => false]);
        exit;
    };

    try {
        // ── Step 1: PHP version ──────────────────────────────────
        $send('step', 'Checking PHP version…');
        if (! version_compare(PHP_VERSION, '8.0', '>=')) {
            $fail('PHP ' . PHP_VERSION . ' is too old. Requires PHP ≥ 8.0.');
        }
        $send('ok', 'PHP ' . PHP_VERSION . ' ✓');

        // ── Step 2: Required extensions ───────────────────────────
        $send('step', 'Checking PHP extensions…');
        $required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'xml', 'fileinfo', 'intl', 'zip'];
        $missing = [];
        foreach ($required as $ext) {
            if (! extension_loaded($ext)) { $missing[] = $ext; }
        }
        if ($missing) {
            $fail('Missing PHP extensions: ' . implode(', ', $missing));
        }
        $send('ok', 'All required extensions loaded ✓');

        // ── Step 3: Validate input ────────────────────────────────
        $send('step', 'Validating input…');
        $input = qy_collect_input();
        if ($err = qy_validate_input($input)) {
            $fail($err);
        }
        $send('ok', 'Input validated ✓');

        // ── Step 4: Test DB connection ────────────────────────────
        $send('step', 'Connecting to database…');
        try {
            $dsn = "mysql:host={$input['db_hostname']};port={$input['db_port']};dbname={$input['db_database']};charset=utf8mb4";
            new PDO($dsn, $input['db_username'], $input['db_password'], [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            $fail('Database connection failed: ' . $e->getMessage());
        }
        $send('ok', "Connected to '{$input['db_database']}' ✓");

        // ── Step 5: Verify qanony.zip exists ──────────────────────
        $send('step', 'Locating application bundle…');
        if (! is_file(QY_ZIP)) {
            $fail('qanony.zip is missing from the installer directory.');
        }
        $send('ok', 'qanony.zip found (' . qy_format_bytes(filesize(QY_ZIP)) . ') ✓');

        // ── Step 6: Extract qanony.zip ────────────────────────────
        $send('step', 'Extracting application files…');
        $zip = new ZipArchive();
        $res = $zip->open(QY_ZIP);
        if ($res !== true) {
            $fail("Failed to open qanony.zip (ZipArchive code {$res}).");
        }
        $count = $zip->numFiles;
        if (! $zip->extractTo(QY_ROOT)) {
            $zip->close();
            $fail('Failed to extract qanony.zip — check write permissions on the install directory.');
        }
        $zip->close();
        $send('ok', "Extracted {$count} entries ✓");

        // ── Step 7: Ensure writable subdirectories ───────────────
        $send('step', 'Creating writable directories…');
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
            $dir = QY_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (! is_dir($dir)) { @mkdir($dir, 0775, true); }
            $ph = $dir . DIRECTORY_SEPARATOR . 'index.html';
            if (! file_exists($ph)) {
                @file_put_contents($ph, '<!DOCTYPE html><html><body></body></html>');
            }
        }
        $send('ok', 'Writable directories ready ✓');

        // ── Step 8: Recursive chmod ──────────────────────────────
        $send('step', 'Fixing file permissions…');
        $fixed = qy_recursive_chmod(QY_ROOT);
        $send('ok', "Permissions applied to {$fixed} entries ✓");

        // ── Step 9: Write .env ────────────────────────────────────
        $send('step', 'Writing .env configuration…');
        qy_write_env($input);
        $rollback['env'] = true;
        $send('ok', '.env written ✓');

        // ── Step 10: Boot CodeIgniter ────────────────────────────
        $send('step', 'Booting CodeIgniter…');
        qy_boot_ci();
        $send('ok', 'CodeIgniter booted ✓');

        // ── Step 11: Run migrations ──────────────────────────────
        $send('step', 'Running database migrations…');
        try {
            $migrate = \Config\Services::migrations();
            $migrate->latest();
        } catch (Throwable $e) {
            $fail('Migration failed: ' . $e->getMessage());
        }
        $send('ok', 'Migrations applied ✓');

        // ── Step 12: Seed initial data ───────────────────────────
        $send('step', 'Seeding roles and permissions…');
        try {
            \Config\Database::seeder()->call('InitialSeeder');
        } catch (Throwable $e) {
            $fail('Seeding failed: ' . $e->getMessage());
        }
        $send('ok', 'Seed complete ✓');

        // ── Step 13: Update admin user ───────────────────────────
        $send('step', 'Configuring admin account…');
        try {
            $db = \Config\Database::connect();
            $admin = $db->table('users')->where('username', 'admin')->get()->getRowArray();
            if (! $admin) {
                $fail('Default admin row not found after seeding.');
            }
            $hash = password_hash($input['admin_password'], PASSWORD_BCRYPT, ['cost' => 12]);

            // If the requested username differs from 'admin', ensure it's unique.
            if ($input['admin_username'] !== 'admin') {
                $conflict = $db->table('users')
                    ->where('username', $input['admin_username'])
                    ->where('id !=', $admin['id'])
                    ->countAllResults();
                if ($conflict > 0) {
                    $fail("Admin username '{$input['admin_username']}' is already taken.");
                }
            }

            $db->table('users')->where('id', $admin['id'])->update([
                'username'              => $input['admin_username'],
                'email'                 => $input['admin_email'],
                'full_name'             => $input['admin_full_name'],
                'password_hash'         => $hash,
                'force_password_change' => 0,
                'is_active'             => 1,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            $adminId = (int) $admin['id'];
        } catch (Throwable $e) {
            $fail('Failed to configure admin: ' . $e->getMessage());
        }
        $send('ok', "Admin user '{$input['admin_username']}' configured ✓");

        // ── Step 14: Write install.lock ──────────────────────────
        $send('step', 'Finalizing installation…');
        try {
            \Config\AppVersion::writeLock(\Config\AppVersion::APP_VERSION);
            $rollback['lock'] = true;
        } catch (Throwable $e) {
            $fail('Failed to write install.lock: ' . $e->getMessage());
        }
        $send('ok', 'install.lock written ✓');

        // ── Step 15: Auto-login ──────────────────────────────────
        $send('step', 'Signing you in…');
        try {
            $loginUrl = qy_autologin($adminId, $input['site_url'], $sessionId, $input);
        } catch (Throwable $e) {
            $fail('Auto-login failed: ' . $e->getMessage());
        }
        $send('ok', 'Signed in ✓');

        // ── Step 16: Cleanup ─────────────────────────────────────
        $send('step', 'Cleaning up installer files…');
        @unlink(QY_ZIP);
        $self = __FILE__;
        register_shutdown_function(static function () use ($self): void {
            @unlink($self);
        });
        $send('ok', 'Installer cleaned up ✓');

        // ── Done ─────────────────────────────────────────────────
        $send('done', 'Installation complete — redirecting to dashboard…', [
            'ok'       => true,
            'redirect' => $loginUrl,
        ]);
    } catch (Throwable $e) {
        $fail('Unexpected error: ' . $e->getMessage());
    }
}


// ───────────────────────────────────────────────────────────────────
// Helpers
// ───────────────────────────────────────────────────────────────────

function qy_collect_input(): array
{
    return [
        'site_url'        => rtrim(trim((string) ($_POST['site_url']        ?? '')), '/') . '/',
        'db_hostname'     => trim((string) ($_POST['db_hostname']     ?? 'localhost')),
        'db_port'         => max(1, (int) ($_POST['db_port']          ?? 3306)),
        'db_database'     => trim((string) ($_POST['db_database']     ?? '')),
        'db_username'     => trim((string) ($_POST['db_username']     ?? '')),
        'db_password'     =>       (string) ($_POST['db_password']     ?? ''),
        'admin_username'  => trim((string) ($_POST['admin_username']  ?? 'admin')),
        'admin_email'     => trim((string) ($_POST['admin_email']     ?? '')),
        'admin_full_name' => trim((string) ($_POST['admin_full_name'] ?? 'مدير النظام')),
        'admin_password'  =>       (string) ($_POST['admin_password']  ?? ''),
        'admin_password_confirm' => (string) ($_POST['admin_password_confirm'] ?? ''),
    ];
}

function qy_validate_input(array $in): ?string
{
    if ($in['site_url'] === '/' || ! filter_var(rtrim($in['site_url'], '/'), FILTER_VALIDATE_URL)) {
        return 'Site URL is invalid.';
    }
    if ($in['db_database'] === '')  { return 'Database name is required.'; }
    if ($in['db_username'] === '')  { return 'Database username is required.'; }
    if ($in['admin_username'] === '') { return 'Admin username is required.'; }
    if (! filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
        return 'Admin email is invalid.';
    }
    if (strlen($in['admin_password']) < 8) {
        return 'Admin password must be at least 8 characters.';
    }
    if ($in['admin_password'] !== $in['admin_password_confirm']) {
        return 'Admin password and confirmation do not match.';
    }
    return null;
}

function qy_detect_site_url(): string
{
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $dir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/installer.php'), '/\\');
    return $proto . '://' . $host . $dir . '/';
}

function qy_format_bytes(int $bytes): string
{
    if ($bytes < 1024) { return $bytes . ' B'; }
    if ($bytes < 1048576) { return round($bytes / 1024, 1) . ' KB'; }
    return round($bytes / 1048576, 1) . ' MB';
}

function qy_recursive_chmod(string $root): int
{
    @chmod($root, 0755);
    $writablePrefix = $root . DIRECTORY_SEPARATOR . 'writable';
    $fixed = 0;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $item) {
        /** @var SplFileInfo $item */
        $isInWritable = str_starts_with($item->getPathname(), $writablePrefix);
        $mode = $item->isDir()
            ? ($isInWritable ? 0775 : 0755)
            : ($isInWritable ? 0664 : 0644);
        if (@chmod($item->getPathname(), $mode)) { $fixed++; }
    }
    return $fixed;
}

function qy_write_env(array $in): void
{
    $quote = static function (string $v): string {
        $v = str_replace(["\r", "\n", "\0"], '', $v);
        return "'" . str_replace("'", "'\\''", $v) . "'";
    };

    $siteUrl  = $in['site_url'];
    $parsed   = parse_url($siteUrl);
    $hostname = $parsed['host'] ?? 'localhost';
    $secure   = (($parsed['scheme'] ?? 'http') === 'https') ? 'true' : 'false';
    $encKey   = 'hex2bin:' . bin2hex(random_bytes(32));

    $dbHostQ = $quote($in['db_hostname']);
    $dbNameQ = $quote($in['db_database']);
    $dbUserQ = $quote($in['db_username']);
    $dbPassQ = $quote($in['db_password']);

    $content = <<<ENV
#--------------------------------------------------------------------
# ENVIRONMENT
#--------------------------------------------------------------------

CI_ENVIRONMENT = production

#--------------------------------------------------------------------
# APP
#--------------------------------------------------------------------

app.baseURL = '{$siteUrl}'
app.allowedHostnames = '{$hostname}'
app.forceGlobalSecureRequests = {$secure}
app.CSPEnabled = false
app.defaultLocale = 'ar'
app.supportedLocales = 'ar,en'

#--------------------------------------------------------------------
# DATABASE
#--------------------------------------------------------------------

database.default.hostname = {$dbHostQ}
database.default.database = {$dbNameQ}
database.default.username = {$dbUserQ}
database.default.password = {$dbPassQ}
database.default.DBDriver = MySQLi
database.default.DBPrefix =
database.default.port = {$in['db_port']}
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci

#--------------------------------------------------------------------
# ENCRYPTION
#--------------------------------------------------------------------

encryption.key = {$encKey}

#--------------------------------------------------------------------
# SESSION
#--------------------------------------------------------------------

session.driver = 'CodeIgniter\\Session\\Handlers\\DatabaseHandler'
session.savePath = 'ci_sessions'
session.expiration = 7200
session.regenerateDestroy = true

#--------------------------------------------------------------------
# LOGGER
#--------------------------------------------------------------------

logger.threshold = 4

#--------------------------------------------------------------------
# QANONY AUTH
#--------------------------------------------------------------------

auth.minPasswordLength = 8
auth.maxLoginAttempts = 5
auth.lockoutDuration = 900

#--------------------------------------------------------------------
# UPLOAD
#--------------------------------------------------------------------

upload.maxFileSize = 52428800
upload.allowedTypes = 'docx,doc'

#--------------------------------------------------------------------
# CLOUD STORAGE INTEGRATION
#--------------------------------------------------------------------

cloud.googlePickerApiKey =
cloud.googlePickerClientId =
cloud.googlePickerAppId =
cloud.dropboxAppKey =
cloud.onedriveClientId =
ENV;

    if (file_put_contents(QY_ENV, $content) === false) {
        throw new RuntimeException('Cannot write .env file.');
    }
    @chmod(QY_ENV, 0644);
}

/**
 * Boot CodeIgniter inside the installer process so we can use the
 * migration runner, seeder, session service and database connection.
 *
 * This must only be called AFTER qanony.zip has been extracted and
 * a valid .env file has been written.
 */
function qy_boot_ci(): void
{
    if (defined('FCPATH')) { return; } // Already booted

    define('FCPATH', QY_ROOT . DIRECTORY_SEPARATOR);
    if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) { @chdir(FCPATH); }

    // During the installer run we have NO ci_sessions table until step 11,
    // but we boot AFTER migrations. To be safe we set FileHandler for the
    // first boot stage; the auto-login step rebinds to DatabaseHandler.
    $_ENV['session.driver']    = 'CodeIgniter\\Session\\Handlers\\FileHandler';
    $_SERVER['session.driver'] = 'CodeIgniter\\Session\\Handlers\\FileHandler';
    putenv('session.driver=CodeIgniter\\Session\\Handlers\\FileHandler');
    $sess = QY_ROOT . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'session';
    $_ENV['session.savePath']    = $sess;
    $_SERVER['session.savePath'] = $sess;
    putenv('session.savePath=' . $sess);

    require FCPATH . 'app/Config/Paths.php';
    $paths = new \Config\Paths();

    require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

    require_once SYSTEMPATH . 'Config/DotEnv.php';
    (new \CodeIgniter\Config\DotEnv(ROOTPATH))->load();

    // Force the in-memory baseURL to match what the user typed, so site_url()
    // produces the correct redirect URL.
    $_ENV['app.baseURL']    = $_POST['site_url'] ?? '';
    $_SERVER['app.baseURL'] = $_POST['site_url'] ?? '';

    $app = \Config\Services::codeigniter();
    $app->initialize();
    $app->setContext('web');
    // Do NOT call $app->run() — we want to use services manually.
}

/**
 * Issue an authenticated session for the freshly configured admin user
 * by INSERTING a row directly into ci_sessions with PHP's native
 * session_encode() format (which is what CI4's DatabaseHandler reads).
 *
 * The session cookie was already set by qy_action_install() BEFORE any
 * output, so the browser will present this session ID on the redirect.
 *
 * @param int    $userId    DB id of the admin row to log in
 * @param string $siteUrl   Site URL the wizard collected (used for redirect)
 * @param string $sessionId 40-char hex session ID already sent as ci_session cookie
 * @param array  $input     Form input (used to reach the DB if CI4 services aren't usable)
 */
function qy_autologin(int $userId, string $siteUrl, string $sessionId, array $input): string
{
    // Use a fresh PDO connection — avoids any CI4 session/service side-effects.
    $dsn = "mysql:host={$input['db_hostname']};port={$input['db_port']};dbname={$input['db_database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $input['db_username'], $input['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Look up the freshly configured admin row.
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (! $user) {
        throw new RuntimeException('Cannot find the newly created admin user.');
    }

    // Look up the role name.
    $st = $pdo->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
    $st->execute([$user['role_id']]);
    $role = $st->fetch(PDO::FETCH_ASSOC);

    // Pull permissions assigned to the admin role.
    $st = $pdo->prepare(
        'SELECT p.name FROM role_permissions rp '
      . 'JOIN permissions p ON p.id = rp.permission_id '
      . 'WHERE rp.role_id = ?'
    );
    $st->execute([$user['role_id']]);
    $permissions = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'name');

    // Build the session payload exactly as AuthController::attemptLogin() does.
    $session = [
        '__ci_last_regenerate'  => time(),
        'user_id'               => (int) $user['id'],
        'username'              => (string) $user['username'],
        'full_name'             => (string) ($user['full_name'] ?? ''),
        'email'                 => (string) ($user['email'] ?? ''),
        'role_id'               => (int) $user['role_id'],
        'role_name'             => (string) ($role['name'] ?? 'admin'),
        'permissions'           => $permissions,
        'logged_in'             => true,
        'force_password_change' => false,
    ];

    // CI4 DatabaseHandler stores the result of PHP's session_encode() in the
    // `data` BLOB column. We construct that string manually:
    //   key1|serialized_val1;key2|serialized_val2;...
    // (PHP's built-in default serialization handler format.)
    $encoded = '';
    foreach ($session as $key => $value) {
        if (strpos($key, '|') !== false) {
            throw new RuntimeException("Session key contains pipe: {$key}");
        }
        $encoded .= $key . '|' . serialize($value);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ts = time();

    // Insert (or replace) the session row.
    $st = $pdo->prepare(
        'INSERT INTO ci_sessions (id, ip_address, timestamp, data) '
      . 'VALUES (:id, :ip, :ts, :data) '
      . 'ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address), '
      . 'timestamp = VALUES(timestamp), data = VALUES(data)'
    );
    $st->bindValue(':id',   $sessionId);
    $st->bindValue(':ip',   $ip);
    $st->bindValue(':ts',   $ts, PDO::PARAM_INT);
    $st->bindParam(':data', $encoded, PDO::PARAM_LOB);
    $st->execute();

    return rtrim($siteUrl, '/') . '/dashboard';
}


// ───────────────────────────────────────────────────────────────────
// Views
// ───────────────────────────────────────────────────────────────────

function qy_render_already_installed(): void
{
    $loginUrl = qy_detect_site_url() . 'auth/login';
    ?><!DOCTYPE html>
<html lang="en" dir="ltr"><head><meta charset="utf-8"><title>Qanony — Already Installed</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:640px;margin:4rem auto;padding:0 1.5rem;color:#222;line-height:1.55}
h1{font-weight:600}a.btn{display:inline-block;background:#1a73e8;color:#fff;text-decoration:none;padding:.7rem 1.5rem;border-radius:6px;font-weight:500}
</style></head><body>
<h1>Qanony is already installed</h1>
<p>An <code>install.lock</code> file is present on the server. To reinstall, delete <code>writable/install.lock</code> and refresh this page.</p>
<p><a class="btn" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES) ?>">Go to login →</a></p>
</body></html><?php
}

function qy_render_form(array $defaults, array $flags): void
{
    ?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Qanony — Setup / إعداد قانوني</title>
<style>
:root{--blue:#1a73e8;--ok:#197a3d;--err:#b00020;--bg:#f7f8fa;--card:#fff;--bd:#dde1e6;--muted:#666}
*{box-sizing:border-box}
body{font-family:-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:var(--bg);margin:0;padding:2rem 1rem;color:#222;line-height:1.55}
.wrap{max-width:960px;margin:0 auto}
header{text-align:center;margin-bottom:1.5rem}
header h1{font-weight:600;margin:.25rem 0;font-size:1.8rem}
header p{color:var(--muted);margin:0}
.card{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:1.5rem;margin-bottom:1rem;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.card h2{margin:0 0 1rem;font-size:1.1rem;font-weight:600;border-bottom:1px solid var(--bd);padding-bottom:.5rem}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.field{display:flex;flex-direction:column;gap:.25rem}
.field label{font-size:.92rem;font-weight:500}
.field label .en{color:var(--muted);font-size:.82rem;font-weight:400;margin-inline-start:.4rem}
.field input{padding:.6rem .75rem;border:1px solid var(--bd);border-radius:6px;font-size:.95rem;font-family:inherit;direction:ltr;text-align:start}
.field input:focus{outline:0;border-color:var(--blue);box-shadow:0 0 0 3px rgba(26,115,232,.15)}
.field .hint{font-size:.78rem;color:var(--muted)}
.row{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.4rem 0;border-bottom:1px dashed #eef0f3}
.row:last-child{border-bottom:0}
.ok{color:var(--ok)}.err{color:var(--err)}
.btn{background:var(--blue);color:#fff;border:0;padding:.85rem 2rem;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:500;font-family:inherit}
.btn:disabled{background:#aaa;cursor:not-allowed}
.btn.secondary{background:#eef1f5;color:#222}
.actions{display:flex;justify-content:flex-end;gap:.5rem}
.banner{background:#fef3c7;border:1px solid #f6c453;color:#7a4a00;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem}
.banner.err{background:#fde2e2;border-color:#e0a3a3;color:#7a1d1d}
#log{background:#0f172a;color:#cbd5e1;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85rem;padding:1rem;border-radius:8px;min-height:160px;max-height:340px;overflow-y:auto;direction:ltr;text-align:start}
#log .ok{color:#7ddc8c}#log .err{color:#ff8e8e}#log .step{color:#9ec5fe}#log .done{color:#fff}
#progress{display:none;margin-top:1rem}
.testbar{display:flex;gap:.5rem;align-items:center;margin-top:.5rem;font-size:.9rem}
</style>
</head>
<body>
<div class="wrap">

<header>
    <h1>قانوني — إعداد التثبيت / Qanony Setup</h1>
    <p>قم بتعبئة البيانات أدناه ثم اضغط «تثبيت» / Fill in the values below and click Install</p>
</header>

<?php if ($flags['zipMissing'] || ! $flags['phpOk'] || ! $flags['zipExt']): ?>
<div class="banner err">
    <strong>التثبيت غير ممكن / Cannot proceed:</strong>
    <ul style="margin:.4rem 0 0;padding-inline-start:1.4rem">
        <?php if (! $flags['phpOk']): ?><li>PHP <?= PHP_VERSION ?> too old — need ≥ 8.0</li><?php endif; ?>
        <?php if (! $flags['zipExt']): ?><li>PHP <code>zip</code> extension is missing</li><?php endif; ?>
        <?php if ($flags['zipMissing']): ?><li><code>qanony.zip</code> is missing in this directory</li><?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (! empty($flags['error'])): ?>
<div class="banner err"><?= htmlspecialchars((string) $flags['error']) ?></div>
<?php endif; ?>

<form id="installForm" autocomplete="off">
    <input type="hidden" name="action" value="install">

    <div class="card">
        <h2>الموقع / Site</h2>
        <div class="grid">
            <div class="field" style="grid-column:1/-1">
                <label>رابط الموقع <span class="en">Site URL</span></label>
                <input name="site_url" type="url" value="<?= htmlspecialchars($defaults['site_url'], ENT_QUOTES) ?>" required>
                <span class="hint">مثال: https://stpbystp.com/q/</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>قاعدة البيانات / Database</h2>
        <div class="grid">
            <div class="field">
                <label>خادم قاعدة البيانات <span class="en">DB Host</span></label>
                <input name="db_hostname" value="<?= htmlspecialchars($defaults['db_hostname'], ENT_QUOTES) ?>" required>
            </div>
            <div class="field">
                <label>المنفذ <span class="en">Port</span></label>
                <input name="db_port" type="number" min="1" value="<?= htmlspecialchars($defaults['db_port'], ENT_QUOTES) ?>" required>
            </div>
            <div class="field">
                <label>اسم قاعدة البيانات <span class="en">DB Name</span></label>
                <input name="db_database" value="<?= htmlspecialchars($defaults['db_database'], ENT_QUOTES) ?>" required>
            </div>
            <div class="field">
                <label>المستخدم <span class="en">DB Username</span></label>
                <input name="db_username" value="<?= htmlspecialchars($defaults['db_username'], ENT_QUOTES) ?>" required>
            </div>
            <div class="field" style="grid-column:1/-1">
                <label>كلمة المرور <span class="en">DB Password</span></label>
                <input name="db_password" type="password" value="">
            </div>
        </div>
        <div class="testbar">
            <button type="button" class="btn secondary" id="testDbBtn">اختبار الاتصال / Test connection</button>
            <span id="testDbResult"></span>
        </div>
    </div>

    <div class="card">
        <h2>حساب المدير / Admin Account</h2>
        <div class="grid">
            <div class="field">
                <label>اسم المستخدم <span class="en">Username</span></label>
                <input name="admin_username" value="<?= htmlspecialchars($defaults['admin_username'], ENT_QUOTES) ?>" required>
            </div>
            <div class="field">
                <label>البريد الإلكتروني <span class="en">Email</span></label>
                <input name="admin_email" type="email" value="<?= htmlspecialchars($defaults['admin_email'], ENT_QUOTES) ?>" required>
            </div>
            <div class="field" style="grid-column:1/-1">
                <label>الاسم الكامل <span class="en">Full Name</span></label>
                <input name="admin_full_name" value="<?= htmlspecialchars($defaults['admin_full_name'], ENT_QUOTES) ?>">
            </div>
            <div class="field">
                <label>كلمة المرور <span class="en">Password (min 8)</span></label>
                <input name="admin_password" type="password" minlength="8" required>
            </div>
            <div class="field">
                <label>تأكيد كلمة المرور <span class="en">Confirm Password</span></label>
                <input name="admin_password_confirm" type="password" minlength="8" required>
            </div>
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="btn" id="installBtn" <?= ($flags['zipMissing'] || ! $flags['phpOk'] || ! $flags['zipExt']) ? 'disabled' : '' ?>>
            تثبيت قانوني / Install Qanony
        </button>
    </div>
</form>

<div id="progress" class="card">
    <h2>سجل التثبيت / Installation Log</h2>
    <div id="log"></div>
</div>

</div>

<script>
(function(){
    const form = document.getElementById('installForm');
    const log = document.getElementById('log');
    const progress = document.getElementById('progress');
    const installBtn = document.getElementById('installBtn');

    function appendLog(level, msg) {
        const line = document.createElement('div');
        line.className = level;
        const prefix = level === 'ok' ? '✓ ' : level === 'err' ? '✗ ' : level === 'step' ? '→ ' : '';
        line.textContent = prefix + msg;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    // ── Test DB connection ─────────────────────────────────────
    document.getElementById('testDbBtn').addEventListener('click', async function(){
        const btn = this;
        const out = document.getElementById('testDbResult');
        btn.disabled = true; out.textContent = 'Testing…'; out.className = '';
        const fd = new FormData(form);
        fd.set('action', 'test-db');
        try {
            const res = await fetch(window.location.pathname + '?action=test-db', {method:'POST', body: fd});
            const json = await res.json();
            out.textContent = json.message;
            out.className = json.ok ? 'ok' : 'err';
        } catch (e) {
            out.textContent = 'Network error: ' + e.message;
            out.className = 'err';
        } finally { btn.disabled = false; }
    });

    // ── Install ────────────────────────────────────────────────
    form.addEventListener('submit', async function(ev){
        ev.preventDefault();
        installBtn.disabled = true;
        installBtn.textContent = 'Installing…';
        progress.style.display = 'block';
        log.innerHTML = '';

        const fd = new FormData(form);
        try {
            const res = await fetch(window.location.pathname, {method:'POST', body: fd});
            if (!res.ok) {
                appendLog('err', 'HTTP ' + res.status + ' — ' + res.statusText);
                installBtn.disabled = false;
                installBtn.textContent = 'Retry / إعادة المحاولة';
                return;
            }
            const reader = res.body.getReader();
            const dec = new TextDecoder();
            let buf = '';
            let redirect = null;
            while (true) {
                const {value, done} = await reader.read();
                if (done) break;
                buf += dec.decode(value, {stream:true});
                const lines = buf.split('\n');
                buf = lines.pop();
                for (const ln of lines) {
                    if (!ln.trim()) continue;
                    try {
                        const row = JSON.parse(ln);
                        appendLog(row.level, row.msg);
                        if (row.redirect) redirect = row.redirect;
                    } catch (e) {
                        appendLog('err', 'Bad server line: ' + ln);
                    }
                }
            }
            if (redirect) {
                appendLog('done', 'Redirecting to ' + redirect);
                setTimeout(()=>{ window.location.href = redirect; }, 1200);
            } else {
                installBtn.disabled = false;
                installBtn.textContent = 'Retry / إعادة المحاولة';
            }
        } catch (e) {
            appendLog('err', 'Network error: ' + e.message);
            installBtn.disabled = false;
            installBtn.textContent = 'Retry / إعادة المحاولة';
        }
    });
})();
</script>
</body>
</html><?php
}
