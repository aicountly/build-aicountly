<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildDevRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                     => ['type' => 'BIGSERIAL'],
            'source_portal'          => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false, 'default' => 'manual'],
            'source_reference_id'    => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'repo_id'                => ['type' => 'BIGINT', 'null' => true],
            'product'                => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'requirement_text'       => ['type' => 'TEXT', 'null' => false],
            'request_type'           => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false, 'default' => 'task'],
            'priority'               => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'normal'],
            'risk_level'             => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'medium'],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'received'],
            'files_likely_affected'  => ['type' => 'JSONB', 'null' => true],
            'code_summary'           => ['type' => 'TEXT', 'null' => true],
            'files_changed_summary'  => ['type' => 'JSONB', 'null' => true],
            'commit_hash'            => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'pr_url'                 => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'deployment_status'      => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'created_by'             => ['type' => 'BIGINT', 'null' => true],
            'metadata'               => ['type' => 'JSONB', 'null' => true],
            'created_at'             => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'             => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('status');
        $this->forge->addKey('source_portal');
        $this->forge->addKey('source_reference_id');
        $this->forge->addKey('repo_id');
        $this->forge->addKey('priority');
        $this->forge->addKey('risk_level');
        $this->forge->addKey('created_at');
        $this->forge->createTable('build_dev_requests', true);

        // Enforce the 18-status state machine at the DB level.
        $this->db->query("ALTER TABLE build_dev_requests
            ADD CONSTRAINT build_dev_requests_status_check
            CHECK (status IN (
                'received','analyzing','plan_prepared','pending_approval','approved_for_code',
                'coding','tests_running','pending_commit_approval','committed','pending_pr_approval',
                'pr_created','pending_staging_deployment','staging_deployed','pending_production_approval',
                'production_deployed','failed','rejected','closed'
            ))");

        $this->db->query("ALTER TABLE build_dev_requests
            ADD CONSTRAINT build_dev_requests_request_type_check
            CHECK (request_type IN ('bug','feature','task','refactor','ui_fix','security','other'))");

        $this->db->query("ALTER TABLE build_dev_requests
            ADD CONSTRAINT build_dev_requests_priority_check
            CHECK (priority IN ('low','normal','high','urgent'))");

        $this->db->query("ALTER TABLE build_dev_requests
            ADD CONSTRAINT build_dev_requests_risk_check
            CHECK (risk_level IN ('low','medium','high','critical'))");

        $this->db->query("ALTER TABLE build_dev_requests
            ADD CONSTRAINT build_dev_requests_source_check
            CHECK (source_portal IN ('flow','console','manual'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_dev_requests', true);
    }
}
