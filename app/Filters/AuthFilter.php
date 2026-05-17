<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('logged_in')) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['error' => lang('App.session_expired')]);
            }

            $session->setFlashdata('error', lang('App.session_expired'));

            return redirect()->to(site_url('auth/login'));
        }

        // Check force password change
        if ($session->get('force_password_change')) {
            $currentPath = trim(uri_string(), '/');
            $allowedPaths = ['auth/change-password', 'auth/logout', 'lang/ar', 'lang/en'];

            if (! in_array($currentPath, $allowedPaths, true)) {
                return redirect()->to(site_url('auth/change-password'));
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed
    }
}
