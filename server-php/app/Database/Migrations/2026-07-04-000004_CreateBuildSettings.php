<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildSettings extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGSERIAL'],
            'key'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'value_json'  => ['type' => 'JSONB', 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'updated_by'  => ['type' => 'BIGINT', 'null' => true],
            'created_at'  => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'  => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('key');
        $this->forge->createTable('build_settings', true);

        // Seed the two mandatory settings: bot_mode + default_risk.
        $this->db->query("INSERT INTO build_settings (key, value_json, description)
            VALUES ('bot_mode', '\"confirm\"'::jsonb, 'Global bot mode: confirm | auto')
            ON CONFLICT (key) DO NOTHING");
        $this->db->query("INSERT INTO build_settings (key, value_json, description)
            VALUES ('default_risk', '\"medium\"'::jsonb, 'Default risk level applied to new dev requests')
            ON CONFLICT (key) DO NOTHING");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_settings', true);
    }
}
