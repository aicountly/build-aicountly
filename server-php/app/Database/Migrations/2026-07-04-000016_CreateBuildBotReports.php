<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildBotReports extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                    => ['type' => 'BIGSERIAL'],
            'dev_request_id'        => ['type' => 'BIGINT', 'null' => false],
            'bot_name'              => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'build.bot'],
            'ai_provider'           => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'understanding'         => ['type' => 'TEXT', 'null' => true],
            'repo_id'               => ['type' => 'BIGINT', 'null' => true],
            'product'               => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'files_accessed'        => ['type' => 'JSONB', 'null' => true],
            'ui_screenshots'        => ['type' => 'JSONB', 'null' => true],
            'plan'                  => ['type' => 'JSONB', 'null' => true],
            'code_changes'          => ['type' => 'JSONB', 'null' => true],
            'tests_run'             => ['type' => 'JSONB', 'null' => true],
            'errors'                => ['type' => 'JSONB', 'null' => true],
            'approval_history'      => ['type' => 'JSONB', 'null' => true],
            'commit_details'        => ['type' => 'JSONB', 'null' => true],
            'pr_details'            => ['type' => 'JSONB', 'null' => true],
            'deployment_details'    => ['type' => 'JSONB', 'null' => true],
            'next_recommended_action' => ['type' => 'TEXT', 'null' => true],
            'raw_metadata'          => ['type' => 'JSONB', 'null' => true],
            'created_at'            => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'            => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('bot_name');
        $this->forge->addKey('created_at');
        $this->forge->createTable('build_bot_reports', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('build_bot_reports', true);
    }
}
