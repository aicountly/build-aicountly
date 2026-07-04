<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ReposController extends BaseController
{
    public function index(): ResponseInterface
    {
        $enabled = filter_var($this->request->getGet('enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $rows = Services::repoRegistry()->list($enabled === true);
        return build_json_success($rows);
    }

    public function show(int $id): ResponseInterface
    {
        $row = Services::repoRegistry()->find($id);
        return $row ? build_json_success($row) : build_json_not_found('Repo not found.');
    }

    public function create(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['repo_code', 'repo_name']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        try {
            $id = Services::repoRegistry()->create($data);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 400);
        }

        Services::auditService()->log('repo.created', [
            'entity_type' => 'repo',
            'entity_id'   => $id,
            'new_value'   => $data,
        ]);

        return build_json_success(Services::repoRegistry()->find($id), 'Repo registered.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $repo = Services::repoRegistry()->find($id);
        if (! $repo) {
            return build_json_not_found('Repo not found.');
        }

        $data = $this->jsonInput();
        try {
            Services::repoRegistry()->update($id, $data);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 400);
        }

        Services::auditService()->log('repo.updated', [
            'entity_type' => 'repo',
            'entity_id'   => $id,
            'old_value'   => $repo,
            'new_value'   => $data,
        ]);

        return build_json_success(Services::repoRegistry()->find($id), 'Repo updated.');
    }

    public function delete(int $id): ResponseInterface
    {
        $repo = Services::repoRegistry()->find($id);
        if (! $repo) {
            return build_json_not_found('Repo not found.');
        }
        Services::repoRegistry()->delete($id);
        Services::auditService()->log('repo.deleted', [
            'entity_type' => 'repo',
            'entity_id'   => $id,
            'old_value'   => $repo,
            'risk_level'  => 'high',
        ]);
        return build_json_success(null, 'Repo removed.');
    }

    public function sync(int $id): ResponseInterface
    {
        $repo = Services::repoRegistry()->find($id);
        if (! $repo) {
            return build_json_not_found('Repo not found.');
        }

        $github = Services::github();
        if (! $github->isConfigured()) {
            return build_json_error('GitHub is not configured.', ['env' => 'BUILD_GITHUB_TOKEN'], 503);
        }

        try {
            $liveRepo = $github->getRepo((string) $repo['github_org'], (string) $repo['github_repo']);
            Services::repoRegistry()->update($id, [
                'default_branch' => (string) ($liveRepo['default_branch'] ?? $repo['default_branch']),
                'protected_branch' => (string) ($liveRepo['default_branch'] ?? $repo['protected_branch']),
            ]);
            Services::repoRegistry()->markSynced($id);
        } catch (\Throwable $e) {
            return build_json_error('GitHub sync failed: ' . $e->getMessage(), [], 502);
        }

        Services::auditService()->log('repo.synced', [
            'entity_type' => 'repo',
            'entity_id'   => $id,
        ]);

        return build_json_success(Services::repoRegistry()->find($id), 'Repo synced.');
    }
}
