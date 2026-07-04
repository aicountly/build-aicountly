<?php

namespace App\Services;

use App\Models\DevRequestEventsModel;
use App\Models\DevRequestsModel;
use Config\Services;
use RuntimeException;

/**
 * Encodes the 18-status state machine from Part D.
 * Every transition writes an entry to build_dev_request_events + audit log.
 */
class DevRequestWorkflowService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'received'                     => ['analyzing', 'rejected', 'closed'],
        'analyzing'                    => ['plan_prepared', 'failed', 'rejected'],
        'plan_prepared'                => ['pending_approval', 'analyzing'],
        'pending_approval'             => ['approved_for_code', 'rejected'],
        'approved_for_code'            => ['coding', 'rejected'],
        'coding'                       => ['tests_running', 'failed'],
        'tests_running'                => ['pending_commit_approval', 'failed'],
        'pending_commit_approval'      => ['committed', 'rejected'],
        'committed'                    => ['pending_pr_approval'],
        'pending_pr_approval'          => ['pr_created', 'rejected'],
        'pr_created'                   => ['pending_staging_deployment', 'closed'],
        'pending_staging_deployment'   => ['staging_deployed', 'failed', 'closed'],
        'staging_deployed'             => ['pending_production_approval', 'closed'],
        'pending_production_approval'  => ['production_deployed', 'rejected', 'closed'],
        'production_deployed'          => ['closed'],
        'failed'                       => ['closed', 'analyzing'],
        'rejected'                     => ['closed', 'analyzing'],
        'closed'                       => [],
    ];

    private DevRequestsModel      $requests;
    private DevRequestEventsModel $events;

    public function __construct()
    {
        $this->requests = new DevRequestsModel();
        $this->events   = new DevRequestEventsModel();
    }

    public function isTransitionAllowed(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Move a dev-request row from its current status to $toStatus.
     * Records an append-only event row and fires an audit log entry.
     *
     * @param array{note?: string|null, payload?: array<string,mixed>|null, actor_kind?: string, force?: bool} $opts
     */
    public function transition(int $devRequestId, string $toStatus, array $opts = []): array
    {
        $row = $this->requests->find($devRequestId);
        if (! $row) {
            throw new RuntimeException("dev_request #{$devRequestId} not found.");
        }

        $from  = (string) $row['status'];
        $force = (bool) ($opts['force'] ?? false);

        if (! $force && ! $this->isTransitionAllowed($from, $toStatus)) {
            throw new RuntimeException("Illegal status transition {$from} -> {$toStatus} for dev_request #{$devRequestId}.");
        }

        $this->requests->update($devRequestId, ['status' => $toStatus]);

        $req = service('request');
        $this->events->insert([
            'dev_request_id' => $devRequestId,
            'actor_id'       => $req->buildUser['id']    ?? null,
            'actor_email'    => $req->buildUser['email'] ?? null,
            'actor_kind'     => $opts['actor_kind'] ?? 'user',
            'event'          => 'status_changed',
            'from_status'    => $from,
            'to_status'      => $toStatus,
            'note'           => $opts['note'] ?? null,
            'payload'        => isset($opts['payload']) ? json_encode($opts['payload']) : null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        Services::auditService()->log('dev_request.status_changed', [
            'entity_type' => 'dev_request',
            'entity_id'   => $devRequestId,
            'old_value'   => ['status' => $from],
            'new_value'   => ['status' => $toStatus],
            'metadata'    => $opts['payload'] ?? null,
        ]);

        return $this->requests->find($devRequestId) ?? $row;
    }

    /** Convenience for actor_kind='bot'. */
    public function botTransition(int $devRequestId, string $toStatus, ?string $note = null, ?array $payload = null): array
    {
        return $this->transition($devRequestId, $toStatus, [
            'actor_kind' => 'bot',
            'note'       => $note,
            'payload'    => $payload,
        ]);
    }

    public function recordEvent(int $devRequestId, string $event, ?string $note = null, ?array $payload = null, string $actorKind = 'system'): void
    {
        $req = service('request');
        $this->events->insert([
            'dev_request_id' => $devRequestId,
            'actor_id'       => $req->buildUser['id']    ?? null,
            'actor_email'    => $req->buildUser['email'] ?? null,
            'actor_kind'     => $actorKind,
            'event'          => $event,
            'note'           => $note,
            'payload'        => $payload ? json_encode($payload) : null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
