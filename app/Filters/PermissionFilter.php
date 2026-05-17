<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermissionFilter implements FilterInterface
{
    /**
     * @param array|null $arguments Permission names, e.g. ['users.read']
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (empty($arguments)) {
            return;
        }

        $session = session();
        $permissions = $session->get('permissions') ?? [];

        foreach ($arguments as $required) {
            if (! in_array($required, $permissions, true)) {
                if ($request->isAJAX()) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON(['error' => lang('App.forbidden')]);
                }

                return redirect()->back()->with('error', lang('App.forbidden'));
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed
    }
}
