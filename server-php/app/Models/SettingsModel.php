<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingsModel extends Model
{
    protected $table         = 'build_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['key', 'value_json', 'description', 'updated_by'];

    protected array $casts = ['value_json' => 'json-array'];

    public function getValue(string $key, mixed $default = null): mixed
    {
        $row = $this->where('key', $key)->first();
        if (! $row) {
            return $default;
        }
        $decoded = is_string($row['value_json']) ? json_decode($row['value_json'], true) : $row['value_json'];
        return $decoded ?? $default;
    }

    public function setValue(string $key, mixed $value, ?int $userId = null): void
    {
        $encoded  = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $existing = $this->where('key', $key)->first();

        if ($existing) {
            $this->update($existing['id'], [
                'value_json' => $encoded,
                'updated_by' => $userId,
            ]);
        } else {
            $this->insert([
                'key'        => $key,
                'value_json' => $encoded,
                'updated_by' => $userId,
            ]);
        }
    }
}
