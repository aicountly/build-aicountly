<?php

namespace App\Models;

use CodeIgniter\Model;

class AiProviderCallsModel extends Model
{
    protected $table         = 'build_ai_provider_calls';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'dev_request_id', 'provider', 'model', 'endpoint',
        'prompt_hash', 'tokens_in', 'tokens_out', 'latency_ms',
        'cost_estimate', 'status', 'error', 'created_at',
    ];
}
