<?php

namespace App\Libraries;

use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;
use Throwable;

/**
 * Custom exception handler that gracefully recovers from expired/invalid
 * CSRF tokens.
 *
 * Browsers refreshing a previously-POSTed URL or re-submitting a stale
 * form would normally trigger a generic "Whoops!" production page when
 * CSRF verification fails. That's a terrible experience for users who
 * just hit refresh on the login screen.
 *
 * Instead, for AJAX requests we return a clean JSON 403 (so the upload
 * UI can surface a real message and retry with a fresh token), and for
 * regular requests we redirect back to where they came from with a
 * flash error message.
 */
class CsrfAwareExceptionHandler extends ExceptionHandler
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
        if ($exception instanceof SecurityException) {
            // AJAX/JSON requests: return JSON 403 so the JS layer can
            // refresh the CSRF token and retry.
            if ($request->isAJAX() || $request->hasHeader('X-Requested-With')) {
                $response->setStatusCode(403)
                    ->setContentType('application/json')
                    ->setHeader('X-CSRF-TOKEN', csrf_hash())
                    ->setBody(json_encode([
                        'status'    => 'error',
                        'message'   => 'Security token expired. Please retry.',
                        '_csrfError' => true,
                        'csrf_hash' => csrf_hash(),
                    ]))
                    ->send();
                exit($exitCode);
            }

            // Regular HTML request: redirect to the previous page with
            // a friendly flash message. Falls back to the login page if
            // we can't determine a referer.
            $referer = $request->getServer('HTTP_REFERER');
            $target  = $referer ?: site_url('auth/login');

            // Set a flash message via the session if available.
            try {
                session()->setFlashdata('error', lang('App.session_expired') ?: 'Your session expired. Please try again.');
            } catch (Throwable $e) {
                // Session unavailable — just redirect silently.
            }

            $response->redirect($target)->send();
            exit($exitCode);
        }

        // Fall back to the default CI4 handler for everything else.
        parent::handle($exception, $request, $response, $statusCode, $exitCode);
    }
}
