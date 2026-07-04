<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildBotPlans extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'BIGSERIAL'],
            'dev_request_id'   => ['type' => 'BIGINT', 'null' => false],
            'ai_provider'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'mock'],
            'ai_model'         => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'summary'          => ['type' => 'TEXT', 'null' => true],
            'plan'             => ['type' => 'JSONB', 'null' => true],
            'test_plan'        => ['type' => 'JSONB', 'null' => true],
            'risk_level'       => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'medium'],
            'files_estimated'  => ['type' => 'JSONB', 'null' => true],
            'tokens_used'      => ['type' => 'INTEGER', 'default' => 0],
            'created_by_bot'   => ['type' => 'BOOLEAN', 'default' => true],
            'created_at'       => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'       => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('ai_provider');
        $this->forge->createTable('build_bot_plans', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('build_bot_plans', true);
    }
}
