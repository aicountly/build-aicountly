<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ConsoleSyncsModel;
use CodeIgniter\HTTP\ResponseInterface;

class ConsoleSyncController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new ConsoleSyncsModel();
        if (! empty($status = $this->request->getGet('status'))) {
            $model = $model->where('status', $status);
        }
        if (! empty($dir = $this->request->getGet('direction'))) {
            $model = $model->where('direction', $dir);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }
}
