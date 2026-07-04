<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ApprovalsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ApprovalsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $status  = (string) ($this->request->getGet('status') ?? '');
        $action  = (string) ($this->request->getGet('action') ?? '');
        $entity  = (string) ($this->request->getGet('entity_type') ?? '');
        $eid     = $this->request->getGet('entity_id');

        $model = new ApprovalsModel();
        if ($status !== '') { $model = $model->where('status', $status); }
        if ($action !== '') { $model = $model->where('action', $action); }
        if ($entity !== '') { $model = $model->where('entity_type', $entity); }
        if ($eid !== null && $eid !== '') { $model = $model->where('entity_id', (int) $eid); }

        return build_json_success($model->orderBy('id', 'DESC')->limit(500)->find());
    }

    public function create(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['entity_id', 'action']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        try {
            $id = Services::approvalService()->request(
                (int) $data['entity_id'],
                (string) $data['action'],
                $this->currentUserId(),
                (array) ($data['payload'] ?? [])
            );
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 400);
        }

        return build_json_success(['approval_id' => $id], 'Approval requested.', 201);
    }

    public function approve(int $id): ResponseInterface
    {
        $data = $this->jsonInput();
        try {
            $row = Services::approvalService()->approve($id, (int) $this->currentUserId(), $data['reason'] ?? null);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 409);
        }
        return build_json_success($row, 'Approved.');
    }

    public function reject(int $id): ResponseInterface
    {
        $data = $this->jsonInput();
        try {
            $row = Services::approvalService()->reject($id, (int) $this->currentUserId(), $data['reason'] ?? null);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 409);
        }
        return build_json_success($row, 'Rejected.');
    }

    public function highRiskOverride(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['dev_request_id', 'reason']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        try {
            $id = Services::safetyGuard()->recordHighRiskOverride(
                (int) $data['dev_request_id'],
                (string) $data['reason'],
                $this->currentUserId(),
            );
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 400);
        }

        Services::auditService()->log('high_risk.override_recorded', [
            'entity_type' => 'dev_request',
            'entity_id'   => (int) $data['dev_request_id'],
            'metadata'    => ['approval_id' => $id, 'reason' => (string) $data['reason']],
            'risk_level'  => 'critical',
        ]);

        try {
            Services::consoleClient()->sendHighRiskActionRequest([
                'approval_id'    => $id,
                'dev_request_id' => (int) $data['dev_request_id'],
                'reason'         => (string) $data['reason'],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Console high-risk notify failed: ' . $e->getMessage());
        }

        return build_json_success(['approval_id' => $id], 'High-risk override recorded.', 201);
    }
}
