<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\BotReportsModel;
use CodeIgniter\HTTP\ResponseInterface;

class BotReportsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new BotReportsModel();
        $devRequestId = $this->request->getGet('dev_request_id');
        if ($devRequestId !== null && $devRequestId !== '') {
            $model = $model->where('dev_request_id', (int) $devRequestId);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }

    public function show(int $id): ResponseInterface
    {
        $row = (new BotReportsModel())->find($id);
        return $row ? build_json_success($row) : build_json_not_found('Bot report not found.');
    }
}
