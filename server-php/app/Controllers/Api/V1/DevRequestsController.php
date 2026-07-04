<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\DevRequestEventsModel;
use App\Models\DevRequestsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class DevRequestsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $q      = $this->request->getGet();
        $model  = new DevRequestsModel();

        if (! empty($q['status']))     $model = $model->where('status', $q['status']);
        if (! empty($q['source']))     $model = $model->where('source_portal', $q['source']);
        if (! empty($q['risk']))       $model = $model->where('risk_level', $q['risk']);
        if (! empty($q['repo_id']))    $model = $model->where('repo_id', (int) $q['repo_id']);
        if (! empty($q['request_type'])) $model = $model->where('request_type', $q['request_type']);
        if (! empty($q['priority']))   $model = $model->where('priority', $q['priority']);

        $rows = $model->orderBy('id', 'DESC')->limit(200)->find();
        return build_json_success($rows);
    }

    public function show(int $id): ResponseInterface
    {
        $model = new DevRequestsModel();
        $row   = $model->find($id);
        return $row ? build_json_success($row) : build_json_not_found('Development request not found.');
    }

    public function create(): ResponseInterface
    {
        $data   = $this->jsonInput();
        $errors = build_require_fields($data, ['requirement_text']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $model = new DevRequestsModel();
        $id    = (int) $model->insert([
            'source_portal'    => 'manual',
            'source_reference_id' => (string) ($data['source_reference_id'] ?? ''),
            'repo_id'          => isset($data['repo_id']) ? (int) $data['repo_id'] : null,
            'product'          => $data['product']       ?? null,
            'requirement_text' => (string) $data['requirement_text'],
            'request_type'     => (string) ($data['request_type'] ?? 'task'),
            'priority'         => (string) ($data['priority']     ?? 'normal'),
            'risk_level'       => (string) ($data['risk_level']   ?? env('BUILD_BOT_DEFAULT_RISK', 'medium')),
            'status'           => 'received',
            'created_by'       => $this->currentUserId(),
        ], true);

        Services::auditService()->log('dev_request.created', [
            'entity_type' => 'dev_request',
            'entity_id'   => $id,
            'new_value'   => $data,
        ]);

        return build_json_success($model->find($id), 'Development request received.', 201);
    }

    public function update(int $id): ResponseInterface
    {
        $model = new DevRequestsModel();
        $row   = $model->find($id);
        if (! $row) {
            return build_json_not_found('Development request not found.');
        }

        $data = $this->jsonInput();
        $allowed = ['product','request_type','priority','risk_level','requirement_text',
                    'files_likely_affected','code_summary','files_changed_summary',
                    'commit_hash','pr_url','deployment_status','metadata'];
        $patch = array_intersect_key($data, array_flip($allowed));
        foreach (['files_likely_affected','files_changed_summary','metadata'] as $j) {
            if (isset($patch[$j]) && is_array($patch[$j])) {
                $patch[$j] = json_encode($patch[$j]);
            }
        }
        if ($patch !== []) {
            $model->update($id, $patch);
        }

        Services::auditService()->log('dev_request.updated', [
            'entity_type' => 'dev_request',
            'entity_id'   => $id,
            'old_value'   => $row,
            'new_value'   => $patch,
        ]);

        return build_json_success($model->find($id), 'Development request updated.');
    }

    public function transition(int $id): ResponseInterface
    {
        $data = $this->jsonInput();
        $to   = (string) ($data['to_status'] ?? '');
        if ($to === '') {
            return build_json_error('to_status is required.', ['to_status' => 'required'], 422);
        }

        try {
            $updated = Services::devRequestWorkflow()->transition($id, $to, [
                'note'    => $data['note']    ?? null,
                'payload' => $data['payload'] ?? null,
                'force'   => (bool) ($data['force'] ?? false),
                'actor_kind' => 'user',
            ]);
        } catch (\Throwable $e) {
            return build_json_error($e->getMessage(), [], 409);
        }
        return build_json_success($updated, 'Status changed.');
    }

    public function timeline(int $id): ResponseInterface
    {
        $events = (new DevRequestEventsModel())->forRequest($id);
        return build_json_success($events);
    }
}
