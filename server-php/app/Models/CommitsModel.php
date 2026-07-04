<?php

namespace App\Models;

use CodeIgniter\Model;

class CommitsModel extends Model
{
    protected $table         = 'build_commits';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'dev_request_id', 'repo_id', 'branch', 'sha', 'message',
        'diff_summary', 'status', 'created_by_bot',
        'approved_by', 'approved_at', 'error',
    ];

    protected array $casts = ['diff_summary' => 'json-array'];
}
