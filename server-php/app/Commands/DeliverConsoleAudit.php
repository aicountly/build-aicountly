<?php

namespace App\Commands;

use App\Services\ConsoleAuditOutbox;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark console:deliver-audit
 *
 * Sends the central-audit events Console has not taken yet (build_console_syncs, kind
 * audit_event, status pending, due) to POST {CONSOLE_API_URL}/portal/audit in batches of 100.
 * Events are normally delivered the moment they happen; this retries the rest with back-off.
 *
 * Cron (every 5 minutes):
 *   0,5,10,15,20,25,30,35,40,45,50,55 * * * * cd /path/to/api && php spark console:deliver-audit >> writable/logs/console_audit.log 2>&1
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

        return EXIT_SUCCESS;
    }
}
