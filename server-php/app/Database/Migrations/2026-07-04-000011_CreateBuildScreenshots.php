<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildScreenshots extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'BIGSERIAL'],
            'dev_request_id'     => ['type' => 'BIGINT', 'null' => false],
            'playwright_job_id'  => ['type' => 'BIGINT', 'null' => true],
            'phase'              => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => false],
            'url'                => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'path'               => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'worker_job_id'      => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'width'              => ['type' => 'INTEGER', 'null' => true],
            'height'             => ['type' => 'INTEGER', 'null' => true],
            'meta'               => ['type' => 'JSONB', 'null' => true],
            'created_at'         => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('playwright_job_id');
        $this->forge->addKey('phase');
        $this->forge->createTable('build_screenshots', true);

        $this->db->query("ALTER TABLE build_screenshots
            ADD CONSTRAINT build_screenshots_phase_check
            CHECK (phase IN ('before','after','inspection','smoke','evidence'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_screenshots', true);
    }
}
