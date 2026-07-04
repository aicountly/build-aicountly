<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\PullRequestsModel;
use App\Models\ReposModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class PullRequestsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new PullRequestsModel();
        $devRequestId = $this->request->getGet('dev_request_id');
        if ($devRequestId !== null && $devRequestId !== '') {
            $model = $model->where('dev_request_id', (int) $devRequestId);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new PullRequestsModel())->find($id);
        return $row ? build_json_success($row) : build_json_not_found('Pull request not found.');
    }

    public function approve(int $id): ResponseInterface
    {
        $pr = (new PullRequestsModel())->find($id);
        if (! $pr) {
            return build_json_not_found('Pull request not found.');
        }

        try {
            Services::safetyGuard()->assertAllowed('pr', (int) $pr['dev_request_id'], [
                'repo_id' => (int) $pr['repo_id'],
                'branch'  => (string) ($pr['branch'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 403);
        }

        (new PullRequestsModel())->update($id, [
            'status'      => 'approved',
            'approved_by' => $this->currentUserId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        Services::auditService()->log('pr.approved', [
            'entity_type' => 'pull_request',
            'entity_id'   => $id,
            'metadata'    => ['dev_request_id' => $pr['dev_request_id']],
        ]);

        return build_json_success((new PullRequestsModel())->find($id), 'PR approved.');
    }

    public function execute(int $id): ResponseInterface
    {
        $pr = (new PullRequestsModel())->find($id);
        if (! $pr) {
            return build_json_not_found('Pull request not found.');
        }
        if ($pr['status'] !== 'approved') {
            return build_json_error('PR must be approved before creation.', [], 409);
        }

        $repo = (new ReposModel())->find((int) $pr['repo_id']);
        if (! $repo) {
            return build_json_error('Repo not found for PR.', [], 400);
        }

        try {
            $created = Services::github()->createPullRequest(
                (string) $repo['github_org'],
                (string) $repo['github_repo'],
                (string) ($pr['title']  ?? 'Build bot proposal'),
                (string) ($pr['branch'] ?? ''),
                (string) ($pr['target_branch'] ?? $repo['default_branch']),
                (string) ($pr['body']   ?? ''),
            );
        } catch (\Throwable $e) {
            return build_json_error('PR creation failed: ' . $e->getMessage(), [], 502);
        }

        (new PullRequestsModel())->update($id, [
            'status'    => 'open',
            'pr_number' => (int) ($created['number'] ?? 0),
            'url'       => (string) ($created['html_url'] ?? ''),
        ]);

        Services::auditService()->log('pr.created', [
            'entity_type' => 'pull_request',
            'entity_id'   => $id,
            'metadata'    => ['pr_number' => $created['number'] ?? null, 'url' => $created['html_url'] ?? null],
        ]);

        try {
            Services::consoleClient()->sendPrCreated([
                'pr_id'          => $id,
                'dev_request_id' => (int) $pr['dev_request_id'],
                'pr_number'      => (int) ($created['number'] ?? 0),
                'url'            => (string) ($created['html_url'] ?? ''),
                'repo'           => (string) $repo['repo_code'],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Console pr.created notify failed: ' . $e->getMessage());
        }

        Services::devRequestWorkflow()->transition((int) $pr['dev_request_id'], 'pr_created', [
            'actor_kind' => 'bot',
            'note'       => 'PR opened: #' . ($created['number'] ?? '?'),
        ]);

        return build_json_success((new PullRequestsModel())->find($id), 'PR created.');
    }
}
