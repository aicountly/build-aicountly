<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\PlaywrightJobsModel;
use App\Models\ScreenshotsModel;
use App\Services\PlaywrightWorkerService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class PlaywrightReviewController extends BaseController
{
    public function index(): ResponseInterface
    {
        $devRequestId = $this->request->getGet('dev_request_id');
        $model = new PlaywrightJobsModel();
        if ($devRequestId !== null && $devRequestId !== '') {
            $model = $model->where('dev_request_id', (int) $devRequestId);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }

    public function show(int $id): ResponseInterface
    {
        $job = (new PlaywrightJobsModel())->find($id);
        if (! $job) {
            return build_json_not_found('Playwright job not found.');
        }
        $shots = (new ScreenshotsModel())->where('playwright_job_id', $id)->orderBy('id')->findAll();
        return build_json_success(['job' => $job, 'screenshots' => $shots]);
    }

    public function enqueue(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['kind', 'target_url']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $kind = (string) $data['kind'];
        $allowed = [
            PlaywrightWorkerService::KIND_BEFORE,
            PlaywrightWorkerService::KIND_AFTER,
            PlaywrightWorkerService::KIND_UI_INSPECTION,
            PlaywrightWorkerService::KIND_SMOKE_NAVIGATION,
            PlaywrightWorkerService::KIND_VISUAL_EVIDENCE,
        ];
        if (! in_array($kind, $allowed, true)) {
            return build_json_error('Invalid kind. Allowed: ' . implode(', ', $allowed), ['kind' => 'invalid'], 422);
        }

        $jobId = Services::playwrightWorker()->enqueue(
            $kind,
            isset($data['dev_request_id']) ? (int) $data['dev_request_id'] : null,
            isset($data['repo_id']) ? (int) $data['repo_id'] : null,
            (string) $data['target_url'],
            (array) ($data['extra'] ?? []),
        );

        return build_json_success(['job_id' => $jobId], 'Job enqueued.', 202);
    }
}
