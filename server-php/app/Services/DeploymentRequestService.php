<?php

namespace App\Services;

use App\Models\DeploymentRequestsModel;
use Config\Services;
use RuntimeException;

/**
 * Tracks staging and production deployment requests. Never executes an
 * actual deployment: if BUILD_DEPLOY_API_URL is empty, the request lands in
 * the 'provider_unconfigured' status and the UI shows "provider not configured".
 *
 * Production deployment always requires a *separate* prod_deploy approval —
 * even if staging_deploy or code approval already exist for the same request.
 */
class DeploymentRequestService
{
    private DeploymentRequestsModel $model;

    public function __construct()
    {
        $this->model = new DeploymentRequestsModel();
    }

    public function requestStaging(int $devRequestId, ?int $repoId, ?int $requesterId, ?string $notes = null): int
    {
        return $this->request($devRequestId, $repoId, 'staging', $requesterId, $notes);
    }

    public function requestProduction(int $devRequestId, ?int $repoId, ?int $requesterId, ?string $notes = null): int
    {
        return $this->request($devRequestId, $repoId, 'production', $requesterId, $notes);
    }

    private function request(int $devRequestId, ?int $repoId, string $environment, ?int $requesterId, ?string $notes): int
    {
        $id = (int) $this->model->insert([
            'dev_request_id' => $devRequestId,
            'repo_id'        => $repoId,
            'environment'    => $environment,
            'status'         => 'requested',
            'requester_id'   => $requesterId,
            'provider'       => 'cpanel',
            'notes'          => $notes,
        ], true);

        Services::auditService()->log("deployment.requested.{$environment}", [
            'entity_type' => 'dev_request',
            'entity_id'   => $devRequestId,
            'metadata'    => ['deployment_id' => $id, 'environment' => $environment],
            'risk_level'  => $environment === 'production' ? 'high' : 'info',
        ]);

        try {
            Services::consoleClient()->sendDeploymentRequested([
                'deployment_id'  => $id,
                'dev_request_id' => $devRequestId,
                'environment'    => $environment,
                'repo_id'        => $repoId,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Console deployment notify failed: ' . $e->getMessage());
        }

        return $id;
    }

    /**
     * Mark a deployment as approved. For production this MUST be preceded by an
     * approved prod_deploy row in build_approvals — the caller (Controller)
     * enforces this via SafetyGuardService.
     */
    public function markApproved(int $deploymentId, int $decidedBy): void
    {
        $row = $this->model->find($deploymentId);
        if (! $row) {
            throw new RuntimeException("Deployment request #{$deploymentId} not found.");
        }

        $this->model->update($deploymentId, [
            'status'      => 'approved',
            'approved_by' => $decidedBy,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        // Attempt to hand off to the deploy provider — or record provider_unconfigured.
        $providerUrl = (string) env('BUILD_DEPLOY_API_URL', '');
        if ($providerUrl === '') {
            $this->model->update($deploymentId, ['status' => 'provider_unconfigured']);
        }
        // Real provider dispatch is intentionally left as a NoOp — Build hands off
        // to the target repo's deploy pipeline once it exists.

        Services::auditService()->log("deployment.approved.{$row['environment']}", [
            'entity_type' => 'dev_request',
            'entity_id'   => $row['dev_request_id'],
            'metadata'    => ['deployment_id' => $deploymentId, 'environment' => $row['environment']],
            'risk_level'  => $row['environment'] === 'production' ? 'critical' : 'info',
        ]);

        try {
            Services::consoleClient()->sendDeploymentStatus([
                'deployment_id' => $deploymentId,
                'dev_request_id' => $row['dev_request_id'],
                'environment'   => $row['environment'],
                'status'        => 'approved',
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Console deployment status notify failed: ' . $e->getMessage());
        }
    }

    public function markDeployed(int $deploymentId, ?string $providerRef = null): void
    {
        $row = $this->model->find($deploymentId);
        if (! $row) {
            throw new RuntimeException("Deployment request #{$deploymentId} not found.");
        }

        $this->model->update($deploymentId, [
            'status'       => 'deployed',
            'deployed_at'  => date('Y-m-d H:i:s'),
            'provider_ref' => $providerRef,
        ]);

        Services::auditService()->log("deployment.deployed.{$row['environment']}", [
            'entity_type' => 'dev_request',
            'entity_id'   => $row['dev_request_id'],
            'metadata'    => ['deployment_id' => $deploymentId, 'environment' => $row['environment'], 'provider_ref' => $providerRef],
            'risk_level'  => $row['environment'] === 'production' ? 'critical' : 'info',
        ]);
    }

    public function isDeployProviderConfigured(): bool
    {
        return (string) env('BUILD_DEPLOY_API_URL', '') !== '';
    }
}
