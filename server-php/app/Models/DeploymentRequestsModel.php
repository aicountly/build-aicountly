<?php

namespace App\Models;

use CodeIgniter\Model;

class DeploymentRequestsModel extends Model
{
    protected $table         = 'build_deployment_requests';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'dev_request_id', 'repo_id', 'environment', 'status',
        'requester_id', 'approved_by', 'approved_at', 'deployed_at',
        'provider', 'provider_ref', 'notes', 'metadata',
    ];

    protected array $casts = ['metadata' => 'json-array'];
}
