<?php

namespace App\Services;

use App\Models\ApprovalsModel;
use Config\Services;
use RuntimeException;

/**
 * Handles creation, approval and rejection of Approval rows for the six
 * approval actions: code, commit, pr, staging_deploy, prod_deploy, high_risk_override.
 *
 * Every state change is an AuditService action (approval.*), which reaches Console's central
 * audit; approvals are decided here in Build.
 */
class ApprovalService
{
    private ApprovalsModel $model;

    public function __construct()
    {
        $this->model = new ApprovalsModel();
    }

    public function request(int $devRequestId, string $action, ?int $requesterId, array $payload = []): int
    {
        if (! in_array($action, ApprovalsModel::ACTIONS, true)) {
            throw new RuntimeException("Unknown approval action: {$action}");
        }

        // If a pending approval for the same (entity, action) already exists, return it.
        $existing = $this->model->findPending('dev_request', $devRequestId, $action);
        if ($existing) {
            return (int) $existing['id'];
        }

        $id = (int) $this->model->insert([
            'entity_type'  => 'dev_request',
            'entity_id'    => $devRequestId,
            'action'       => $action,
            'status'       => 'pending',
            'payload'      => $payload,
            'requester_id' => $requesterId,
        ], true);

        // The request (and its payload, when small enough for Console's 64 KB metadata limit)
        // reaches Console through the central audit (approval.* is fanned out).
        $payloadJson = json_encode($payload);
        Services::auditService()->log('approval.requested', [
            'entity_type' => 'dev_request',
            'entity_id'   => $devRequestId,
            'metadata'    => ['action' => $action, 'approval_id' => $id]
                + (is_string($payloadJson) && strlen($payloadJson) <= 16384
                    ? ['payload' => $payload]
                    : ['payload_omitted_bytes' => is_string($payloadJson) ? strlen($payloadJson) : null]),
        ]);

        return $id;
    }

    public function approve(int $approvalId, int $decidedBy, ?string $reason = null): array
    {
        return $this->decide($approvalId, 'approved', $decidedBy, $reason);
    }

    public function reject(int $approvalId, int $decidedBy, ?string $reason = null): array
    {
        return $this->decide($approvalId, 'rejected', $decidedBy, $reason);
    }

    private function decide(int $approvalId, string $decision, int $decidedBy, ?string $reason): array
    {
        $row = $this->model->find($approvalId);
        if (! $row) {
            throw new RuntimeException("Approval #{$approvalId} not found.");
        }
        if ($row['status'] !== 'pending') {
            throw new RuntimeException("Approval #{$approvalId} is not pending (currently: {$row['status']}).");
        }

        $this->model->update($approvalId, [
            'status'     => $decision,
            'reason'     => $reason,
            'decided_by' => $decidedBy,
            'decided_at' => date('Y-m-d H:i:s'),
        ]);

        Services::auditService()->log("approval.{$decision}", [
            'entity_type' => 'dev_request',
            'entity_id'   => $row['entity_id'],
            'metadata'    => [
                'approval_id' => $approvalId,
                'action'      => $row['action'],
                'reason'      => $reason,
            ],
            'risk_level'  => in_array($row['action'], ['prod_deploy', 'high_risk_override'], true) ? 'critical' : 'info',
        ]);

        return $this->model->find($approvalId) ?? $row;
    }

    /** @return list<array<string, mixed>> */
    public function pending(?string $action = null): array
    {
        $q = $this->model->where('status', 'pending')->orderBy('id', 'DESC');
        if ($action) {
            $q = $q->where('action', $action);
        }
        return $q->findAll();
    }

    public function findByConsoleRef(string $consoleRef): ?array
    {
        return $this->model->where('console_ref', $consoleRef)->first();
    }
}
