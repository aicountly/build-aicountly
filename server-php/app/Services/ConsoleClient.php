<?php

namespace App\Services;

use App\Models\ConsoleSyncsModel;

/**
 * Build -> Console outbound HTTP client. Every call is recorded in
 * build_console_syncs with request/response for observability.
 *
 * Endpoints (Part J of the spec):
 *   audit_event                POST /api/v1/portal/audit
 *   approval_request           POST /api/v1/portal/build/approvals
 *   bot_report_summary         POST /api/v1/portal/build/bot-reports
 *   high_risk_action_request   POST /api/v1/portal/build/high-risk
 *   pr_created                 POST /api/v1/portal/build/prs
 *   deployment_requested       POST /api/v1/portal/build/deployments
 *   deployment_status_changed  PATCH /api/v1/portal/build/deployments
 *   bot_mode_status            POST /api/v1/portal/build/bot-mode
 *   health_status              POST /api/v1/portal/build/health
 *
 * The paths above are canonical for Build → Console. If Console changes them,
 * only this file needs updating. When CONSOLE_API_BASE_URL is empty, every
 * call is recorded as `skipped_not_configured`.
 */
class ConsoleClient
{
    private const TIMEOUT_SECONDS = 15;

    private string $baseUrl;
    private string $token;
    private ConsoleSyncsModel $syncs;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('BUILD_CONSOLE_API_BASE_URL', ''), '/');
        $this->token   = (string) env('BUILD_CONSOLE_API_TOKEN', '');
        $this->syncs   = new ConsoleSyncsModel();
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    public function sendAuditEvent(string $type, array $payload): void
    {
        $this->dispatch('audit_event', 'POST', '/api/v1/portal/audit', [
            'source'    => BUILD_PORTAL_SOURCE,
            'type'      => $type,
            'payload'   => $payload,
            'timestamp' => gmdate('c'),
        ]);
    }

    public function sendApprovalRequest(array $payload): void
    {
        $this->dispatch('approval_request', 'POST', '/api/v1/portal/build/approvals', $payload);
    }

    public function sendBotReportSummary(array $payload): void
    {
        $this->dispatch('bot_report_summary', 'POST', '/api/v1/portal/build/bot-reports', $payload);
    }

    public function sendHighRiskActionRequest(array $payload): void
    {
        $this->dispatch('high_risk_action_request', 'POST', '/api/v1/portal/build/high-risk', $payload);
    }

    public function sendPrCreated(array $payload): void
    {
        $this->dispatch('pr_created', 'POST', '/api/v1/portal/build/prs', $payload);
    }

    public function sendDeploymentRequested(array $payload): void
    {
        $this->dispatch('deployment_requested', 'POST', '/api/v1/portal/build/deployments', $payload);
    }

    public function sendDeploymentStatus(array $payload): void
    {
        $this->dispatch('deployment_status_changed', 'PATCH', '/api/v1/portal/build/deployments', $payload);
    }

    public function sendBotModeStatus(array $payload): void
    {
        $this->dispatch('bot_mode_status', 'POST', '/api/v1/portal/build/bot-mode', $payload);
    }

    public function sendHealthStatus(array $payload): void
    {
        $this->dispatch('health_status', 'POST', '/api/v1/portal/build/health', $payload);
    }

    /** @param array<string, mixed> $payload */
    private function dispatch(string $kind, string $method, string $path, array $payload): void
    {
        $requestSnapshot = [
            'method'  => $method,
            'path'    => $path,
            'payload' => $payload,
        ];

        if (! $this->isConfigured()) {
            $this->syncs->insert([
                'kind'      => $kind,
                'direction' => 'outbound',
                'status'    => 'skipped_not_configured',
                'request'   => json_encode($requestSnapshot),
                'response'  => null,
                'error'     => 'BUILD_CONSOLE_API_BASE_URL or BUILD_CONSOLE_API_TOKEN empty.',
            ]);
            return;
        }

        $url = $this->baseUrl . $path;
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'X-Source: ' . BUILD_PORTAL_SOURCE,
            ],
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        $ok = $err === '' && $status >= 200 && $status < 300;

        $this->syncs->insert([
            'kind'      => $kind,
            'direction' => 'outbound',
            'status'    => $ok ? 'delivered' : 'failed',
            'request'   => json_encode($requestSnapshot),
            'response'  => json_encode([
                'http_status' => $status,
                'body'        => $body ? substr((string) $body, 0, 4096) : null,
            ]),
            'error'     => $err !== '' ? $err : ($ok ? null : ('http_status=' . $status)),
        ]);
    }
}
