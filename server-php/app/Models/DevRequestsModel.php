<?php

namespace App\Models;

use CodeIgniter\Model;

class DevRequestsModel extends Model
{
    protected $table         = 'build_dev_requests';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'source_portal', 'source_reference_id',
        'repo_id', 'product',
        'requirement_text', 'request_type', 'priority', 'risk_level', 'status',
        'files_likely_affected', 'code_summary', 'files_changed_summary',
        'commit_hash', 'pr_url', 'deployment_status',
        'created_by', 'metadata',
    ];

    protected array $casts = [
        'files_likely_affected' => 'json-array',
        'files_changed_summary' => 'json-array',
        'metadata'              => 'json-array',
    ];

    public const STATUSES = [
        'received','analyzing','plan_prepared','pending_approval','approved_for_code',
        'coding','tests_running','pending_commit_approval','committed','pending_pr_approval',
        'pr_created','pending_staging_deployment','staging_deployed','pending_production_approval',
        'production_deployed','failed','rejected','closed',
    ];

    public const REQUEST_TYPES = ['bug','feature','task','refactor','ui_fix','security','other'];
    public const PRIORITIES    = ['low','normal','high','urgent'];
    public const RISK_LEVELS   = ['low','medium','high','critical'];
    public const SOURCE_PORTALS = ['flow','console','manual'];
}
