<?php

namespace App\Models;

use CodeIgniter\Model;

class UsersModel extends Model
{
    protected $table         = 'build_users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'email', 'name', 'password_hash', 'status', 'role',
        'last_login_at', 'last_login_ip', 'failed_attempts',
        'console_user_id', 'roles_synced_at', 'authz_version', 'console_app_roles', 'sessions_revoked_at',
    ];

    public function findByConsoleUserId(int $consoleUserId): ?array
    {
        if ($consoleUserId <= 0) {
            return null;
        }

        return $this->where('console_user_id', $consoleUserId)->first();
    }

    /**
     * Local role list for a build_users row. A NULL/empty role grants nothing —
     * there is no default role.
     *
     * @param array<string,mixed> $user
     * @return list<string>
     */
    public static function rolesOf(array $user): array
    {
        $role = trim((string) ($user['role'] ?? ''));

        return $role === '' ? [] : [$role];
    }

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Mark any build_sessions rows for the user revoked. Build JWTs are
     * stateless; the authoritative cut-off is build_users.sessions_revoked_at,
     * checked by JwtFilter.
     */
    public function revokeSessionRows(int $userId, string $stamp): void
    {
        $this->db->table('build_sessions')
            ->where('user_id', $userId)
            ->where('revoked_at', null)
            ->update(['revoked_at' => $stamp]);
    }
}
