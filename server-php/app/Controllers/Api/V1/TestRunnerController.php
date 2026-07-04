<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\CodeTasksModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class TestRunnerController extends BaseController
{
    /**
     * Records a "test_run" code_task row for the request. Actual test
     * execution happens externally (CI on the target repo). The bot report
     * is updated with the recorded intent.
     */
    public function run(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['dev_request_id']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $devRequestId = (int) $data['dev_request_id'];
        $suites = (array) ($data['suites'] ?? ['default']);

        $model = new CodeTasksModel();
        $id = (int) $model->insert([
            'dev_request_id' => $devRequestId,
            'kind'           => 'test_run',
            'status'         => 'pending',
            'payload'        => json_encode(['suites' => $suites]),
        ], true);

        Services::devRequestWorkflow()->transition($devRequestId, 'tests_running', [
            'actor_kind' => 'bot',
            'note'       => 'Tests requested (suites: ' . implode(', ', $suites) . ')',
            'payload'    => ['code_task_id' => $id],
        ]);

        return build_json_success(['code_task_id' => $id], 'Test run recorded.', 202);
    }

    public function latest(int $devRequestId): ResponseInterface
    {
        $row = (new CodeTasksModel())
            ->where('dev_request_id', $devRequestId)
            ->where('kind', 'test_run')
            ->orderBy('id', 'DESC')
            ->first();
        return $row ? build_json_success($row) : build_json_success(null, 'No test runs yet.', 200);
    }
}
