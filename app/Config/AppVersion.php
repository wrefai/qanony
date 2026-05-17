<?php

namespace Config;

/**
 * AppVersion — central version constants & system requirements.
 *
 * Bump APP_VERSION on every release. The InstallCheckFilter compares
 * this value against writable/install.lock to decide whether to redirect
 * to /update.
 */
class AppVersion
{
    /** Current application version (SemVer) */
    public const APP_VERSION = '1.0.0';

    /** Minimum PHP version required */
    public const MIN_PHP = '8.0.0';

    /** Required PHP extensions */
    public const REQUIRED_EXTENSIONS = [
        'pdo',
        'pdo_mysql',
        'mbstring',
        'json',
        'xml',
        'fileinfo',
        'intl',
    ];

    /** Directories that must be writable */
    public const WRITABLE_DIRS = [
        'writable',
        'writable/cache',
        'writable/logs',
        'writable/session',
        'writable/uploads',
    ];

    /**
     * Check all system requirements.
     *
     * @return array{ok: bool, items: list<array{label: string, ok: bool, detail: string}>}
     */
    public static function checkRequirements(): array
    {
        $items = [];

        // PHP version
        $phpOk = version_compare(PHP_VERSION, self::MIN_PHP, '>=');
        $items[] = [
            'label'  => 'إصدار PHP ≥ ' . self::MIN_PHP,
            'ok'     => $phpOk,
            'detail' => 'PHP ' . PHP_VERSION . ' مُثبَّت',
        ];

        // Extensions
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $ok = extension_loaded($ext);
            $items[] = [
                'label'  => 'امتداد PHP: ' . $ext,
                'ok'     => $ok,
                'detail' => $ok ? 'متوفر' : 'غير متوفر — يجب تفعيله في php.ini',
            ];
        }

        // Writable directories
        $base = rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach (self::WRITABLE_DIRS as $rel) {
            $path = $base . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $ok   = is_dir($path) && is_writable($path);
            $items[] = [
                'label'  => 'مجلد قابل للكتابة: ' . $rel,
                'ok'     => $ok,
                'detail' => $ok ? 'قابل للكتابة' : 'غير موجود أو غير قابل للكتابة',
            ];
        }

        $allOk = array_reduce($items, fn($carry, $i) => $carry && $i['ok'], true);

        return ['ok' => $allOk, 'items' => $items];
    }

    // ── install.lock helpers ──────────────────────────────────────

    /** Absolute path to the lock file. */
    public static function lockPath(): string
    {
        return rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'install.lock';
    }

    /** Read and decode the lock file; returns null if not present / corrupt. */
    public static function readLock(): ?array
    {
        $path = self::lockPath();
        if (! file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /** Write the lock file (create or overwrite). */
    public static function writeLock(string $version): void
    {
        $lock = self::readLock() ?? [];
        $now  = date('Y-m-d H:i:s');

        if (! isset($lock['installed_at'])) {
            $lock['installed_at'] = $now;
        }

        $lock['version']         = $version;
        $lock['last_updated_at'] = $now;

        file_put_contents(self::lockPath(), json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Is the application installed (lock exists)? */
    public static function isInstalled(): bool
    {
        return self::readLock() !== null;
    }

    /** Does the lock version match APP_VERSION? */
    public static function isUpToDate(): bool
    {
        $lock = self::readLock();
        if ($lock === null) {
            return false;
        }
        return version_compare($lock['version'] ?? '0.0.0', self::APP_VERSION, '>=');
    }
}
