<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides shared functionality for all Qanony controllers.
 *
 * Features:
 * - viewData(): merges shared layout variables (user, permissions, locale, direction)
 * - jsonResponse(): standardized JSON responses
 * - can(): permission check helper
 * - getDirection(): returns 'rtl' or 'ltr' based on current locale
 */
abstract class BaseController extends Controller
{
    /**
     * Currently logged-in user session data.
     */
    protected array $currentUser = [];

    /**
     * Permission slugs for the current user.
     *
     * @var list<string>
     */
    protected array $currentPermissions = [];

    /**
     * @var list<string>
     */
    protected $helpers = ['form', 'url'];

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // Apply locale + populate user info from the session. Both reads
        // trigger session_start() under the hood — if the session backend
        // (DB handler / writable dir) is broken, this can throw and turn
        // every page into a generic "Whoops" production error. Wrap the
        // whole block defensively: on failure, log a warning and fall
        // back to the configured defaultLocale with no logged-in user.
        $appConfig = config('App');
        try {
            $sessionLocale = session()->get('locale');
            if ($sessionLocale && in_array($sessionLocale, $appConfig->supportedLocales, true)) {
                $request->setLocale($sessionLocale);
            } else {
                $request->setLocale($appConfig->defaultLocale);
            }

            // Populate current user info from session
            if (session()->get('logged_in')) {
                $this->currentUser = [
                    'id'        => session()->get('user_id'),
                    'username'  => session()->get('username'),
                    'full_name' => session()->get('full_name'),
                    'email'     => session()->get('email'),
                    'role_id'   => session()->get('role_id'),
                    'role_name' => session()->get('role_name'),
                ];
                $this->currentPermissions = session()->get('permissions') ?? [];
            }
        } catch (\Throwable $e) {
            log_message('error', 'BaseController session/locale init failed: ' . $e->getMessage());
            $request->setLocale($appConfig->defaultLocale);
        }
    }

    /**
     * Build view data array with shared layout variables merged in.
     *
     * Every view rendered through this method receives:
     * - currentUser, currentPermissions
     * - locale, direction, otherLocale
     * - appName, csrf_token_name, csrf_hash
     *
     * @param array $extra Additional data specific to the view
     */
    protected function viewData(array $extra = []): array
    {
        $locale = $this->request->getLocale();

        $shared = [
            'currentUser'        => $this->currentUser,
            'currentPermissions' => $this->currentPermissions,
            'locale'             => $locale,
            'direction'          => $this->getDirection(),
            'otherLocale'        => ($locale === 'ar') ? 'en' : 'ar',
            'otherLocaleName'    => ($locale === 'ar') ? 'English' : 'العربية',
            'appName'            => lang('App.app_name'),
            'appSubtitle'        => lang('App.app_subtitle'),
            'csrf_token_name'    => csrf_token(),
            'csrf_hash'          => csrf_hash(),
            'theme'              => session()->get('theme') ?? 'light',
        ];

        return array_merge($shared, $extra);
    }

    /**
     * Return a standardized JSON response.
     *
     * @param array $data    Response payload
     * @param int   $status  HTTP status code
     */
    protected function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setContentType('application/json')
            ->setJSON($data);
    }

    /**
     * Check whether the current user has a given permission.
     */
    protected function can(string $permission): bool
    {
        return in_array($permission, $this->currentPermissions, true);
    }

    /**
     * Get text direction based on current locale.
     */
    protected function getDirection(): string
    {
        return ($this->request->getLocale() === 'ar') ? 'rtl' : 'ltr';
    }
}
