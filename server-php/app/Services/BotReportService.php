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

        foreach (['files_accessed','ui_screenshots','plan','code_changes','tests_run',
                  'errors','approval_history','commit_details','pr_details',
                  'deployment_details','raw_metadata'] as $jsonField) {
            if (isset($row[$jsonField]) && is_array($row[$jsonField])) {
                $row[$jsonField] = json_encode($row[$jsonField]);
            }
        }

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
