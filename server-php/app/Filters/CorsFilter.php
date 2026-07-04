<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Env-driven CORS. Origins come from BUILD_ALLOWED_ORIGINS (comma-separated).
 * The framework's Config\Cors is intentionally left permissive because this
 * filter is the source of truth.
 */
class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $origin     = (string) ($request->getServer('HTTP_ORIGIN') ?? '');
        $allowedRaw = (string) env('BUILD_ALLOWED_ORIGINS', '');
        $allowed    = array_values(array_filter(array_map('trim', explode(',', $allowedRaw))));

        $allowOrigin = in_array($origin, $allowed, true) ? $origin : ($allowed[0] ?? '*');

        $response = service('response');
        $response->setHeader('Access-Control-Allow-Origin', $allowOrigin);
        $response->setHeader('Vary', 'Origin');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Authorization,Content-Type,X-Worker-Token,X-Source,X-Requested-With');
        $response->setHeader('Access-Control-Max-Age', '3600');

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $response->setStatusCode(204);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Headers already applied in before().
    }
}
