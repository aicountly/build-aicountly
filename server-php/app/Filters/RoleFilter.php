<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Route-level role enforcement.
 *
 *   $routes->get('bots/mode', '...', ['filter' => 'role:super_admin']);
 *
 * Requires JwtFilter to have populated request->buildUser first.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('response');

        $user = $request->buildUser ?? null;
        if (! $user) {
            return build_json_unauthorized('Not authenticated.');
        }

        $allowed = array_values(array_filter($arguments ?? []));
        if ($allowed === []) {
            return; // no role requirement
        }

        $userRoles = array_values($user['roles'] ?? []);
        $ok        = (bool) array_intersect($allowed, $userRoles);

        if (! $ok) {
            return build_json_forbidden(
                'Forbidden — required role: ' . implode(', ', $allowed) . '.'
            );
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
