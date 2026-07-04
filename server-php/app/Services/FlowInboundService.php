<?php

namespace App\Services;

use App\Models\DevRequestsModel;
use App\Models\FlowHandoffsModel;
use App\Models\ReposModel;
use Config\Services;
use RuntimeException;

/**
 * Handles inbound Flow handoffs. Idempotent on `flow_handoff_id`:
 * calling create() twice with the same id returns the existing dev_request.
 *
 * Accepts payload shape from flow-react-app/server-php/app/Libraries/BuildApiClient.php:
 *   {
 *     flow_handoff_id: int (required),
 *     source_type: string, source_id: int,
 *     title: string, description: string, priority: string, product: string,
 *     requested_by: string
 *   }
 */
class FlowInboundService
{
    private FlowHandoffsModel $handoffs;
    private DevRequestsModel  $requests;
    private ReposModel        $repos;

    public function __construct()
    {
        $this->handoffs = new FlowHandoffsModel();
        $this->requests = new DevRequestsModel();
        $this->repos    = new ReposModel();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{handoff: array<string, mixed>, dev_request: array<string, mixed>, created: bool}
     */
    public function create(array $payload, string $source = 'flow.aicountly.org'): array
    {
        $flowHandoffId = (int) ($payload['flow_handoff_id'] ?? 0);
        if ($flowHandoffId <= 0) {
            throw new RuntimeException('flow_handoff_id is required.');
        }

        // Idempotent short-circuit.
        $existing = $this->handoffs->findByFlowId($flowHandoffId);
        if ($existing) {
            return [
                'handoff'     => $existing,
                'dev_request' => $existing['dev_request_id'] ? ($this->requests->find($existing['dev_request_id']) ?? []) : [],
                'created'     => false,
            ];
        }

        $product = (string) ($payload['product'] ?? '');
        $repoId  = null;
        if ($product !== '') {
            $repo = $this->repos->where('product', $product)->first();
            if ($repo) {
                $repoId = (int) $repo['id'];
            }
        }

        $title       = trim((string) ($payload['title'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $requirement = trim($title . "\n\n" . $description);
        if ($requirement === '') {
            $requirement = '(no requirement text provided by Flow)';
        }

        $requestType = $this->mapSourceTypeToRequestType((string) ($payload['source_type'] ?? ''));
        $priority    = $this->normalisePriority((string) ($payload['priority'] ?? 'normal'));

        $devRequestId = (int) $this->requests->insert([
            'source_portal'       => 'flow',
            'source_reference_id' => (string) ($payload['source_id'] ?? $flowHandoffId),
            'repo_id'             => $repoId,
            'product'             => $product ?: null,
            'requirement_text'    => $requirement,
            'request_type'        => $requestType,
            'priority'            => $priority,
            'risk_level'          => (string) env('BUILD_BOT_DEFAULT_RISK', 'medium'),
            'status'              => 'received',
            'metadata'            => json_encode([
                'flow_handoff_id' => $flowHandoffId,
                'source'          => $source,
                'requested_by'    => (string) ($payload['requested_by'] ?? ''),
                'raw'             => $payload,
            ]),
        ], true);

        $handoffId = (int) $this->handoffs->insert([
            'flow_handoff_id' => $flowHandoffId,
            'source_type'     => (string) ($payload['source_type'] ?? ''),
            'source_id'       => (int) ($payload['source_id'] ?? 0),
            'source_portal'   => $source,
            'raw_payload'     => json_encode($payload),
            'dev_request_id'  => $devRequestId,
            'received_at'     => date('Y-m-d H:i:s'),
            'processed_at'    => date('Y-m-d H:i:s'),
            'status'          => 'acknowledged',
        ], true);

        Services::auditService()->log('dev_request.created_from_flow', [
            'entity_type' => 'dev_request',
            'entity_id'   => $devRequestId,
            'metadata'    => [
                'flow_handoff_id' => $flowHandoffId,
                'source_type'     => $payload['source_type'] ?? null,
                'source_id'       => $payload['source_id']   ?? null,
            ],
        ]);

        return [
            'handoff'     => $this->handoffs->find($handoffId) ?? [],
            'dev_request' => $this->requests->find($devRequestId) ?? [],
            'created'     => true,
        ];
    }

    private function mapSourceTypeToRequestType(string $sourceType): string
    {
        return match (strtolower($sourceType)) {
            'bug', 'bug_report' => 'bug',
            'feature', 'feature_request' => 'feature',
            'ticket'            => 'task',
            'ui_fix'            => 'ui_fix',
            'security'          => 'security',
            'refactor'          => 'refactor',
            default             => 'task',
        };
    }

    private function normalisePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));
        return in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal';
    }
}
