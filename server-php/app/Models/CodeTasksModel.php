<?php

namespace App\Models;

class CodeTasksModel extends BuildModel
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
        'payload' => '?json-array',
        'result'  => '?json-array',
    ];

    public const DESTRUCTIVE_KINDS = ['file_delete', 'branch_delete', 'force_push'];
}
