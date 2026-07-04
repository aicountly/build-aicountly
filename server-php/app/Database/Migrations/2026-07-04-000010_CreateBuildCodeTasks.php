<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildCodeTasks extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'dev_request_id'  => ['type' => 'BIGINT', 'null' => false],
            'repo_id'         => ['type' => 'BIGINT', 'null' => true],
            'task_index'      => ['type' => 'INTEGER', 'default' => 0],
            'kind'            => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'payload'         => ['type' => 'JSONB', 'null' => true],
            'result'          => ['type' => 'JSONB', 'null' => true],
            'error'           => ['type' => 'TEXT', 'null' => true],
            'ran_at'          => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('status');
        $this->forge->addKey('kind');
        $this->forge->createTable('build_code_tasks', true);

        $this->db->query("ALTER TABLE build_code_tasks
            ADD CONSTRAINT build_code_tasks_kind_check
            CHECK (kind IN (
                'file_create','file_update','file_delete',
                'branch_create','branch_delete',
                'commit','pr_create','pr_update','pr_merge',
                'force_push','test_run','deploy_staging','deploy_production'
            ))");

        $this->db->query("ALTER TABLE build_code_tasks
            ADD CONSTRAINT build_code_tasks_status_check
            CHECK (status IN ('pending','approved','running','completed','failed','skipped','blocked_by_safe_guard'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_code_tasks', true);
    }
}
