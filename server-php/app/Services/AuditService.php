<?php

namespace App\Services;

use App\Models\AuditLogsModel;
use Config\Services;

/**
 * Append-only audit log writer. Every mutating action must call log().
 * Fans the CONSOLE_FANOUT_PREFIXES actions out to Console's central audit through the
 * outbox (ConsoleAuditOutbox — retried until Console has them).
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
        // Every auth.* event: sign-ins are auth.controller_sso_login / auth.controller_sso_callback /
        // auth.console_session_login (never plain auth.login), plus logout, refresh and access denials.
        'auth.',
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
                // JSON-cast fields: pass the values, AuditLogsModel encodes them.
                'old_value'   => $opts['old_value'] ?? null,
                'new_value'   => $opts['new_value'] ?? null,
                'ip_address'  => method_exists($req, 'getIPAddress') ? $req->getIPAddress() : null,
                'user_agent'  => method_exists($req, 'getUserAgent') ? substr((string) $req->getUserAgent(), 0, 510) : null,
                'metadata'    => $opts['metadata'] ?? null,
                'created_at'  => date('Y-m-d H:i:s'),
            ];

            $auditId = $this->model->insert($row);

            if ($this->shouldFanOut($action)) {
                try {
                    // external_id = this build_audit_logs row: Console stores it once however often it is resent.
                    Services::consoleClient()->sendAuditEvent('build.' . $action, [
                        'action'      => $action,
                        'entity_type' => $row['entity_type'],
                        'entity_id'   => $row['entity_id'],
                        'risk_level'  => $row['risk_level'],
                        'actor_id'    => $actorId,
                        'actor_email' => $actorEmail,
                        'old_value'   => $row['old_value'],
                        'new_value'   => $row['new_value'],
                        'ip_address'  => $row['ip_address'],
                        'user_agent'  => $row['user_agent'],
                        'metadata'    => $row['metadata'],
                    ], $auditId ? 'build:audit_logs:' . $auditId : null);
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
