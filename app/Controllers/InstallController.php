<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\AppVersion;
use Psr\Log\LoggerInterface;

/**
 * InstallController — AJAX multi-step setup wizard.
 *
 * Publicly accessible (no auth required).
 *
 * Routes
 * ──────
 *   GET  /install                       → wizard UI page
 *   POST /install/step/requirements     → check PHP & dirs
 *   POST /install/step/config           → test DB + write .env
 *   POST /install/step/migrate          → run migrations + seed
 *   POST /install/step/admin            → set admin credentials
 *   POST /install/step/finalize         → write install.lock
 *
 * All POST endpoints return JSON: { ok, message, [items] }
 */
class InstallController extends Controller
{
    protected $helpers = ['url', 'form'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    // ── GET /install ──────────────────────────────────────────────

    public function index(): \CodeIgniter\HTTP\RedirectResponse|string
    {
        // Already installed and fully up to date → nothing to do here
        if (AppVersion::isInstalled() && AppVersion::isUpToDate()) {
            return redirect()->to(site_url('auth/login'));
        }

        $lock = AppVersion::readLock();

        return view('install/wizard', [
            'title'       => 'معالج الإعداد',
            'locale'      => 'ar',
            'direction'   => 'rtl',
            'appVersion'  => AppVersion::APP_VERSION,
            'isInstalled' => $lock !== null,
            'lockVersion' => $lock['version'] ?? null,
            'csrfName'    => csrf_token(),
            'csrfHash'    => csrf_hash(),
            'envValues'   => $this->readEnvValues(),
        ]);
    }

    // ── POST /install/step/:name ──────────────────────────────────
    // Dispatcher for all AJAX step endpoints.
    // Destructive steps are blocked once the app is installed and
    // up to date — use /update for post-install migrations.

    public function step(string $name): ResponseInterface
    {
        $destructive = ['config', 'migrate', 'admin', 'finalize'];

        if (in_array($name, $destructive, true)
            && AppVersion::isInstalled()
            && AppVersion::isUpToDate()
        ) {
            return $this->json([
                'ok'      => false,
                'message' => 'النظام مُثبَّت ومحدَّث. استخدم /update لتطبيق ترحيلات جديدة.',
            ], 403);
        }

        return match ($name) {
            'requirements' => $this->stepRequirements(),
            'db'           => $this->stepDb(),
            'config'       => $this->stepConfig(),
            'migrate'      => $this->stepMigrate(),
            'admin'        => $this->stepAdmin(),
            'finalize'     => $this->stepFinalize(),
            default        => $this->json(['ok' => false, 'message' => 'خطوة غير معروفة: ' . $name], 404),
        };
    }

    // ── POST /install/step/requirements ──────────────────────────

    public function stepRequirements(): ResponseInterface
    {
        $result = AppVersion::checkRequirements();

        return $this->json([
            'ok'      => $result['ok'],
            'message' => $result['ok']
                ? 'جميع المتطلبات مستوفاة.'
                : 'بعض المتطلبات غير مستوفاة — راجع القائمة أدناه.',
            'items'   => $result['items'],
        ]);
    }

    // ── POST /install/step/db (legacy — kept for compatibility) ───

    public function stepDb(): ResponseInterface
    {
        try {
            $db = \Config\Database::connect();
            $db->connect(true);
            $dbName = $db->getDatabase();

            return $this->json([
                'ok'      => true,
                'message' => 'الاتصال بقاعدة البيانات «' . $dbName . '» ناجح.',
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok'      => false,
                'message' => 'فشل الاتصال: ' . $e->getMessage(),
            ]);
        }
    }

    // ── POST /install/step/config ─────────────────────────────────
    // Accepts site URL + DB credentials, tests connection via PDO,
    // then writes a complete .env file (preserving encryption key).

    public function stepConfig(): ResponseInterface
    {
        $post = $this->request->getPost();

        $siteUrl = rtrim(trim((string) ($post['site_url'] ?? '')), '/') . '/';
        $dbHost  = trim((string) ($post['db_hostname'] ?? 'localhost'));
        $dbPort  = max(1, (int) ($post['db_port'] ?? 3306));
        $dbName  = trim((string) ($post['db_database'] ?? ''));
        $dbUser  = trim((string) ($post['db_username'] ?? ''));
        $dbPass  = (string) ($post['db_password'] ?? '');

        if (empty($siteUrl) || $siteUrl === '/') {
            return $this->json(['ok' => false, 'message' => 'رابط الموقع مطلوب.']);
        }

        if (empty($dbName)) {
            return $this->json(['ok' => false, 'message' => 'اسم قاعدة البيانات مطلوب.']);
        }

        // Test DB connection directly via PDO (independent of CI4 config)
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new \PDO($dsn, $dbUser, $dbPass, [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            unset($pdo);
        } catch (\PDOException $e) {
            return $this->json([
                'ok'      => false,
                'message' => 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage(),
            ]);
        }

        // Write .env
        try {
            $this->writeEnv([
                'site_url'    => $siteUrl,
                'db_hostname' => $dbHost,
                'db_port'     => $dbPort,
                'db_database' => $dbName,
                'db_username' => $dbUser,
                'db_password' => $dbPass,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok'      => false,
                'message' => 'تعذّر كتابة ملف الإعداد: ' . $e->getMessage(),
            ]);
        }

        return $this->json([
            'ok'      => true,
            'message' => 'تم حفظ الإعدادات. الاتصال بقاعدة البيانات «' . $dbName . '» ناجح.',
        ]);
    }

    // ── POST /install/step/migrate ────────────────────────────────

    public function stepMigrate(): ResponseInterface
    {
        $log = [];

        // Migrations
        try {
            $migrate = \Config\Services::migrations();
            $migrate->latest();
            $log[] = ['ok' => true,  'label' => 'Migrations', 'msg' => 'جميع الترحيلات مُطبَّقة بنجاح.'];
        } catch (\Throwable $e) {
            $log[] = ['ok' => false, 'label' => 'Migrations', 'msg' => $e->getMessage()];

            return $this->json([
                'ok'      => false,
                'message' => 'فشل تطبيق الترحيلات.',
                'items'   => $log,
            ]);
        }

        // Seed
        try {
            $seeder = \Config\Database::seeder();
            $seeder->call('InitialSeeder');
            $log[] = ['ok' => true, 'label' => 'Initial Seed', 'msg' => 'تم إضافة الأدوار والصلاحيات والمستخدم الإداري.'];
        } catch (\Throwable $e) {
            $log[] = ['ok' => false, 'label' => 'Initial Seed', 'msg' => $e->getMessage()];

            return $this->json([
                'ok'      => false,
                'message' => 'فشل تهيئة البيانات الأولية.',
                'items'   => $log,
            ]);
        }

        return $this->json([
            'ok'      => true,
            'message' => 'تم تطبيق الترحيلات وتهيئة البيانات بنجاح.',
            'items'   => $log,
        ]);
    }

    // ── POST /install/step/admin ──────────────────────────────────
    // Updates the default admin user seeded by InitialSeeder with
    // the credentials provided by the installer.

    public function stepAdmin(): ResponseInterface
    {
        $post = $this->request->getPost();

        $username  = trim((string) ($post['admin_username'] ?? 'admin'));
        $fullName  = trim((string) ($post['admin_full_name'] ?? 'مدير النظام'));
        $email     = trim((string) ($post['admin_email'] ?? ''));
        $password  = (string) ($post['admin_password'] ?? '');
        $confirm   = (string) ($post['admin_password_confirm'] ?? '');

        // Validate
        if (empty($username)) {
            return $this->json(['ok' => false, 'message' => 'اسم المستخدم مطلوب.']);
        }

        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'message' => 'البريد الإلكتروني غير صالح.']);
        }

