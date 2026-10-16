<?php

namespace App\Models;

class PlaywrightJobsModel extends BuildModel
{
    protected $table         = 'build_playwright_jobs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'dev_request_id', 'repo_id', 'kind', 'target_url',
        'status', 'worker_ref',
        'request', 'response', 'error',
        'requested_at', 'completed_at',
    ];

    protected array $casts = [
        'request'  => '?json-array',
        'response' => '?json-array',
    ];
}
