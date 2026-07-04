<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Independent JWT auth for the Build portal.
 *
 * Usage in Routes.php:
 *   $routes->group('', ['filter' => 'jwt'], function ($routes) { ... });
 *   $routes->group('', ['filter' => 'jwt:super_admin'], function ($routes) { ... });
 *
 * The argument (if any) is enforced by RoleFilter — JwtFilter only decodes
 * the token and stashes buildUser onto the request.
 */
class JwtFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('response');

        $header = $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\\s+(\\S+)$/i', $header, $m)) {
            return build_json_unauthorized('Missing or malformed Authorization header.');
        }

        try {
            $payload = Services::jwt()->decode($m[1]);
        } catch (\Throwable $e) {
            return service('response')
                ->setStatusCode(503)
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Server misconfigured: BUILD_JWT_SECRET.',
                    'data'    => null,
                    'errors'  => [],
                ]);
        }

        if (! $payload) {
            return build_json_unauthorized('Invalid or expired token.');
        }

        $request->buildUser = [
            'id'    => (int) $payload['sub'],
            'email' => (string) ($payload['email'] ?? ''),
            'roles' => array_values($payload['roles'] ?? []),
        ];

        // Optional inline role assertion via filter argument.
        if (! empty($arguments)) {
            $required = array_values(array_filter($arguments));
            if ($required !== []) {
                $userRoles = $request->buildUser['roles'];
                $ok        = (bool) array_intersect($required, $userRoles);
                if (! $ok) {
                    return build_json_forbidden('Forbidden — required role: ' . implode(', ', $required) . '.');
                }
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
