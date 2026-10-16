<?php

namespace App\Services;

use App\Models\BotReportsModel;

/**
 * Every bot action must produce a report entry (Part L). Reports are
 * append-only; the latest report per dev_request is what the UI shows.
 */
class BotReportService
{
    private BotReportsModel $model;

    public function __construct()
    {
        $this->model = new BotReportsModel();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function record(int $devRequestId, array $data): int
    {
        $row = array_merge([
            'dev_request_id' => $devRequestId,
            'bot_name'       => 'build.bot',
        ], $data);

        // JSON fields (plan, tests_run, raw_metadata, ...) go in as arrays;
        // BotReportsModel's casts encode them.
        return (int) $this->model->insert($row, true);
    }

    public function forRequest(int $devRequestId): array
    {
        return $this->model->where('dev_request_id', $devRequestId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function latestForRequest(int $devRequestId): ?array
    {
        return $this->model->where('dev_request_id', $devRequestId)
            ->orderBy('id', 'DESC')
            ->first();
    }
}
