<?php

namespace App\Models;

use CodeIgniter\Model;

class BotReportsModel extends Model
{
    protected $table         = 'build_bot_reports';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'dev_request_id', 'bot_name', 'ai_provider',
        'understanding', 'repo_id', 'product',
        'files_accessed', 'ui_screenshots',
        'plan', 'code_changes', 'tests_run', 'errors',
        'approval_history', 'commit_details', 'pr_details', 'deployment_details',
        'next_recommended_action', 'raw_metadata',
    ];

    protected array $casts = [
        'files_accessed'      => 'json-array',
        'ui_screenshots'      => 'json-array',
        'plan'                => 'json-array',
        'code_changes'        => 'json-array',
        'tests_run'           => 'json-array',
        'errors'              => 'json-array',
        'approval_history'    => 'json-array',
        'commit_details'      => 'json-array',
        'pr_details'          => 'json-array',
        'deployment_details'  => 'json-array',
        'raw_metadata'        => 'json-array',
    ];
}
