<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\CodeTasksModel;
use CodeIgniter\HTTP\ResponseInterface;

class CodeTasksController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new CodeTasksModel();
        if (! empty($devRequestId = $this->request->getGet('dev_request_id'))) {
            $model = $model->where('dev_request_id', (int) $devRequestId);
        }
        if (! empty($status = $this->request->getGet('status'))) {
            $model = $model->where('status', $status);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new CodeTasksModel())->find($id);
        return $row ? build_json_success($row) : build_json_not_found('Code task not found.');
    }
}
