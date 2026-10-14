<?php

namespace App\Models;

class SettingsModel extends BuildModel
{
    protected $table         = 'build_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = ['key', 'value_json', 'description', 'updated_by'];

    protected array $casts = ['value_json' => '?json-array'];

    /**
     * value_json holds any JSON value (a string such as "confirm", a number,
     * an object). The cast has already decoded it.
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $row = $this->where('key', $key)->first();
        if (! $row) {
            return $default;
        }

        return $row['value_json'] ?? $default;
    }

    /** Stores $value as JSON. Pass the value itself, not json_encode() output. */
    public function setValue(string $key, mixed $value, ?int $userId = null): void
    {
        $existing = $this->where('key', $key)->first();

        if ($existing) {
            $this->update($existing['id'], [
                'value_json' => $value,
                'updated_by' => $userId,
            ]);
        } else {
            $this->insert([
                'key'        => $key,
                'value_json' => $value,
                'updated_by' => $userId,
            ]);
        }
    }
}
