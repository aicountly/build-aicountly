<?php

namespace App\Services;

/**
 * Pure rules for turning Console's answer into Build roles. No I/O here, so
 * every branch is unit-testable (tests/unit/ConsoleAccessPolicyTest.php).
 *
 * Console contract (app_code `build`):
 *   - SSO exchange / introspect return `{active, global_superadmin, user, ...}`
 *     and, from the role-aware Console onward, `console_user_id`,
 *     `app_roles` (role slugs in this app; a Console superadmin gets all),
 *     `app_superadmin` and `authz_version`.
 *   - user-access (pull) returns `{exists, active, app_access, app_superadmin,
 *     console_user_id, email, name, app_roles, authz_version}`.
 *
 * Build's catalog has exactly one role: `super_admin` (Build Administrator).
 * There is no default role — a user Console has not assigned does not get in.
 */
final class ConsoleAccessPolicy
{
    /** Roles Build knows how to enforce. Console slugs outside this list grant nothing. */
    public const CATALOG = ['super_admin'];

    public const MSG_NOT_ASSIGNED = 'Access not assigned. Ask a Console superadmin to assign you a role.';

    /** How long a previously synced role is trusted while Console cannot be reached. */
    public const OFFLINE_GRACE_SECONDS = 12 * 3600;

    /**
     * Roles for a sign-in, from an SSO exchange / introspect payload.
     *
     * @param array<string,mixed> $identity
     * @return array{allowed:bool, roles:list<string>, app_superadmin:bool, source:string, message:?string}
     */
    public static function rolesFromIdentity(array $identity): array
    {
        $active        = (bool) ($identity['active'] ?? false);
        $global        = (bool) ($identity['global_superadmin'] ?? false);
        $appSuperadmin = (bool) ($identity['app_superadmin'] ?? false);

        if (array_key_exists('app_roles', $identity) && is_array($identity['app_roles'])) {
            $roles = self::normalizeRoles($identity['app_roles'], $appSuperadmin);
            $allowed = ($active || $global || $appSuperadmin) && $roles !== [];

            return [
                'allowed'        => $allowed,
                'roles'          => $allowed ? $roles : [],
                'app_superadmin' => $appSuperadmin,
                'source'         => 'app_roles',
                'message'        => $allowed ? null : self::MSG_NOT_ASSIGNED,
            ];
        }

        // Console without role assignment (no `app_roles` key). Build has only
        // an administrator role, so only a Console global superadmin may in.
        return [
            'allowed'        => $global,
            'roles'          => $global ? self::CATALOG : [],
            'app_superadmin' => $global,
            'source'         => 'legacy_global_superadmin',
            'message'        => $global ? null : self::MSG_NOT_ASSIGNED,
        ];
    }

    /**
     * Roles from a user-access (pull) answer, or null when Console says the
     * user no longer has Build access.
     *
     * @param array<string,mixed> $data
     * @return list<string>|null
     */
    public static function rolesFromUserAccess(array $data): ?array
    {
        $appSuperadmin = (bool) ($data['app_superadmin'] ?? false);

        if (empty($data['exists']) || empty($data['active'])) {
            return null;
        }
        if (empty($data['app_access']) && ! $appSuperadmin) {
            return null;
        }

        $roles = self::normalizeRoles(is_array($data['app_roles'] ?? null) ? $data['app_roles'] : [], $appSuperadmin);

        return $roles === [] ? null : $roles;
    }

    /**
     * Keep only catalog roles; an app superadmin holds the whole catalog.
     *
     * @param array<mixed> $raw
     * @return list<string>
     */
    public static function normalizeRoles(array $raw, bool $appSuperadmin): array
    {
        if ($appSuperadmin) {
            return self::CATALOG;
        }

        $roles = [];
        foreach ($raw as $slug) {
            if (! is_string($slug)) {
                continue;
            }
            $slug = strtolower(trim($slug));
            if (in_array($slug, self::CATALOG, true) && ! in_array($slug, $roles, true)) {
                $roles[] = $slug;
            }
        }

        return $roles;
    }

    /**
     * May a stored role still be used when Console could not be asked?
     */
    public static function withinOfflineGrace(?string $rolesSyncedAt, int $now): bool
    {
        if ($rolesSyncedAt === null || trim($rolesSyncedAt) === '') {
            return false;
        }

        $syncedAt = strtotime($rolesSyncedAt);
        if ($syncedAt === false) {
            return false;
        }

        return ($now - $syncedAt) < self::OFFLINE_GRACE_SECONDS;
    }
}
