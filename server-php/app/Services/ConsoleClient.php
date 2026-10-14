<?php

namespace App\Services;

/**
 * Build -> Console. One thing goes to Console: the central audit
 * (POST {CONSOLE_API_URL}/portal/audit through ConsoleAuditOutbox, retried until Console has it).
 *
 * Build used to send eight more event kinds — approval_request, bot_report_summary,
 * high_risk_action_request, pr_created, deployment_requested, deployment_status_changed,
 * bot_mode_status, health_status — to /api/v1/portal/build/* on BUILD_CONSOLE_API_BASE_URL.
 * Console has never had those routes (none in its history), so every one ended as a `failed` or
 * `skipped_not_configured` row in build_console_syncs and Console learnt nothing. They were
 * removed (2026-10-14) because each duplicated something Console now receives anyway:
 *
 *   - approvals, PRs, deployments, the high-risk override and the bot mode are decided and
 *     recorded in Build itself (build_approvals, build_pull_requests, build_deployment_requests,
 *     build_settings), and every one of those changes is an AuditService action whose prefix is
 *     fanned out (approval.*, pr.*, deployment.*, high_risk.*, bot.*) — so Console's audit log
 *     (filter app = Build) shows it, with the fields those calls carried in its metadata;
 *   - bot_report_summary and health_status had no caller.
 *
 * Console's own cross-portal approval queue is the Bot Orchestrator (POST /portal/bot-approval-request,
 * bot_portals HMAC key), which is for bot actions; Build's human approvals stay in Build. The inbound
 * POST /api/v1/internal/console/approvals/callback remains for a Console that decides Build
 * approvals; Console does not call it today.
 */
class ConsoleClient
{
    public function isConfigured(): bool
    {
        return (new ConsoleAuditOutbox())->isConfigured();
    }

    /**
     * Central audit: POST {CONSOLE_API_URL}/portal/audit through the outbox (ConsoleAuditOutbox),
     * retried by `php spark console:deliver-audit` instead of only recorded.
     */
    public function sendAuditEvent(string $type, array $payload, ?string $externalId = null): void
    {
        (new ConsoleAuditOutbox())->record($type, $payload, $externalId);
    }
}
