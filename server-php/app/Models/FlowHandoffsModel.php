<?php

namespace App\Models;

use CodeIgniter\Model;

class FlowHandoffsModel extends Model
{
    protected $table         = 'build_flow_handoffs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'flow_handoff_id', 'source_type', 'source_id', 'source_portal',
        'raw_payload', 'dev_request_id',
        'received_at', 'processed_at', 'status',
    ];

    protected array $casts = ['raw_payload' => 'json-array'];

    public function findByFlowId(int $flowHandoffId): ?array
    {
        return $this->where('flow_handoff_id', $flowHandoffId)->first();
    }
}
