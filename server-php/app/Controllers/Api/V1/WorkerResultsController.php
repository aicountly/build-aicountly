<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class WorkerResultsController extends BaseController
{
    /**
     * Playwright worker posts here with the completed job payload.
     *   POST /api/v1/internal/worker/results/{jobId}
     * Body: { status: "completed"|"failed", screenshots: [...], error: "..." }
     * Auth: X-Worker-Token (enforced by WorkerAuthFilter).
     */
    public function submit(int $jobId): ResponseInterface
    {
        $payload = $this->jsonInput();
        try {
            Services::playwrightWorker()->completeJob($jobId, $payload);
        } catch (\Throwable $e) {
            return build_json_error('Worker result store failed: ' . $e->getMessage(), [], 500);
        }
        return build_json_success(['job_id' => $jobId], 'Result stored.');
    }
}
