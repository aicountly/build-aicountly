<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildPlaywrightJobs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGSERIAL'],
            'dev_request_id'    => ['type' => 'BIGINT', 'null' => true],
            'repo_id'           => ['type' => 'BIGINT', 'null' => true],
            'kind'              => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false],
            'target_url'        => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'queued'],
            'worker_ref'        => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'request'           => ['type' => 'JSONB', 'null' => true],
            'response'          => ['type' => 'JSONB', 'null' => true],
            'error'             => ['type' => 'TEXT', 'null' => true],
            'requested_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'completed_at'      => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('status');
        $this->forge->addKey('kind');
        $this->forge->createTable('build_playwright_jobs', true);

        $this->db->query("ALTER TABLE build_playwright_jobs
            ADD CONSTRAINT build_playwright_jobs_kind_check
            CHECK (kind IN ('before_screenshot','after_screenshot','ui_inspection','smoke_navigation','visual_evidence_report'))");

        $this->db->query("ALTER TABLE build_playwright_jobs
            ADD CONSTRAINT build_playwright_jobs_status_check
            CHECK (status IN ('queued','running','completed','failed','disabled'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_playwright_jobs', true);
    }
}
