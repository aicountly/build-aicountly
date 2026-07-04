<?php

namespace App\Services;

use App\Models\PlaywrightJobsModel;
use App\Models\ScreenshotsModel;
use Config\Services;

/**
 * Build -> Playwright worker (worker.apis.aicountly.com) client.
 *
 * The worker is *read-only*: it only takes screenshots, inspects UI, and
 * runs smoke navigations. No GitHub or bot logic ever lives there (Part H).
 *
 * If BUILD_WORKER_BASE_URL / BUILD_WORKER_API_TOKEN are empty, enqueue()
 * still records a build_playwright_jobs row with status='disabled' so the
 * UI can surface the unconfigured state.
 */
class PlaywrightWorkerService
{
    public const KIND_BEFORE            = 'before_screenshot';
    public const KIND_AFTER             = 'after_screenshot';
    public const KIND_UI_INSPECTION     = 'ui_inspection';
    public const KIND_SMOKE_NAVIGATION  = 'smoke_navigation';
    public const KIND_VISUAL_EVIDENCE   = 'visual_evidence_report';

    private const TIMEOUT_SECONDS = 20;

    private string $baseUrl;
    private string $token;
    private PlaywrightJobsModel $jobs;
    private ScreenshotsModel $screenshots;

    public function __construct()
    {
        $this->baseUrl     = rtrim((string) env('BUILD_WORKER_BASE_URL', ''), '/');
        $this->token       = (string) env('BUILD_WORKER_API_TOKEN', '');
        $this->jobs        = new PlaywrightJobsModel();
        $this->screenshots = new ScreenshotsModel();
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '';
    }

    /**
     * Enqueue a Playwright job on the worker. Returns the local job id.
     *
     * @param array<string, mixed> $extra
     */
    public function enqueue(string $kind, ?int $devRequestId, ?int $repoId, string $targetUrl, array $extra = []): int
    {
        $requestPayload = array_merge([
            'kind'        => $kind,
            'target_url'  => $targetUrl,
            'dev_request_id' => $devRequestId,
            'repo_id'     => $repoId,
        ], $extra);

        $jobId = (int) $this->jobs->insert([
            'dev_request_id' => $devRequestId,
            'repo_id'        => $repoId,
            'kind'           => $kind,
            'target_url'     => $targetUrl,
            'status'         => 'queued',
            'request'        => json_encode($requestPayload),
        ], true);

        if (! $this->isConfigured()) {
            $this->jobs->update($jobId, ['status' => 'disabled', 'error' => 'Worker not configured (BUILD_WORKER_BASE_URL/BUILD_WORKER_API_TOKEN).']);
            return $jobId;
        }

        [$ok, $status, $body, $err] = $this->post('/api/playwright/jobs', array_merge($requestPayload, [
            'callback_url' => rtrim((string) env('app.baseURL', ''), '/') . '/v1/internal/worker/results',
            'job_id'       => $jobId,
        ]));

        if ($ok) {
            $this->jobs->update($jobId, ['status' => 'running', 'worker_ref' => $this->extractWorkerRef($body)]);
        } else {
            $this->jobs->update($jobId, [
                'status' => 'failed',
                'error'  => $err !== '' ? $err : ('http_status=' . $status),
            ]);
        }

        Services::auditService()->log('bot.playwright_enqueue', [
            'entity_type' => 'dev_request',
            'entity_id'   => $devRequestId,
            'metadata'    => ['kind' => $kind, 'job_id' => $jobId, 'target_url' => $targetUrl],
        ]);

        return $jobId;
    }

    /**
     * Callback from the worker: attaches the response payload + status.
     *
     * @param array<string, mixed> $result
     */
    public function completeJob(int $jobId, array $result): void
    {
        $status = strtolower((string) ($result['status'] ?? 'completed'));
        $status = in_array($status, ['completed', 'failed'], true) ? $status : 'completed';

        $this->jobs->update($jobId, [
            'status'       => $status,
            'response'     => json_encode($result),
            'completed_at' => date('Y-m-d H:i:s'),
            'error'        => $status === 'failed' ? substr((string) ($result['error'] ?? ''), 0, 4096) : null,
        ]);

        // Persist any returned screenshots.
        $screenshots = (array) ($result['screenshots'] ?? []);
        foreach ($screenshots as $shot) {
            $job = $this->jobs->find($jobId);
            $this->screenshots->insert([
                'dev_request_id'    => $job['dev_request_id'] ?? null,
                'playwright_job_id' => $jobId,
                'phase'             => (string) ($shot['phase'] ?? $this->kindToPhase($job['kind'] ?? '')),
                'url'               => $shot['url']  ?? null,
                'path'              => $shot['path'] ?? null,
                'worker_job_id'     => $shot['worker_job_id'] ?? ($job['worker_ref'] ?? null),
                'width'             => isset($shot['width'])  ? (int) $shot['width']  : null,
                'height'            => isset($shot['height']) ? (int) $shot['height'] : null,
                'meta'              => isset($shot['meta']) ? json_encode($shot['meta']) : null,
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function kindToPhase(string $kind): string
    {
        return match ($kind) {
            self::KIND_BEFORE           => 'before',
            self::KIND_AFTER            => 'after',
            self::KIND_UI_INSPECTION    => 'inspection',
            self::KIND_SMOKE_NAVIGATION => 'smoke',
            self::KIND_VISUAL_EVIDENCE  => 'evidence',
            default                     => 'inspection',
        };
    }

    /** @param array<string, mixed> $payload */
    private function post(string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
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
        return [$ok, (int) $status, (string) $body, (string) $err];
    }

    private function extractWorkerRef(string $body): ?string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $data = $decoded['data'] ?? $decoded;
            return isset($data['worker_ref']) ? (string) $data['worker_ref'] : (isset($data['job_id']) ? (string) $data['job_id'] : null);
        }
        return null;
    }
}
