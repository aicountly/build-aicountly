<?php

namespace App\Models;

use CodeIgniter\Model;

class ConsoleSyncsModel extends Model
{
    protected $table         = 'build_console_syncs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'kind', 'direction', 'entity_type', 'entity_id',
        'status', 'request', 'response', 'error', 'retry_count',
    ];

    protected array $casts = [
        'request'  => 'json-array',
        'response' => 'json-array',
    ];
}
