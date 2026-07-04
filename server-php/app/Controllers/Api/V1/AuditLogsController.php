<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\AuditLogsModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuditLogsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new AuditLogsModel();
        if (! empty($risk   = $this->request->getGet('risk_level')))  { $model = $model->where('risk_level', $risk); }
        if (! empty($action = $this->request->getGet('action')))       { $model = $model->like('action', $action); }
        if (! empty($entity = $this->request->getGet('entity_type'))) { $model = $model->where('entity_type', $entity); }

        $limit = min(500, max(10, (int) ($this->request->getGet('limit') ?? 100)));
        return build_json_success($model->orderBy('id', 'DESC')->limit($limit)->find());
    }
}
