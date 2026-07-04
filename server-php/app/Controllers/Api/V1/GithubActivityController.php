<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\GithubActivityModel;
use CodeIgniter\HTTP\ResponseInterface;

class GithubActivityController extends BaseController
{
    public function index(): ResponseInterface
    {
        $model = new GithubActivityModel();
        if (! empty($repoId = $this->request->getGet('repo_id'))) {
            $model = $model->where('repo_id', (int) $repoId);
        }
        if (! empty($devRequestId = $this->request->getGet('dev_request_id'))) {
            $model = $model->where('dev_request_id', (int) $devRequestId);
        }
        return build_json_success($model->orderBy('id', 'DESC')->limit(200)->find());
    }
}
