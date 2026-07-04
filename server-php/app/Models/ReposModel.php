<?php

namespace App\Models;

use CodeIgniter\Model;

class ReposModel extends Model
{
    protected $table         = 'build_repos';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'repo_code', 'repo_name', 'product',
        'github_org', 'github_repo', 'local_path',
        'default_branch', 'protected_branch', 'allowed_working_branch_prefix',
        'deployment_type', 'staging_url', 'production_url',
        'enabled', 'last_sync_at', 'notes',
    ];

    public function findByCode(string $code): ?array
    {
        return $this->where('repo_code', $code)->first();
    }

    public function enabled(): array
    {
        return $this->where('enabled', true)->orderBy('repo_name')->findAll();
    }
}
