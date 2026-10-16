<?php

namespace App\Services;

use App\Models\UsersModel;
use Config\Services;

/**
 * Keeps build_users.role in step with the roles Console assigns in app `build`.
 *
 * - signIn(): applied to every SSO exchange / Console-session introspect.
 * - refresh(): applied on POST /v1/auth/refresh — pulls user-access from
 *   Console when CONSOLE_API_URL + CONSOLE_API_TOKEN are set.
 *
 * Rules (see ConsoleAccessPolicy for the role maths):
 *   revoked in Console            → refuse, clear the local role, end sessions
 *   Console unreachable           → keep the stored role only if it was synced < 12 h ago
 *   user-access not configured    → keep the stored role (logged warning); production
 *                                   must set CONSOLE_API_URL and CONSOLE_API_TOKEN
 *   never synced under these rules→ refuse; the user signs in again through Console
 */
class ConsoleRoleSyncService
{
    private UsersModel $users;
    private ConsoleUserAccessClient $client;

    /** @var callable(): int */
    private $clock;

    public function __construct(?UsersModel $users = null, ?ConsoleUserAccessClient $client = null, ?callable $clock = null)
    {
        $this->users  = $users ?? new UsersModel();
        $this->client = $client ?? Services::consoleUserAccess();
        $this->clock  = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<string,mixed> $identity SSO exchange / introspect payload
     * @return array{ok:true, user:array<string,mixed>, roles:list<string>, decision:array<string,mixed>}
     *        |array{ok:false, status:int, message:string}
     */
    public function signIn(array $identity, ?string $ip = null): array
    {
        $decision    = ConsoleAccessPolicy::rolesFromIdentity($identity);
        $consoleUser = is_array($identity['user'] ?? null) ? $identity['user'] : [];
        $email       = strtolower(trim((string) ($consoleUser['email'] ?? '')));
        $name        = trim((string) ($consoleUser['name'] ?? ''));
        $consoleId   = (int) ($identity['console_user_id'] ?? ($consoleUser['id'] ?? 0));

        $user = $this->users->findByConsoleUserId($consoleId);
        if (! $user && $email !== '') {
            $user = $this->users->findByEmail($email);
        }

        if (! $decision['allowed']) {
            if ($user && UsersModel::rolesOf($user) !== []) {
                $this->endSessions((int) $user['id'], 'console_sign_in_without_role');
            }

            return $this->fail(403, (string) $decision['message']);
        }

        if ($email === '') {
            return $this->fail(502, 'Console identity did not return a user email.');
        }

        $now     = ($this->clock)();
        $roles   = $decision['roles'];
        $syncRow = [
            'role'              => $roles[0],
            'console_user_id'   => $consoleId > 0 ? $consoleId : null,
            'roles_synced_at'   => date('Y-m-d H:i:s', $now),
            'authz_version'     => $this->authzVersion($identity),
            'console_app_roles' => json_encode(array_values((array) ($identity['app_roles'] ?? [])), JSON_UNESCAPED_SLASHES),
        ];

        if (! $user) {
            $userId = $this->users->insert($syncRow + [
                'email'         => $email,
                'name'          => $name !== '' ? $name : $email,
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'status'        => 'active',
            ]);
            if (! $userId) {
                return $this->fail(500, 'Could not provision Build user from Console identity.');
            }
        } else {
            if (($user['status'] ?? 'active') !== 'active') {
                return $this->fail(403, 'Build user account is inactive.');
            }
            $userId = (int) $user['id'];
        }

        $this->users->update($userId, $syncRow + [
            'last_login_at'   => date('Y-m-d H:i:s', $now),
            'last_login_ip'   => $ip,
            'failed_attempts' => 0,
        ]);

        $fresh = $this->users->find((int) $userId);

        return ['ok' => true, 'user' => $fresh, 'roles' => UsersModel::rolesOf($fresh), 'decision' => $decision];
    }

    /**
     * Re-check a signed-in user's roles before issuing a fresh token.
     *
     * @param array<string,mixed> $user build_users row
     * @return array{ok:true, user:array<string,mixed>, roles:list<string>, source:string}
     *        |array{ok:false, status:int, message:string}
     */
    public function refresh(array $user): array
    {
        $userId = (int) $user['id'];
        $now    = ($this->clock)();
        $lookup = $this->client->lookup(
            isset($user['console_user_id']) ? (int) $user['console_user_id'] : null,
            (string) ($user['email'] ?? ''),
        );

        switch ($lookup['status']) {
            case ConsoleUserAccessClient::STATUS_OK:
                $roles = ConsoleAccessPolicy::rolesFromUserAccess($lookup['data']);
                if ($roles === null) {
                    $this->endSessions($userId, 'console_user_access_revoked', $lookup['data']);

                    return $this->fail(403, ConsoleAccessPolicy::MSG_NOT_ASSIGNED);
                }

                $consoleId = (int) ($lookup['data']['console_user_id'] ?? 0);
                $this->users->update($userId, [
                    'role'              => $roles[0],
                    'console_user_id'   => $consoleId > 0 ? $consoleId : ($user['console_user_id'] ?? null),
                    'roles_synced_at'   => date('Y-m-d H:i:s', $now),
                    'authz_version'     => $this->authzVersion($lookup['data']),
                    'console_app_roles' => json_encode(array_values((array) ($lookup['data']['app_roles'] ?? [])), JSON_UNESCAPED_SLASHES),
                ]);
                $fresh = $this->users->find($userId);

                return ['ok' => true, 'user' => $fresh, 'roles' => UsersModel::rolesOf($fresh), 'source' => 'console'];

            case ConsoleUserAccessClient::STATUS_NOT_CONFIGURED:
                log_message(
                    'warning',
                    'Build token refresh without a Console role check: set CONSOLE_API_URL and CONSOLE_API_TOKEN (production must).',
                );
                if (empty($user['roles_synced_at'])) {
                    return $this->fail(401, 'Sign in again from Console to confirm your Build role.');
                }

                return $this->keepStored($user, 'stored_unverified');

            default: // unavailable
                if (! ConsoleAccessPolicy::withinOfflineGrace($user['roles_synced_at'] ?? null, $now)) {
                    return $this->fail(503, 'Could not confirm your Build access with Console. Sign in again from Console.');
                }

                return $this->keepStored($user, 'stored_offline_grace');
        }
    }

    /**
     * Clear the local role and invalidate every Build JWT issued so far.
     *
     * @param array<string,mixed>|null $consoleAnswer
     */
    public function endSessions(int $userId, string $reason, ?array $consoleAnswer = null): void
    {
        $stamp = date('Y-m-d H:i:s', ($this->clock)());
        $row   = [
            'role'                => null,
            'sessions_revoked_at' => $stamp,
        ];
        if ($consoleAnswer !== null) {
            $row['roles_synced_at']   = $stamp;
            $row['authz_version']     = $this->authzVersion($consoleAnswer);
            $row['console_app_roles'] = json_encode(array_values((array) ($consoleAnswer['app_roles'] ?? [])), JSON_UNESCAPED_SLASHES);
        }
        $this->users->update($userId, $row);

        try {
            $this->users->revokeSessionRows($userId, $stamp);
        } catch (\Throwable $e) {
            log_message('error', 'Could not revoke build_sessions rows: ' . $e->getMessage());
        }

        Services::auditService()->log('auth.sessions_revoked', [
            'actor_id'    => $userId,
            'entity_type' => 'build_user',
            'entity_id'   => $userId,
            'risk_level'  => 'high',
            'metadata'    => ['reason' => $reason],
        ]);
    }

    /**
     * @param array<string,mixed> $user
     * @return array{ok:true, user:array<string,mixed>, roles:list<string>, source:string}
     *        |array{ok:false, status:int, message:string}
     */
    private function keepStored(array $user, string $source): array
    {
        $roles = UsersModel::rolesOf($user);
        if ($roles === []) {
            return $this->fail(403, ConsoleAccessPolicy::MSG_NOT_ASSIGNED);
        }

        return ['ok' => true, 'user' => $user, 'roles' => $roles, 'source' => $source];
    }

    /** @param array<string,mixed> $payload */
    private function authzVersion(array $payload): ?string
    {
        $v = $payload['authz_version'] ?? null;
        if ($v === null || $v === '' || is_array($v)) {
            return null;
        }

        return substr((string) $v, 0, 64);
    }

    /** @return array{ok:false, status:int, message:string} */
    private function fail(int $status, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message];
    }
}
