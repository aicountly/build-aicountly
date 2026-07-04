<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildAiProviderCalls extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'dev_request_id'  => ['type' => 'BIGINT', 'null' => true],
            'provider'        => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
            'model'           => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'endpoint'        => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'prompt_hash'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'tokens_in'       => ['type' => 'INTEGER', 'default' => 0],
            'tokens_out'      => ['type' => 'INTEGER', 'default' => 0],
            'latency_ms'      => ['type' => 'INTEGER', 'default' => 0],
            'cost_estimate'   => ['type' => 'NUMERIC(12,6)', 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'ok'],
            'error'           => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('provider');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('build_ai_provider_calls', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('build_ai_provider_calls', true);
    }
}
