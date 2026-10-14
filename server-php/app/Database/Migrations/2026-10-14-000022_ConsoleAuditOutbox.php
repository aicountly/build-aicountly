<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * build_console_syncs doubles as the outbox for Build's central audit to Console
 * (ConsoleAuditOutbox, POST {CONSOLE_API_URL}/portal/audit — console-react-app migration 067).
 *
 * An audit_event row is written `pending` first, sent at once, and retried with back-off by
 * `php spark console:deliver-audit` until Console answers stored / duplicate (→ delivered) or
 * rejected (a Build bug; kept, not retried); after 12 failed attempts it is `dead`.
 * external_id is what Console de-duplicates on ('build:audit_logs:<id>'), unique here too so an
 * event is queued once.
 */
class ConsoleAuditOutbox extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE build_console_syncs ADD COLUMN IF NOT EXISTS external_id VARCHAR(128) NULL');
        $this->db->query('ALTER TABLE build_console_syncs ADD COLUMN IF NOT EXISTS next_attempt_at TIMESTAMP NULL');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS uq_build_console_syncs_external_id
            ON build_console_syncs (external_id) WHERE external_id IS NOT NULL');
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_build_console_syncs_audit_due
            ON build_console_syncs (next_attempt_at) WHERE kind = 'audit_event' AND status = 'pending'");

        $this->db->query('ALTER TABLE build_console_syncs DROP CONSTRAINT IF EXISTS build_console_syncs_status_check');
        $this->db->query("ALTER TABLE build_console_syncs
            ADD CONSTRAINT build_console_syncs_status_check
            CHECK (status IN ('pending','sent','delivered','failed','skipped_not_configured','rejected','dead'))");
    }

    public function down(): void
    {
        $this->db->query("UPDATE build_console_syncs SET status = 'failed' WHERE status IN ('rejected','dead')");
        $this->db->query('ALTER TABLE build_console_syncs DROP CONSTRAINT IF EXISTS build_console_syncs_status_check');
        $this->db->query("ALTER TABLE build_console_syncs
            ADD CONSTRAINT build_console_syncs_status_check
            CHECK (status IN ('pending','sent','delivered','failed','skipped_not_configured'))");
        $this->db->query('DROP INDEX IF EXISTS idx_build_console_syncs_audit_due');
        $this->db->query('DROP INDEX IF EXISTS uq_build_console_syncs_external_id');
        $this->db->query('ALTER TABLE build_console_syncs DROP COLUMN IF EXISTS next_attempt_at');
        $this->db->query('ALTER TABLE build_console_syncs DROP COLUMN IF EXISTS external_id');
    }
}
