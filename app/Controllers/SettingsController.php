<?php

namespace App\Controllers;

use App\Models\AuditLogModel;

class SettingsController extends BaseController
{
    protected AuditLogModel $auditModel;

    /** Keys that are allowed to be read/written via the settings form. */
    private const CLOUD_KEYS = [
        'cloud.googlePickerApiKey',
        'cloud.googlePickerClientId',
        'cloud.googlePickerAppId',
        'cloud.dropboxAppKey',
        'cloud.onedriveClientId',
    ];

    public function __construct()
    {
        $this->auditModel = new AuditLogModel();
    }

    public function index()
    {
        $current = [];
        foreach (self::CLOUD_KEYS as $key) {
            $current[$key] = env($key, '');
        }

        return view('settings/index', $this->viewData([
            'title'   => lang('App.settings'),
            'current' => $current,
        ]));
    }

    public function update()
    {
        $envPath = ROOTPATH . '.env';

        if (! is_writable($envPath)) {
            return redirect()->back()->with('error', lang('App.settings_env_not_writable'));
        }

        $content = file_get_contents($envPath);

        foreach (self::CLOUD_KEYS as $key) {
            $value = trim($this->request->getPost($key) ?? '');

            // Strip characters that are illegal or dangerous in a .env value:
            // newlines would inject new keys; quotes would break the line format.
            $value = str_replace(["\r", "\n", "\0"], '', $value);

            // Build the safe .env representation.
            // Always single-quote the value so spaces, =, and # are safe.
            // Escape any single-quotes inside the value by ending the string,
            // adding an escaped quote, then reopening (shell-quoting convention).
            $quotedValue = "'" . str_replace("'", "'\\''", $value) . "'";

            // Replace existing key = ... line (with or without value).
            // Use preg_replace_callback so $quotedValue is never treated as a
            // replacement pattern — this eliminates the $1 / \1 injection risk.
            $pattern = '/^(' . preg_quote($key, '/') . '\s*=).*$/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback(
                    $pattern,
                    static function (array $m) use ($quotedValue): string {
                        return $m[1] . ' ' . $quotedValue;
                    },
                    $content
                );
            } else {
                // Key line not found — append it.
                $content .= PHP_EOL . $key . ' = ' . $quotedValue;
            }
        }

        file_put_contents($envPath, $content);

        $this->auditModel->log(
            'settings_updated',
            'Cloud API settings updated',
            'settings',
            null
        );

        return redirect()->to(site_url('settings'))->with('success', lang('App.settings_saved'));
    }
}
