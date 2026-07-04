<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildConsoleSyncs extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'kind'            => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false],
            'direction'       => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false, 'default' => 'outbound'],
            'entity_type'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'entity_id'       => ['type' => 'BIGINT', 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'request'         => ['type' => 'JSONB', 'null' => true],
            'response'        => ['type' => 'JSONB', 'null' => true],
            'error'           => ['type' => 'TEXT', 'null' => true],
            'retry_count'     => ['type' => 'INTEGER', 'default' => 0],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('kind');
        $this->forge->addKey('direction');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->createTable('build_console_syncs', true);

        $this->db->query("ALTER TABLE build_console_syncs
            ADD CONSTRAINT build_console_syncs_direction_check
            CHECK (direction IN ('outbound','inbound'))");

        $this->db->query("ALTER TABLE build_console_syncs
            ADD CONSTRAINT build_console_syncs_status_check
            CHECK (status IN ('pending','sent','delivered','failed','skipped_not_configured'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_console_syncs', true);
    }
}
