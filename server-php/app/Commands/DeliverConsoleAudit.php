<?php

namespace App\Commands;

use App\Services\ConsoleAuditOutbox;
use App\Libraries\CronHeartbeat;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark console:deliver-audit
 *
 * Sends the central-audit events Console has not taken yet (build_console_syncs, kind
 * audit_event, status pending, due) to POST {CONSOLE_API_URL}/portal/audit in batches of 100.
 * Events are normally delivered the moment they happen; this retries the rest with back-off.
 *
 * Cron (every 5 minutes): scheduled by the production deploy from bin/ops/jobs.txt (docs/deploy.md step 6) — not
 * pasted into cPanel by hand.
 */
class DeliverConsoleAudit extends BaseCommand
{
    protected $group       = 'Build';
    protected $name        = 'console:deliver-audit';
    protected $description = 'Retry central-audit events Console has not taken yet (POST {CONSOLE_API_URL}/portal/audit).';
    protected $usage       = 'console:deliver-audit';

    public function run(array $params)
    {
        $outbox = new ConsoleAuditOutbox();
        if (! $outbox->isConfigured()) {
            CLI::error('CONSOLE_API_URL / CONSOLE_API_TOKEN not set — nothing can be sent.');

            return EXIT_ERROR;
        }

        $r = $outbox->deliverDue();
        CLI::write(sprintf(
            'Console audit: %d delivered, %d rejected, %d to retry, %d dead (%d batch%s).',
            $r['delivered'], $r['rejected'], $r['retry'], $r['dead'], $r['batches'], $r['batches'] === 1 ? '' : 'es'
        ));

        // Console's Cron Job Monitor (build.console_audit_deliver; CONSOLE_CRON_MONITOR_KEY in .env, console-react-app
        // docs/CRON_MONITORS.md): a delivery run reports ok with its counts. Inert without a key; a failed
        // report never fails this command.
        CronHeartbeat::send('build.console_audit_deliver', 'ok', '', array_map('intval', array_intersect_key($r, array_flip(['delivered', 'rejected', 'retry', 'dead', 'batches']))));

        return EXIT_SUCCESS;
    }
}
