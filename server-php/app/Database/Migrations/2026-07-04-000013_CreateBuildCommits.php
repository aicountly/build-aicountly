<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildCommits extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'dev_request_id'  => ['type' => 'BIGINT', 'null' => false],
            'repo_id'         => ['type' => 'BIGINT', 'null' => false],
            'branch'          => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => false],
            'sha'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'message'         => ['type' => 'TEXT', 'null' => true],
            'diff_summary'    => ['type' => 'JSONB', 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'created_by_bot'  => ['type' => 'BOOLEAN', 'default' => true],
            'approved_by'     => ['type' => 'BIGINT', 'null' => true],
            'approved_at'     => ['type' => 'TIMESTAMP', 'null' => true],
            'error'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('repo_id');
        $this->forge->addKey('sha');
        $this->forge->addKey('status');
        $this->forge->createTable('build_commits', true);

        $this->db->query("ALTER TABLE build_commits
            ADD CONSTRAINT build_commits_status_check
            CHECK (status IN ('pending','approved','committed','failed'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_commits', true);
    }
}