        if (strlen($password) < 8) {
            return $this->json(['ok' => false, 'message' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.']);
        }

        if ($password !== $confirm) {
            return $this->json(['ok' => false, 'message' => 'كلمتا المرور غير متطابقتين.']);
        }

        try {
            $db = \Config\Database::connect();

            // Find the seeded admin user
            $admin = $db->table('users')->where('username', 'admin')->get()->getRowArray();

            if (! $admin) {
                return $this->json([
                    'ok'      => false,
                    'message' => 'لم يُعثَر على حساب المدير. تأكد من تطبيق الترحيلات أولاً.',
                ]);
            }

            // If the desired username differs from the seeded default, ensure
            // it is not already taken by another account.
            if ($username !== $admin['username']) {
                $conflict = $db->table('users')
                    ->where('username', $username)
                    ->where('id !=', $admin['id'])
                    ->countAllResults();
                if ($conflict > 0) {
                    return $this->json([
                        'ok'      => false,
                        'message' => 'اسم المستخدم «' . $username . '» مستخدم بالفعل. اختر اسماً آخر.',
                    ]);
                }
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $db->table('users')->where('id', $admin['id'])->update([
                'username'              => $username,
                'email'                 => $email,
                'full_name'             => $fullName,
                'password_hash'         => $hash,
                'force_password_change' => 0,
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);

            return $this->json([
                'ok'      => true,
                'message' => 'تم تحديث حساب المدير «' . $username . '» بنجاح.',
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok'      => false,
                'message' => 'خطأ أثناء تحديث الحساب: ' . $e->getMessage(),
            ]);
        }
    }

    // ── POST /install/step/finalize ───────────────────────────────

    public function stepFinalize(): ResponseInterface
    {
        try {
            AppVersion::writeLock(AppVersion::APP_VERSION);
        } catch (\Throwable $e) {
            return $this->json([
                'ok'      => false,
                'message' => 'تعذّر كتابة ملف القفل: ' . $e->getMessage(),
            ]);
        }

        return $this->json([
            'ok'         => true,
            'message'    => 'تم التثبيت بنجاح!',
            'version'    => AppVersion::APP_VERSION,
            'loginUrl'   => site_url('auth/login'),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────

    /**
     * Read current .env values to pre-fill the config form.
     */
    private function readEnvValues(): array
    {
        $envPath = ROOTPATH . '.env';

        $defaults = [
            'site_url'    => '',
            'db_hostname' => 'localhost',
            'db_port'     => '3306',
            'db_database' => '',
            'db_username' => 'root',
            'db_password' => '',
        ];

        if (! file_exists($envPath)) {
            return $defaults;
        }

        $lines  = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $parsed = [];

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            [$key, $val] = explode('=', $line, 2);
            $parsed[trim($key)] = trim(trim($val), "'\"");
        }

        return [
            'site_url'    => $parsed['app.baseURL']                ?? $defaults['site_url'],
            'db_hostname' => $parsed['database.default.hostname']  ?? $defaults['db_hostname'],
            'db_port'     => $parsed['database.default.port']      ?? $defaults['db_port'],
            'db_database' => $parsed['database.default.database']  ?? $defaults['db_database'],
            'db_username' => $parsed['database.default.username']  ?? $defaults['db_username'],
            'db_password' => $parsed['database.default.password']  ?? $defaults['db_password'],
        ];
    }

    /**
     * Write a complete .env file, preserving the existing encryption key
     * (or generating a new one if none exists).
     *
     * @param array{site_url:string, db_hostname:string, db_port:int,
     *              db_database:string, db_username:string, db_password:string} $params
     */
    private function writeEnv(array $params): void
    {
        $envPath = ROOTPATH . '.env';

        // Preserve existing encryption key
        $encKey = '';
        if (file_exists($envPath)) {
            $existing = file_get_contents($envPath);
            if (preg_match('/^encryption\.key\s*=\s*(.+)$/m', $existing, $m)) {
                $encKey = trim($m[1]);
            }
        }

        // Generate new key if none found
        if (empty($encKey)) {
            $encKey = 'hex2bin:' . bin2hex(random_bytes(32));
        }

        $siteUrl = $params['site_url']    ?? 'http://localhost/';
        $dbHost  = $params['db_hostname'] ?? 'localhost';
        $dbPort  = (int) ($params['db_port'] ?? 3306);
        $dbName  = $params['db_database'] ?? '';
        $dbUser  = $params['db_username'] ?? 'root';
        $dbPass  = $params['db_password'] ?? '';

        // Derive hostname and HTTPS flag from the provided site URL
        $parsedUrl   = parse_url($siteUrl);
        $hostname    = $parsedUrl['host'] ?? 'localhost';
        $isHttps     = ($parsedUrl['scheme'] ?? 'http') === 'https';
        $forceSecure = $isHttps ? 'true' : 'false';

        // Session: database driver — no file system permissions needed.
        $sessionDriver = 'CodeIgniter\\Session\\Handlers\\DatabaseHandler';
        $sessionPath   = 'ci_sessions';

        // Wrap credentials that may contain special characters (.env-safe single-quoting).
        // Newlines and null bytes are stripped for safety.
        $quoteEnv = static function (string $val): string {
            $val = str_replace(["\r", "\n", "\0"], '', $val);
            return "'" . str_replace("'", "'\\''", $val) . "'";
        };

        $dbHostQ = $quoteEnv($dbHost);
        $dbNameQ = $quoteEnv($dbName);
        $dbUserQ = $quoteEnv($dbUser);
        $dbPassQ = $quoteEnv($dbPass);

        $content = <<<ENV
#--------------------------------------------------------------------
# ENVIRONMENT
# Change to 'development' temporarily if you need verbose error pages.
#--------------------------------------------------------------------

CI_ENVIRONMENT = production

#--------------------------------------------------------------------
# APP
#--------------------------------------------------------------------

app.baseURL = '{$siteUrl}'
app.allowedHostnames = '{$hostname}'
app.forceGlobalSecureRequests = {$forceSecure}
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
database.default.port = {$dbPort}
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_unicode_ci

#--------------------------------------------------------------------
# ENCRYPTION
#--------------------------------------------------------------------

encryption.key = {$encKey}

#--------------------------------------------------------------------
# SESSION  (file-based — no database table required)
#--------------------------------------------------------------------

session.driver = '{$sessionDriver}'
session.savePath = '{$sessionPath}'
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

# Google Drive Picker (https://console.cloud.google.com/)
cloud.googlePickerApiKey =
cloud.googlePickerClientId =
cloud.googlePickerAppId =

# Dropbox Chooser (https://www.dropbox.com/developers/apps)
cloud.dropboxAppKey =

# OneDrive File Picker (https://portal.azure.com/)
cloud.onedriveClientId =
ENV;

        if (file_put_contents($envPath, $content) === false) {
            throw new \RuntimeException('تعذّر الكتابة إلى: ' . $envPath);
        }
    }

    // ── JSON response helper ──────────────────────────────────────

    private function json(array $data, int $status = 200): ResponseInterface
    {
        // Always include a fresh CSRF token so the wizard JS can rotate its
        // stored hash after each step — prevents 419 errors on subsequent POSTs.
        $data['csrf_token_name'] = csrf_token();
        $data['csrf_hash']       = csrf_hash();

        return $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
