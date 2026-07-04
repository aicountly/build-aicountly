<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildApprovals extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'BIGSERIAL'],
            'entity_type'    => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false, 'default' => 'dev_request'],
            'entity_id'      => ['type' => 'BIGINT', 'null' => false],
            'action'         => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'reason'         => ['type' => 'TEXT', 'null' => true],
            'payload'        => ['type' => 'JSONB', 'null' => true],
            'requester_id'   => ['type' => 'BIGINT', 'null' => true],
            'decided_by'     => ['type' => 'BIGINT', 'null' => true],
            'decided_at'     => ['type' => 'TIMESTAMP', 'null' => true],
            'console_ref'    => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'created_at'     => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'     => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addKey('action');
        $this->forge->addKey('status');
        $this->forge->addKey('console_ref');
        $this->forge->createTable('build_approvals', true);

        $this->db->query("ALTER TABLE build_approvals
            ADD CONSTRAINT build_approvals_action_check
            CHECK (action IN ('code','commit','pr','staging_deploy','prod_deploy','high_risk_override'))");

        $this->db->query("ALTER TABLE build_approvals
            ADD CONSTRAINT build_approvals_status_check
            CHECK (status IN ('pending','approved','rejected','cancelled'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_approvals', true);
    }
}
