<?php

namespace App\Models;

use CodeIgniter\Model;

class CodeTasksModel extends Model
{
    protected $table         = 'build_code_tasks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'dev_request_id', 'repo_id', 'task_index', 'kind', 'status',
        'payload', 'result', 'error', 'ran_at',
    ];

    protected array $casts = [
        'payload' => 'json-array',
        'result'  => 'json-array',
    ];

    public const DESTRUCTIVE_KINDS = ['file_delete', 'branch_delete', 'force_push'];
}
