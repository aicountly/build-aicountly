<?php

namespace App\Models;

use CodeIgniter\Model;

class PullRequestsModel extends Model
{
    protected $table         = 'build_pull_requests';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'dev_request_id', 'repo_id', 'pr_number', 'url',
        'branch', 'target_branch',
        'title', 'body', 'status', 'workflow_status',
        'approved_by', 'approved_at',
    ];
}
