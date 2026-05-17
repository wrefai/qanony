<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\AppVersion;

/**
 * InstallCheckFilter — global before filter.
 *
 * Runs on every request (added to globals in Config/Filters.php).
 * Logic:
 *   1. If request is for /install* or /update* → pass through unconditionally.
 *   2. If lock does not exist → redirect to /install.
 *   3. If lock exists but version < APP_VERSION → redirect to /update.
 *   4. Otherwise → pass through.
 */
class InstallCheckFilter implements FilterInterface
{
    /** URI prefixes that are always bypassed */
    private const BYPASS = ['install', 'update'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $uri    = trim(uri_string(), '/');
        $first  = explode('/', $uri)[0] ?? '';

        // Always allow access to install / update routes
        if (in_array($first, self::BYPASS, true)) {
            return;
        }

        // Not installed → redirect to wizard
        if (! AppVersion::isInstalled()) {
            return redirect()->to(site_url('install'));
        }

        // Installed but outdated → redirect to update page
        if (! AppVersion::isUpToDate()) {
            return redirect()->to(site_url('update'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nothing
    }
}
