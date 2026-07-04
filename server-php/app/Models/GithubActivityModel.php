<?php

namespace App\Models;

use CodeIgniter\Model;

class GithubActivityModel extends Model
{
    protected $table         = 'build_github_activity';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'repo_id', 'dev_request_id', 'kind', 'ref', 'actor', 'payload', 'created_at',
    ];

    protected array $casts = ['payload' => 'json-array'];
}
