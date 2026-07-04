<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\CommitsModel;
use App\Models\ReposModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class CommitsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new CommitsModel();
        $devRequestId = $this->request->getGet('dev_request_id');
        if ($devRequestId !== null && $devRequestId !== '') {
            $model = $model->where('dev_request_id', (int) $devRequestId);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }

    public function approve(int $id): ResponseInterface
    {
        $commit = (new CommitsModel())->find($id);
        if (! $commit) {
            return build_json_not_found('Commit not found.');
        }

        // Ensure there is an approved `commit` approval for the dev request.
        try {
            Services::safetyGuard()->assertAllowed('commit', (int) $commit['dev_request_id'], [
                'repo_id' => (int) $commit['repo_id'],
                'branch'  => (string) $commit['branch'],
            ]);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 403);
        }

        (new CommitsModel())->update($id, [
            'status'      => 'approved',
            'approved_by' => $this->currentUserId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        Services::auditService()->log('commit.approved', [
            'entity_type' => 'commit',
            'entity_id'   => $id,
            'metadata'    => ['dev_request_id' => $commit['dev_request_id']],
        ]);

        return build_json_success((new CommitsModel())->find($id), 'Commit approved.');
    }

    /**
     * Executes an approved commit by talking to GitHub. Fails cleanly if
     * GitHub is not configured.
     */
    public function execute(int $id): ResponseInterface
    {
        $commit = (new CommitsModel())->find($id);
        if (! $commit) {
            return build_json_not_found('Commit not found.');
        }
        if ($commit['status'] !== 'approved') {
            return build_json_error('Commit must be approved before execution.', [], 409);
        }

        $repo = (new ReposModel())->find((int) $commit['repo_id']);
        if (! $repo) {
            return build_json_error('Repo not found for commit.', [], 400);
        }

        try {
            Services::safetyGuard()->assertBranchAllowed((int) $repo['id'], (string) $commit['branch']);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 403);
        }

        $files = (array) ($commit['diff_summary'] ?? []);
        // Normalise to {path => content}. If empty, we still create an empty commit stub.
        $filesByPath = [];
        foreach ($files as $f) {
            if (isset($f['path'], $f['after'])) {
                $filesByPath[(string) $f['path']] = (string) $f['after'];
            }
        }

        try {
            $github = Services::github();
            $created = $github->createCommit(
                (string) $repo['github_org'],
                (string) $repo['github_repo'],
                (string) $commit['branch'],
                (string) ($commit['message'] ?? 'Build bot commit'),
                $filesByPath,
            );
        } catch (\Throwable $e) {
            (new CommitsModel())->update($id, ['status' => 'failed', 'error' => $e->getMessage()]);
            return build_json_error('Commit execution failed: ' . $e->getMessage(), [], 502);
        }

        (new CommitsModel())->update($id, [
            'status' => 'committed',
            'sha'    => (string) ($created['sha'] ?? ''),
        ]);

        Services::auditService()->log('commit.created', [
            'entity_type' => 'commit',
            'entity_id'   => $id,
            'metadata'    => ['sha' => $created['sha'] ?? null, 'repo' => $repo['repo_code']],
        ]);

        Services::devRequestWorkflow()->transition((int) $commit['dev_request_id'], 'committed', [
            'actor_kind' => 'bot',
            'note'       => 'Commit created: ' . ($created['sha'] ?? ''),
        ]);

        return build_json_success((new CommitsModel())->find($id), 'Commit created on GitHub.');
    }
}
