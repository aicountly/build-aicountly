<?php

namespace App\Services;

use App\Models\AuditLogsModel;
use Config\Services;

/**
 * Append-only audit log writer. Every mutating action must call log().
 * Optionally fans out to Console for the 8 event kinds listed in Part J.
 */
class AuditService
{
    /** Prefixes that Console listens for. Everything else is portal-local. */
    private const CONSOLE_FANOUT_PREFIXES = [
        'approval.',
        'bot.',
        'commit.',
        'pr.',
        'deployment.',
        'dev_request.',
        'auth.login',
        'auth.logout',
        'high_risk.',
    ];

    private AuditLogsModel $model;

    public function __construct()
    {
        $this->model = new AuditLogsModel();
    }

    /**
     * @param array{
     *     actor_id?: int|null,
     *     actor_email?: string|null,
     *     entity_type?: string|null,
     *     entity_id?: int|string|null,
     *     old_value?: mixed,
     *     new_value?: mixed,
     *     risk_level?: string,
     *     metadata?: array<string, mixed>|null
     * } $opts
     */
    public function log(string $action, array $opts = []): void
    {
        try {
            $req = service('request');
            $actorId    = $opts['actor_id']    ?? ($req->buildUser['id']    ?? null);
            $actorEmail = $opts['actor_email'] ?? ($req->buildUser['email'] ?? null);

            $row = [
                'action'      => $action,
                'actor_id'    => $actorId,
                'actor_email' => $actorEmail,
                'entity_type' => $opts['entity_type'] ?? null,
                'entity_id'   => isset($opts['entity_id']) ? (string) $opts['entity_id'] : null,
                'risk_level'  => $opts['risk_level']  ?? 'info',
                'old_value'   => isset($opts['old_value']) ? json_encode($opts['old_value']) : null,
                'new_value'   => isset($opts['new_value']) ? json_encode($opts['new_value']) : null,
                'ip_address'  => method_exists($req, 'getIPAddress') ? $req->getIPAddress() : null,
                'user_agent'  => method_exists($req, 'getUserAgent') ? substr((string) $req->getUserAgent(), 0, 510) : null,
                'metadata'    => isset($opts['metadata']) ? json_encode($opts['metadata']) : null,
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            $this->model->insert($row);

            if ($this->shouldFanOut($action)) {
                try {
                    Services::consoleClient()->sendAuditEvent('build.' . $action, [
                        'action'      => $action,
                        'entity_type' => $row['entity_type'],
                        'entity_id'   => $row['entity_id'],
                        'risk_level'  => $row['risk_level'],
                        'actor_id'    => $actorId,
                        'actor_email' => $actorEmail,
                    ]);
                } catch (\Throwable $e) {
                    log_message('warning', 'ConsoleClient fanout failed for ' . $action . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Audit log failed for action ' . $action . ': ' . $e->getMessage());
        }
    }

    private function shouldFanOut(string $action): bool
    {
        foreach (self::CONSOLE_FANOUT_PREFIXES as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
