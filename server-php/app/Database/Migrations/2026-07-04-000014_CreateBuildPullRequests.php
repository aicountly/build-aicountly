<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildPullRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'dev_request_id'  => ['type' => 'BIGINT', 'null' => false],
            'repo_id'         => ['type' => 'BIGINT', 'null' => false],
            'pr_number'       => ['type' => 'INTEGER', 'null' => true],
            'url'             => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'branch'          => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'target_branch'   => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'title'           => ['type' => 'TEXT', 'null' => true],
            'body'            => ['type' => 'TEXT', 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'workflow_status' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'approved_by'     => ['type' => 'BIGINT', 'null' => true],
            'approved_at'     => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('repo_id');
        $this->forge->addKey('pr_number');
        $this->forge->addKey('status');
        $this->forge->createTable('build_pull_requests', true);

        $this->db->query("ALTER TABLE build_pull_requests
            ADD CONSTRAINT build_pull_requests_status_check
            CHECK (status IN ('pending','approved','open','merged','closed','failed'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_pull_requests', true);
    }
}
