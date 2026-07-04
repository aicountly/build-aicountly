<?php

namespace App\Models;

use CodeIgniter\Model;

class ApprovalsModel extends Model
{
    protected $table         = 'build_approvals';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'entity_type', 'entity_id', 'action', 'status', 'reason',
        'payload', 'requester_id', 'decided_by', 'decided_at', 'console_ref',
    ];

    protected array $casts = ['payload' => 'json-array'];

    public const ACTIONS = ['code', 'commit', 'pr', 'staging_deploy', 'prod_deploy', 'high_risk_override'];

    public function findApproved(string $entityType, int $entityId, string $action): ?array
    {
        return $this->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('action', $action)
            ->where('status', 'approved')
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function findPending(string $entityType, int $entityId, string $action): ?array
    {
        return $this->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('action', $action)
            ->where('status', 'pending')
            ->orderBy('id', 'DESC')
            ->first();
    }
}
