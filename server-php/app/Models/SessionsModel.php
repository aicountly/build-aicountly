<?php

namespace App\Models;

use CodeIgniter\Model;

class SessionsModel extends Model
{
    protected $table         = 'build_sessions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'user_id', 'token_hash', 'ip_address', 'user_agent',
        'revoked_at', 'expires_at', 'created_at',
    ];

    public function findActiveByHash(string $tokenHash): ?array
    {
        return $this->where('token_hash', $tokenHash)
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }
}
