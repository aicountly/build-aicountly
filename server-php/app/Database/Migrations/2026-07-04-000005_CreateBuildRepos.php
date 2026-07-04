<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildRepos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                           => ['type' => 'BIGSERIAL'],
            'repo_code'                    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'repo_name'                    => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => false],
            'product'                      => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'github_org'                   => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'github_repo'                  => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'local_path'                   => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'default_branch'               => ['type' => 'VARCHAR', 'constraint' => 128, 'default' => 'main'],
            'protected_branch'             => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'allowed_working_branch_prefix' => ['type' => 'VARCHAR', 'constraint' => 128, 'default' => 'build-bot/'],
            'deployment_type'              => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'manual'],
            'staging_url'                  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'production_url'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'enabled'                      => ['type' => 'BOOLEAN', 'default' => true],
            'last_sync_at'                 => ['type' => 'TIMESTAMP', 'null' => true],
            'notes'                        => ['type' => 'TEXT', 'null' => true],
            'created_at'                   => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'                   => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('repo_code');
        $this->forge->addKey('enabled');
        $this->forge->addKey('product');
        $this->forge->createTable('build_repos', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('build_repos', true);
    }
}
