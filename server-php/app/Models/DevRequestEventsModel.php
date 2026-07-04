<?php

namespace App\Models;

use CodeIgniter\Model;

class DevRequestEventsModel extends Model
{
    protected $table         = 'build_dev_request_events';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'dev_request_id', 'actor_id', 'actor_email', 'actor_kind',
        'event', 'from_status', 'to_status', 'note', 'payload', 'created_at',
    ];

    protected array $casts = ['payload' => 'json-array'];

    public function forRequest(int $devRequestId): array
    {
        return $this->where('dev_request_id', $devRequestId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
