<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateBuildDevRequestEvents extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'BIGSERIAL'],
            'dev_request_id'  => ['type' => 'BIGINT', 'null' => false],
            'actor_id'        => ['type' => 'BIGINT', 'null' => true],
            'actor_email'     => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'actor_kind'      => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'user'],
            'event'           => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'from_status'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'to_status'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'note'            => ['type' => 'TEXT', 'null' => true],
            'payload'         => ['type' => 'JSONB', 'null' => true],
            'created_at'      => ['type' => 'TIMESTAMP', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('dev_request_id');
        $this->forge->addKey('event');
        $this->forge->addKey('created_at');
        $this->forge->createTable('build_dev_request_events', true);

        $this->db->query("ALTER TABLE build_dev_request_events
            ADD CONSTRAINT build_dev_request_events_actor_kind_check
            CHECK (actor_kind IN ('user','bot','system','flow','console','worker'))");
    }

    public function down(): void
    {
        $this->forge->dropTable('build_dev_request_events', true);
    }
}
