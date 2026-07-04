<?php

namespace App\Models;

use CodeIgniter\Model;

class ScreenshotsModel extends Model
{
    protected $table         = 'build_screenshots';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'dev_request_id', 'playwright_job_id', 'phase',
        'url', 'path', 'worker_job_id',
        'width', 'height', 'meta', 'created_at',
    ];

    protected array $casts = ['meta' => 'json-array'];
}
