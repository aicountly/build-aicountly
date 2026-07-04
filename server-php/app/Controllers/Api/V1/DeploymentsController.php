<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\DeploymentRequestsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class DeploymentsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new DeploymentRequestsModel();
        if (! empty($env = $this->request->getGet('environment'))) {
            $model = $model->where('environment', $env);
        }
        if (! empty($devRequestId = $this->request->getGet('dev_request_id'))) {
            $model = $model->where('dev_request_id', (int) $devRequestId);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }

    public function requestStaging(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['dev_request_id']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        try {
            Services::safetyGuard()->assertAllowed('staging_deploy', (int) $data['dev_request_id']);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 403);
        }

        $id = Services::deploymentRequestService()->requestStaging(
            (int) $data['dev_request_id'],
            isset($data['repo_id']) ? (int) $data['repo_id'] : null,
            $this->currentUserId(),
            $data['notes'] ?? null,
        );

        Services::devRequestWorkflow()->transition((int) $data['dev_request_id'], 'pending_staging_deployment', [
            'actor_kind' => 'user',
            'note'       => 'Staging deployment requested.',
            'payload'    => ['deployment_id' => $id],
        ]);

        return build_json_success(['deployment_id' => $id], 'Staging deployment requested.', 201);
    }

    public function requestProduction(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['dev_request_id']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        try {
            Services::safetyGuard()->assertAllowed('prod_deploy', (int) $data['dev_request_id']);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 403);
        }

        $id = Services::deploymentRequestService()->requestProduction(
            (int) $data['dev_request_id'],
            isset($data['repo_id']) ? (int) $data['repo_id'] : null,
            $this->currentUserId(),
            $data['notes'] ?? null,
        );

        Services::devRequestWorkflow()->transition((int) $data['dev_request_id'], 'pending_production_approval', [
            'actor_kind' => 'user',
            'note'       => 'Production deployment requested.',
            'payload'    => ['deployment_id' => $id],
        ]);

        return build_json_success(['deployment_id' => $id], 'Production deployment requested.', 201);
    }

    public function approve(int $id): ResponseInterface
    {
        $row = (new DeploymentRequestsModel())->find($id);
        if (! $row) {
            return build_json_not_found('Deployment request not found.');
        }

        $action = $row['environment'] === 'production' ? 'prod_deploy' : 'staging_deploy';
        try {
            Services::safetyGuard()->assertAllowed($action, (int) $row['dev_request_id']);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 403);
        }

        Services::deploymentRequestService()->markApproved($id, (int) $this->currentUserId());
        return build_json_success((new DeploymentRequestsModel())->find($id), 'Deployment approved.');
    }

    public function markDeployed(int $id): ResponseInterface
    {
        $row = (new DeploymentRequestsModel())->find($id);
        if (! $row) {
            return build_json_not_found('Deployment request not found.');
        }
        $data = $this->jsonInput();

        Services::deploymentRequestService()->markDeployed($id, $data['provider_ref'] ?? null);

        $targetStatus = $row['environment'] === 'production' ? 'production_deployed' : 'staging_deployed';
        Services::devRequestWorkflow()->transition((int) $row['dev_request_id'], $targetStatus, [
            'actor_kind' => 'user',
            'note'       => "Deployment to {$row['environment']} confirmed.",
        ]);

        return build_json_success((new DeploymentRequestsModel())->find($id), 'Deployment recorded.');
    }
}
