<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\FlowHandoffsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class FlowHandoffController extends BaseController
{
    /**
     * Public (filtered by `flow-inbound`) — Flow's BuildApiClient POSTs here.
     * Handles both `/api/v1/tasks` (Flow's live path) and
     * `/api/v1/internal/flow/build-task` (spec-canonical). Dedups on
     * flow_handoff_id and always returns 200 with the build_task_ref.
     */
    public function create(): ResponseInterface
    {
        $payload = $this->jsonInput();
        if ($payload === []) {
            return build_json_error('Empty JSON body.', [], 422);
        }
        if (empty($payload['flow_handoff_id'])) {
            return build_json_error('flow_handoff_id is required.', ['flow_handoff_id' => 'required'], 422);
        }

        try {
            $source = $this->request->buildFlowSource ?? 'flow.aicountly.org';
            $result = Services::flowInboundService()->create($payload, (string) $source);
        } catch (\Throwable $e) {
            return build_json_error('Flow handoff failed: ' . $e->getMessage(), [], 500);
        }

        return build_json_success([
            'build_task_ref'  => (int) ($result['dev_request']['id'] ?? 0),
            'dev_request_id'  => (int) ($result['dev_request']['id'] ?? 0),
            'flow_handoff_id' => (int) $payload['flow_handoff_id'],
            'created'         => (bool) $result['created'],
            'status'          => (string) ($result['dev_request']['status'] ?? 'received'),
        ], $result['created'] ? 'Handoff accepted.' : 'Handoff already known.', $result['created'] ? 201 : 200);
    }

    // -- Admin views (jwt + role:super_admin) -----------------------------

    public function index(): ResponseInterface
    {
        $rows = (new FlowHandoffsModel())
            ->orderBy('id', 'DESC')
            ->limit(200)
            ->find();
        return build_json_success($rows);
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new FlowHandoffsModel())->find($id);
        return $row ? build_json_success($row) : build_json_not_found('Handoff not found.');
    }
}
