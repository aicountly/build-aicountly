<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildGithubActivity extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'repo_id'         => ['type' => 'BIGINT', 'null' => true],
            'dev_request_id'  => ['type' => 'BIGINT', 'null' => true],
            'kind'            => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false],
            'ref'             => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'actor'           => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'payload'         => ['type' => 'JSONB', 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('repo_id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('kind');
        $this->forge->addKey('created_at');
        $this->forge->createTable('build_github_activity', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('build_github_activity', true);
    }
}
