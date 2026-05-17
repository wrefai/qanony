<?php

/**
 * Qanony – CodeIgniter 4 Entry Point
 * Restructured for cPanel shared hosting (document root = project root)
 */

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION — must be first, before any PHP-8-only syntax
 *---------------------------------------------------------------
 */
if (version_compare(PHP_VERSION, '8.0', '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'Your PHP version must be 8.0 or higher to run this application. Current version: ' . PHP_VERSION;
    exit(1);
}


/*
 *---------------------------------------------------------------
 * AUTO-CREATE WRITABLE DIRECTORIES
 * Runs silently on first boot — no manual setup needed
 *---------------------------------------------------------------
 */
(static function (): void {
    $base = __DIR__ . DIRECTORY_SEPARATOR . 'writable';
    $dirs = [
        $base,
        $base . '/cache',
        $base . '/logs',
        $base . '/session',
        $base . '/uploads',
        $base . '/uploads/documents',
        $base . '/uploads/documents/original',
        $base . '/uploads/documents/converted',
        $base . '/uploads/documents/extracted',
    ];
    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            // Try 0775 first (group-writable, common on cPanel), fall back to 0755.
            if (! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        $placeholder = $dir . '/index.html';
        if (! file_exists($placeholder)) {
            file_put_contents($placeholder, '<!DOCTYPE html><html><body></body></html>');
        }
    }
})();

/*
 *---------------------------------------------------------------
 * AUTO-DETECT BASE URL
 *
 * CI4 4.4 validates $baseURL strictly. When $baseURL = '' and
 * the server cannot auto-detect scheme+host (common on cPanel /
 * reverse-proxy stacks), SiteURI resolves to "/" and throws a
 * ConfigException before anything renders.
 *
 * We ALWAYS inject here — unconditionally — for two reasons:
 *
 *  1. Pre-installation: no .env exists yet.
 *  2. Stale .env: a previous wizard attempt may have left a .env
 *     with "app.baseURL =" (empty), causing the same error even
 *     though the wizard never completed.
 *
 * BaseConfig::getEnvValue() checks $_ENV BEFORE calling getenv(),
 * so our $_ENV value wins over whatever DotEnv loads from .env.
 * DotEnv itself checks empty() before writing $_ENV, so it will
 * NOT overwrite our non-empty injected value.
 *
 * Once .env contains a valid URL the detected value equals the
 * configured one, making this injection effectively a no-op.
 *---------------------------------------------------------------
 */
(static function (): void {
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
        ?? (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['SERVER_PORT'] ?? 80) == 443)) ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

    // Prefer SCRIPT_FILENAME + DOCUMENT_ROOT: more reliable after mod_rewrite
    // because SCRIPT_NAME can be rewritten to the request path on some hosts.
    if (
        isset($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT'])
        && $_SERVER['DOCUMENT_ROOT'] !== ''
        && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT']) === 0
    ) {
        $rel     = substr($_SERVER['SCRIPT_FILENAME'], strlen($_SERVER['DOCUMENT_ROOT']));
        $baseDir = rtrim(dirname($rel), '/\\') . '/';
    } else {
        // Fallback: SCRIPT_NAME – /q/index.php → dirname → /q → append /
        $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\') . '/';
    }

    $url = $proto . '://' . $host . $baseDir;

    $_ENV['app.baseURL']    = $url;
    $_SERVER['app.baseURL'] = $url;
    putenv('app.baseURL=' . $url);
})();

/*
 *---------------------------------------------------------------
 * DEVELOPMENT MODE DURING INSTALLATION
 * When install.lock does not exist the app is not yet installed.
 * Force development mode so any boot/config errors are visible
 * as a proper error page instead of a blank HTTP 500.
 * Production mode takes effect automatically after installation.
 *---------------------------------------------------------------
 */
if (! file_exists(__DIR__ . '/writable/install.lock')) {
    $_ENV['CI_ENVIRONMENT']    = 'development';
    $_SERVER['CI_ENVIRONMENT'] = 'development';
    putenv('CI_ENVIRONMENT=development');
    // Show raw PHP errors during install so boot crashes are visible
    @ini_set('display_errors', '1');
    @error_reporting(E_ALL);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 * index.php lives at the project root (same level as app/, vendor/)
 *---------------------------------------------------------------
 */
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 */

// Load paths config (CI4 4.3.x bootstrap style)
require FCPATH . 'app/Config/Paths.php';

$paths = new Config\Paths();

/*
 * Self-healing permission fix:
 * cPanel File Manager sometimes extracts ZIPs with overly restrictive
 * permissions (e.g. 600 for files, 700 for dirs) that prevent PHP-FPM
 * from reading vendor/. Fix vendor/ and app/ permissions on first boot
 * if the bootstrap file isn't readable.
 */
(static function (string $fcpath): void {
    // Check readability WITHOUT realpath() — realpath() returns false on unreadable
    // paths, making it useless as a guard here. Use a plain string concat + fopen.
    $bootstrapPath = $fcpath . 'vendor/codeigniter4/framework/system/bootstrap.php';
    $fh = @fopen($bootstrapPath, 'r');
    if ($fh !== false) {
        fclose($fh);
        return; // Already readable — skip the chmod pass
    }
    // Fix permissions: chmod the top-level dir FIRST (so the iterator can open it),
    // then recurse. No @ suppressor so errors surface during install (display_errors=1).
    foreach (['vendor', 'app'] as $top) {
        $dir = $fcpath . $top;
        if (! is_dir($dir)) {
            continue;
        }
        // Must chmod the top dir before RecursiveDirectoryIterator tries to open it
        chmod($dir, 0755);
        try {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iter as $item) {
                chmod($item->getPathname(), $item->isDir() ? 0755 : 0644);
            }
        } catch (Throwable $e) {
            // Log and continue — don't let a chmod failure kill the boot
            error_log('Qanony fixperms: ' . $e->getMessage());
        }
    }
})(FCPATH);

// Location of the framework bootstrap file (CI4 4.3.x uses bootstrap.php)
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

// Load .env into $_SERVER / $_ENV
require_once SYSTEMPATH . 'Config/DotEnv.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();

/*
 *---------------------------------------------------------------
 * INSTALL-TIME SESSION OVERRIDE
 * The wizard needs sessions (for CSRF) BEFORE migrations create
 * the ci_sessions DB table. Override to FileHandler AFTER DotEnv
 * so our value wins over whatever .env specifies.
 * Post-install, .env has DatabaseHandler — this block is skipped.
 *---------------------------------------------------------------
 */
if (! file_exists(__DIR__ . '/writable/install.lock')) {
    $sessionSavePath = rtrim(str_replace('\\', '/', __DIR__), '/') . '/writable/session';
    $_ENV['session.driver']    = 'CodeIgniter\Session\Handlers\FileHandler';
    $_SERVER['session.driver'] = 'CodeIgniter\Session\Handlers\FileHandler';
    putenv('session.driver=CodeIgniter\Session\Handlers\FileHandler');
    $_ENV['session.savePath']    = $sessionSavePath;
    $_SERVER['session.savePath'] = $sessionSavePath;
    putenv('session.savePath=' . $sessionSavePath);
    unset($sessionSavePath);
}

// Boot the application
$app = Config\Services::codeigniter();
$app->initialize();
$context = is_cli() ? 'php-cli' : 'web';
$app->setContext($context);
$app->run();
