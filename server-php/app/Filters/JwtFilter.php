<?php

namespace App\Filters;

use App\Models\UsersModel;
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
 * JwtFilter decodes the token, loads the build_users row and stashes
 * buildUser (with the roles currently stored for that user — Console is the
 * source, see ConsoleRoleSyncService) onto the request. Tokens issued before
 * sessions_revoked_at, or for a user whose role was never synced from
 * Console, are refused with 401.
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

        // Roles are read from build_users on every request, not trusted from
        // the token: Console can revoke or change them at any time and the
        // sign-in / refresh sync writes the result there.
        try {
            $user = (new UsersModel())->find((int) ($payload['sub'] ?? 0));
        } catch (\Throwable $e) {
            log_message('error', 'JwtFilter user lookup failed: ' . $e->getMessage());

            return build_json_error('Could not verify your Build session.', [], 503);
        }

        if (! $user || ($user['status'] ?? '') !== 'active') {
            return build_json_unauthorized('User not active.');
        }
        if (empty($user['roles_synced_at'])) {
            // Issued before Build took roles from Console — sign in again so
            // the role is derived from Console's assignment.
            return build_json_unauthorized('Sign in again from Console to confirm your Build role.');
        }
        if (self::tokenPredatesRevocation($payload, $user)) {
            return build_json_unauthorized('This session has ended. Sign in again from Console.');
        }

        $request->buildUser = [
            'id'    => (int) $user['id'],
            'email' => (string) $user['email'],
            'roles' => UsersModel::rolesOf($user),
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

    /**
     * True when the token was issued before Build ended the user's sessions
     * (build_users.sessions_revoked_at, set when Console revokes access).
     *
     * @param array<string,mixed> $payload decoded JWT
     * @param array<string,mixed> $user    build_users row
     */
    public static function tokenPredatesRevocation(array $payload, array $user): bool
    {
        $revokedAt = $user['sessions_revoked_at'] ?? null;
        if ($revokedAt === null || $revokedAt === '') {
            return false;
        }

        $cutoff = strtotime((string) $revokedAt);
        if ($cutoff === false) {
            return true;
        }

        return (int) ($payload['iat'] ?? 0) < $cutoff;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
