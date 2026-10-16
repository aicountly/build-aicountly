<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Staff roles come from Console (app_code `build`), not from a local default.
 *
 * - `role` loses its `super_admin` default and becomes nullable: a row with no
 *   role grants nothing. It is (re)written from Console's `app_roles` on every
 *   sign-in and every token refresh.
 * - `console_user_id`, `roles_synced_at`, `authz_version` and
 *   `console_app_roles` record the last successful Console sync.
 * - `sessions_revoked_at` ends every Build JWT issued before it (JwtFilter
 *   compares the token's `iat`); set when Console reports the access revoked.
 *
 * Existing rows keep their current `role` value but have no `roles_synced_at`,
 * so JwtFilter refuses their tokens until the user signs in again through
 * Console, which re-derives the role under the new rules.
 */
class AddConsoleRoleSyncToBuildUsers extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE build_users ALTER COLUMN role DROP DEFAULT');
        $this->db->query('ALTER TABLE build_users ALTER COLUMN role DROP NOT NULL');

        $this->forge->addColumn('build_users', [
            'console_user_id'     => ['type' => 'BIGINT', 'null' => true],
            'roles_synced_at'     => ['type' => 'TIMESTAMP', 'null' => true],
            'authz_version'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'console_app_roles'   => ['type' => 'TEXT', 'null' => true],
            'sessions_revoked_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->db->query('CREATE INDEX IF NOT EXISTS build_users_console_user_id_idx ON build_users (console_user_id)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX IF EXISTS build_users_console_user_id_idx');
        $this->forge->dropColumn('build_users', [
            'console_user_id', 'roles_synced_at', 'authz_version', 'console_app_roles', 'sessions_revoked_at',
        ]);

        // A NULL role means "nothing assigned"; keep that meaning (empty
        // string grants no route) rather than re-granting super_admin.
        $this->db->query("UPDATE build_users SET role = '' WHERE role IS NULL");
        $this->db->query('ALTER TABLE build_users ALTER COLUMN role SET NOT NULL');
        $this->db->query("ALTER TABLE build_users ALTER COLUMN role SET DEFAULT 'super_admin'");
    }
}
