<?php

namespace App\Services;

use App\Models\ReposModel;
use RuntimeException;

class RepoRegistryService
{
    private ReposModel $model;

    public function __construct()
    {
        $this->model = new ReposModel();
    }

    /** @return list<array<string, mixed>> */
    public function list(bool $onlyEnabled = false): array
    {
        return $onlyEnabled ? $this->model->enabled() : $this->model->orderBy('repo_name')->findAll();
    }

    public function find(int $id): ?array
    {
        return $this->model->find($id) ?: null;
    }

    public function findByCode(string $code): ?array
    {
        return $this->model->findByCode($code);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $id = $this->model->insert($this->fill($data), true);
        if ($id === false) {
            throw new RuntimeException('Failed to create repo: ' . implode(', ', $this->model->errors()));
        }
        return (int) $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $ok = $this->model->update($id, $this->fill($data));
        if (! $ok) {
            throw new RuntimeException('Failed to update repo #' . $id . ': ' . implode(', ', $this->model->errors()));
        }
    }

    public function delete(int $id): void
    {
        $this->model->delete($id);
    }

    public function markSynced(int $id): void
    {
        $this->model->update($id, ['last_sync_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fill(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'repo_code', 'repo_name', 'product',
            'github_org', 'github_repo', 'local_path',
            'default_branch', 'protected_branch', 'allowed_working_branch_prefix',
            'deployment_type', 'staging_url', 'production_url',
            'enabled', 'notes',
        ]));
    }
}
