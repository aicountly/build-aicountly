<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Playwright worker → Build inbound authentication.
 *
 * Worker sends header:  X-Worker-Token: <BUILD_WORKER_API_TOKEN>
 * Constant-time comparison; never logs the secret.
 *
 * Applied only to `/api/v1/internal/worker/*` routes so a leaked worker token
 * cannot access user-facing endpoints.
 */
class WorkerAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('response');

        $expected = (string) env('BUILD_WORKER_API_TOKEN', '');
        $provided = (string) $request->getHeaderLine('X-Worker-Token');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return build_json_unauthorized('Invalid worker token.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
