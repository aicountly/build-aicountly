<?php

namespace App\Models;

use CodeIgniter\Model;

class BotPlansModel extends Model
{
    protected $table         = 'build_bot_plans';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'dev_request_id', 'ai_provider', 'ai_model',
        'summary', 'plan', 'test_plan', 'risk_level',
        'files_estimated', 'tokens_used', 'created_by_bot',
    ];

    protected array $casts = [
        'plan'            => 'json-array',
        'test_plan'       => 'json-array',
        'files_estimated' => 'json-array',
    ];

    public function latestForRequest(int $devRequestId): ?array
    {
        return $this->where('dev_request_id', $devRequestId)
            ->orderBy('id', 'DESC')
            ->first();
    }
}
