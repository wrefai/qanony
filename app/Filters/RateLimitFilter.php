<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Rate-limits POST requests to login (brute-force protection at the IP level).
 *
 * Uses a simple file-cache approach: allows max N attempts per window per IP.
 * This is a belt-and-suspenders complement to the per-user account lockout.
 */
class RateLimitFilter implements FilterInterface
{
    /** Maximum login POST attempts per window. */
    private const MAX_ATTEMPTS = 10;

    /** Window size in seconds. */
    private const WINDOW = 300; // 5 minutes

    public function before(RequestInterface $request, $arguments = null)
    {
        // Only rate-limit POST (the actual login submit)
        if ($request->getMethod() !== 'post') {
            return;
        }

        try {
            $ip  = $request->getIPAddress();
            $key = 'ratelimit_login_' . md5($ip);

            $cache = \Config\Services::cache();
            $data  = $cache->get($key);

            if ($data === null) {
                $data = ['count' => 0, 'expires' => time() + self::WINDOW];
            }

            // Reset if window expired
            if (time() > $data['expires']) {
                $data = ['count' => 0, 'expires' => time() + self::WINDOW];
            }

            $data['count']++;
            $cache->save($key, $data, self::WINDOW);

            if ($data['count'] > self::MAX_ATTEMPTS) {
                $remaining = (int) ceil(($data['expires'] - time()) / 60);

                if ($request->isAJAX()) {
                    return service('response')
                        ->setStatusCode(429)
                        ->setJSON(['error' => lang('App.rate_limit_exceeded', [$remaining])]);
                }

                return redirect()->back()
                    ->with('error', lang('App.rate_limit_exceeded', [$remaining]));
            }
        } catch (\Throwable $e) {
            // Cache unavailable (e.g. unwritable writable/cache/ on shared hosting).
            // Skip rate limiting rather than blocking all login attempts.
            log_message('warning', 'RateLimitFilter: cache error, skipping rate limit: ' . $e->getMessage());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed
    }
}
