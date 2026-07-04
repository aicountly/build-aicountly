<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the first superadmin from env vars.
 *
 * Accepts either BUILD_OWNER_* (local .env) or SUPER_ADMIN_* (CI deploy,
 * mirroring reach-aicountly). Idempotent: inserts when missing; updates
 * password/name when the email already exists.
 *
 * Run once after `php spark migrate`:
 *   php spark db:seed OwnerSeeder
 */
class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $email    = strtolower(trim((string) (env('BUILD_OWNER_EMAIL') ?: env('SUPER_ADMIN_EMAIL', ''))));
        $name     = trim((string) (env('BUILD_OWNER_NAME') ?: env('SUPER_ADMIN_NAME', 'Build Superadmin')));
        $password = (string) (env('BUILD_OWNER_PASSWORD') ?: env('SUPER_ADMIN_PASSWORD', ''));

        if ($email === '' || $password === '') {
            echo "[OwnerSeeder] Skipped — set BUILD_OWNER_* or SUPER_ADMIN_* email/password in .env (or GitHub secrets for deploy).\n";
            return;
        }

        if ($name === '') {
            $name = 'Build Superadmin';
        }

        if (strlen($password) < 8) {
            echo "[OwnerSeeder] Refusing to seed — password must be at least 8 characters.\n";
            return;
        }

        $existing = $this->db->table('build_users')->where('email', $email)->get()->getRow();
        $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        if ($existing) {
            $this->db->table('build_users')->where('id', $existing->id)->update([
                'name'          => $name,
                'password_hash' => $hash,
                'status'        => 'active',
                'role'          => 'super_admin',
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            echo "[OwnerSeeder] Updated superadmin credentials: {$email}\n";
            return;
        }

        $this->db->table('build_users')->insert([
            'email'         => $email,
            'name'          => $name,
            'password_hash' => $hash,
            'status'        => 'active',
            'role'          => 'super_admin',
        ]);

        echo "[OwnerSeeder] Seeded superadmin: {$email}\n";
    }
}
