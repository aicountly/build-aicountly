<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the 18 AICOUNTLY repositories from the Part C list.
 *
 * `local_path` is intentionally left NULL — never seed absolute filesystem paths.
 * Runtime configuration (paths, deployment endpoints, etc.) can be edited
 * via the Repo Registry admin UI or `build_settings` overrides.
 */
class ReposSeeder extends Seeder
{
    /**
     * @return list<array<string, mixed>>
     */
    private function repos(): array
    {
        $org = (string) (env('BUILD_GITHUB_ORG', 'AICOUNTLY'));

        return [
            ['repo_code' => 'books-react-app',         'repo_name' => 'Smart Books',            'product' => 'books',            'github_repo' => 'books-react-app',         'deployment_type' => 'cpanel',   'production_url' => 'https://books.aicountly.com',       'staging_url' => null],
            ['repo_code' => 'auditor-react-app',       'repo_name' => 'Auditor',                'product' => 'auditor',          'github_repo' => 'auditor-react-app',       'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'fr-react-app',            'repo_name' => 'FR (Financial Reports)', 'product' => 'fr',               'github_repo' => 'fr-react-app',            'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'secretarial-react-app',   'repo_name' => 'Secretarial',            'product' => 'secretarial',      'github_repo' => 'secretarial-react-app',   'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'calendar-react-app',      'repo_name' => 'Calendar',               'product' => 'calendar',         'github_repo' => 'calendar-react-app',      'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'contacts-react-app',      'repo_name' => 'Contacts',               'product' => 'contacts',         'github_repo' => 'contacts-react-app',      'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'vault-react-app',         'repo_name' => 'Vault',                  'product' => 'vault',            'github_repo' => 'vault-react-app',         'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'chat-aicountly',          'repo_name' => 'Chat',                   'product' => 'chat',             'github_repo' => 'chat-aicountly',          'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'docs-react-app',          'repo_name' => 'Docs',                   'product' => 'docs',             'github_repo' => 'docs-react-app',          'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'hrms-react-app',          'repo_name' => 'HRMS',                   'product' => 'hrms',             'github_repo' => 'hrms-react-app',          'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'our-people-react-app',    'repo_name' => 'Our People',             'product' => 'our_people',       'github_repo' => 'our-people-react-app',    'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'flow-react-app',          'repo_name' => 'Flow',                   'product' => 'flow',             'github_repo' => 'flow-react-app',          'deployment_type' => 'cpanel',   'production_url' => 'https://flow.aicountly.org',        'staging_url' => null],
            ['repo_code' => 'console-react-app',       'repo_name' => 'Console',                'product' => 'console',          'github_repo' => 'console-react-app',       'deployment_type' => 'cpanel',   'production_url' => 'https://console.aicountly.org',     'staging_url' => null],
            ['repo_code' => 'engage-aicountly',        'repo_name' => 'Engage',                 'product' => 'engage',           'github_repo' => 'engage-aicountly',        'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'reach-aicountly',         'repo_name' => 'Reach',                  'product' => 'reach',            'github_repo' => 'reach-aicountly',         'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'build-aicountly',         'repo_name' => 'Build (this portal)',    'product' => 'build',            'github_repo' => 'build-aicountly',         'deployment_type' => 'cpanel',   'production_url' => 'https://build.aicountly.org',       'staging_url' => null],
            ['repo_code' => 'my-aicountly-com',        'repo_name' => 'My Aicountly (customer identity)', 'product' => 'my',   'github_repo' => 'my-aicountly-com',        'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
            ['repo_code' => 'manage-aicountly',        'repo_name' => 'Manage',                 'product' => 'manage',           'github_repo' => 'manage-aicountly',        'deployment_type' => 'cpanel',   'production_url' => null,                                  'staging_url' => null],
        ];
    }

    public function run(): void
    {
        $org = (string) env('BUILD_GITHUB_ORG', 'AICOUNTLY');
        $defaultBranch = (string) env('BUILD_GITHUB_DEFAULT_BRANCH', 'main');
        $prefix        = (string) env('BUILD_GITHUB_WORKING_BRANCH_PREFIX', 'build-bot/');

        foreach ($this->repos() as $repo) {
            $existing = $this->db->table('build_repos')->where('repo_code', $repo['repo_code'])->get()->getRow();
            if ($existing) {
                continue;
            }

            $this->db->table('build_repos')->insert([
                'repo_code'                     => $repo['repo_code'],
                'repo_name'                     => $repo['repo_name'],
                'product'                       => $repo['product'],
                'github_org'                    => $org,
                'github_repo'                   => $repo['github_repo'],
                'local_path'                    => null,
                'default_branch'                => $defaultBranch,
                'protected_branch'              => $defaultBranch,
                'allowed_working_branch_prefix' => $prefix,
                'deployment_type'               => $repo['deployment_type'],
                'staging_url'                   => $repo['staging_url'],
                'production_url'                => $repo['production_url'],
                'enabled'                       => true,
                'notes'                         => 'Seeded by ReposSeeder',
            ]);
        }

        echo "[ReposSeeder] Seeded/verified 18 AICOUNTLY repositories.\n";
    }
}
