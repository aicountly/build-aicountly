<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildAuditLogs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGSERIAL'],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
            'actor_id'    => ['type' => 'BIGINT', 'null' => true],
            'actor_email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'entity_id'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'risk_level'  => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'info'],
            'old_value'   => ['type' => 'JSONB', 'null' => true],
            'new_value'   => ['type' => 'JSONB', 'null' => true],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'user_agent'  => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'metadata'    => ['type' => 'JSONB', 'null' => true],
            'created_at'  => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('action');
        $this->forge->addKey('actor_id');
        $this->forge->addKey('entity_type');
        $this->forge->addKey('risk_level');
        $this->forge->addKey('created_at');
        $this->forge->createTable('build_audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('build_audit_logs', true);
    }
}
