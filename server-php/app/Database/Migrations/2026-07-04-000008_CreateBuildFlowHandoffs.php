<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildFlowHandoffs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGSERIAL'],
            'flow_handoff_id'   => ['type' => 'BIGINT', 'null' => false],
            'source_type'       => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'source_id'         => ['type' => 'BIGINT', 'null' => true],
            'source_portal'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'flow.aicountly.org'],
            'raw_payload'       => ['type' => 'JSONB', 'null' => false],
            'dev_request_id'    => ['type' => 'BIGINT', 'null' => true],
            'received_at'       => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'processed_at'      => ['type' => 'TIMESTAMP', 'null' => true],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'received'],
        ]);

        $this->forge->addPrimaryKey('id');
        // Unique on the Flow-side ID for idempotent retries.
        $this->forge->addUniqueKey('flow_handoff_id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('status');
        $this->forge->createTable('build_flow_handoffs', true);

        $this->db->query("ALTER TABLE build_flow_handoffs
            ADD CONSTRAINT build_flow_handoffs_status_check
            CHECK (status IN ('received','acknowledged','failed'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_flow_handoffs', true);
    }
}
