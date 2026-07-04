<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildDeploymentRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'dev_request_id'  => ['type' => 'BIGINT', 'null' => false],
            'repo_id'         => ['type' => 'BIGINT', 'null' => true],
            'environment'     => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'requested'],
            'requester_id'    => ['type' => 'BIGINT', 'null' => true],
            'approved_by'     => ['type' => 'BIGINT', 'null' => true],
            'approved_at'     => ['type' => 'TIMESTAMP', 'null' => true],
            'deployed_at'     => ['type' => 'TIMESTAMP', 'null' => true],
            'provider'        => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'provider_ref'    => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'metadata'        => ['type' => 'JSONB', 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('environment');
        $this->forge->addKey('status');
        $this->forge->createTable('build_deployment_requests', true);

        $this->db->query("ALTER TABLE build_deployment_requests
            ADD CONSTRAINT build_deployment_requests_env_check
            CHECK (environment IN ('staging','production'))");

        $this->db->query("ALTER TABLE build_deployment_requests
            ADD CONSTRAINT build_deployment_requests_status_check
            CHECK (status IN ('requested','approved','deployed','failed','cancelled','provider_unconfigured'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_deployment_requests', true);
    }
}
