<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogsModel extends Model
{
    protected $table         = 'build_audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'action', 'actor_id', 'actor_email',
        'entity_type', 'entity_id', 'risk_level',
        'old_value', 'new_value',
        'ip_address', 'user_agent', 'metadata', 'created_at',
    ];

    protected array $casts = [
        'old_value' => 'json-array',
        'new_value' => 'json-array',
        'metadata'  => 'json-array',
    ];
}
