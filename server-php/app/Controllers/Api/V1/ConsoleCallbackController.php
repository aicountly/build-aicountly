<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ApprovalsModel;
use App\Models\ConsoleSyncsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Handles inbound approval callbacks from Console.
 * Filter `console-inbound` validated the shared token.
 *
 * Payload contract:
 *   {
 *     approval_id: int,            // build_approvals.id
 *     console_ref: string,         // Console's own reference id
 *     decision: "approved"|"rejected",
 *     reason: string,
 *     decided_by_email: string
 *   }
 */
class ConsoleCallbackController extends BaseController
{
    public function approval(): ResponseInterface
    {
        $payload = $this->jsonInput();
        $errors = build_require_fields($payload, ['approval_id', 'decision']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $decision = strtolower((string) $payload['decision']);
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            return build_json_error('decision must be "approved" or "rejected".', ['decision' => 'invalid'], 422);
        }

        $approvals = new ApprovalsModel();
        $approval  = $approvals->find((int) $payload['approval_id']);
        if (! $approval) {
            return build_json_not_found('Approval not found.');
        }

        $approvals->update($approval['id'], [
            'status'      => $decision,
            'reason'      => $payload['reason'] ?? $approval['reason'],
            'console_ref' => $payload['console_ref'] ?? $approval['console_ref'],
            'decided_at'  => date('Y-m-d H:i:s'),
        ]);

        // Log the inbound callback into the sync table for observability.
        (new ConsoleSyncsModel())->insert([
            'kind'        => 'approval_callback',
            'direction'   => 'inbound',
            'entity_type' => $approval['entity_type'],
            'entity_id'   => $approval['entity_id'],
            'status'      => 'delivered',
            'request'     => json_encode($payload),
            'response'    => json_encode(['ok' => true]),
        ]);

        Services::auditService()->log("approval.callback.{$decision}", [
            'entity_type' => $approval['entity_type'],
            'entity_id'   => $approval['entity_id'],
            'metadata'    => [
                'approval_id'      => $approval['id'],
                'action'           => $approval['action'],
                'reason'           => $payload['reason'] ?? null,
                'decided_by_email' => $payload['decided_by_email'] ?? null,
            ],
            'risk_level' => in_array($approval['action'], ['prod_deploy', 'high_risk_override'], true) ? 'critical' : 'info',
        ]);

        return build_json_success(['approval_id' => (int) $approval['id'], 'status' => $decision]);
    }
}
