<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\AppVersion;
use Psr\Log\LoggerInterface;

/**
 * UpdateController — one-click database migration & version bump.
 *
 * Publicly accessible (no auth required) so it can run even when the
 * auth system depends on DB tables that don't yet exist.
 *
 * Routes
 * ──────
 *   GET  /update       → update page (show installed vs latest version)
 *   POST /update/run   → run migrations, bump install.lock version
 */
class UpdateController extends Controller
{
    protected $helpers = ['url', 'form'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    // ── GET /update ───────────────────────────────────────────────

    public function index(): string
    {
        $lock = AppVersion::readLock();

        // Count pending (not-yet-applied) migrations using the CI4 public API.
        // findMigrations() returns every discovered migration object keyed by UID;
        // getHistory() returns the already-applied rows from the migrations table.
        // The difference is the pending count.
        $pendingCount = 0;
        try {
            $migrate  = \Config\Services::migrations();
            $allFiles = $migrate->findMigrations();                  // array<uid, object{version,...}>
            $history  = array_column($migrate->getHistory(), 'version'); // applied versions
            foreach ($allFiles as $migration) {
                if (! in_array($migration->version, $history, true)) {
                    $pendingCount++;
                }
            }
        } catch (\Throwable $e) {
            $pendingCount = -1; // unknown — silently degrade
        }

        return view('install/update', [
            'title'          => 'تحديث النظام',
            'locale'         => 'ar',
            'direction'      => 'rtl',
            'installedVer'   => $lock['version'] ?? '—',
            'latestVer'      => AppVersion::APP_VERSION,
            'installedAt'    => $lock['installed_at'] ?? null,
            'lastUpdatedAt'  => $lock['last_updated_at'] ?? null,
            'isUpToDate'     => AppVersion::isUpToDate(),
            'pendingCount'   => $pendingCount,
            'csrfName'       => csrf_token(),
            'csrfHash'       => csrf_hash(),
        ]);
    }

    // ── POST /update/run ──────────────────────────────────────────

    public function run(): ResponseInterface
    {
        // Refuse to run if there is nothing to update — prevents anonymous
        // callers from triggering a no-op migration pass on a live system.
        if (AppVersion::isInstalled() && AppVersion::isUpToDate()) {
            return $this->json([
                'ok'      => false,
                'message' => 'النظام محدَّث بالفعل. لا توجد ترحيلات معلَّقة.',
            ], 403);
        }

        $log = [];

        // Run migrations
        try {
            $migrate = \Config\Services::migrations();
            $migrate->latest();
            $log[] = ['ok' => true, 'label' => 'Migrations', 'msg' => 'جميع الترحيلات مُطبَّقة بنجاح.'];
        } catch (\Throwable $e) {
            $log[] = ['ok' => false, 'label' => 'Migrations', 'msg' => $e->getMessage()];

            return $this->json([
                'ok'      => false,
                'message' => 'فشل تطبيق الترحيلات.',
                'items'   => $log,
            ]);
        }

        // Bump lock version
        try {
            AppVersion::writeLock(AppVersion::APP_VERSION);
            $log[] = ['ok' => true, 'label' => 'Version Lock', 'msg' => 'تم تحديث ملف القفل إلى v' . AppVersion::APP_VERSION];
        } catch (\Throwable $e) {
            $log[] = ['ok' => false, 'label' => 'Version Lock', 'msg' => $e->getMessage()];

            return $this->json([
                'ok'      => false,
                'message' => 'فشل تحديث ملف القفل.',
                'items'   => $log,
            ]);
        }

        return $this->json([
            'ok'        => true,
            'message'   => 'تم التحديث بنجاح إلى v' . AppVersion::APP_VERSION,
            'version'   => AppVersion::APP_VERSION,
            'loginUrl'  => site_url('auth/login'),
            'items'     => $log,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function json(array $data, int $status = 200): ResponseInterface
    {
        // Rotate CSRF token so the update page JS can use it on retry.
        $data['csrf_token_name'] = csrf_token();
        $data['csrf_hash']       = csrf_hash();

        return $this->response
            ->setStatusCode($status)
            ->setContentType('application/json')
            ->setBody(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
