<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ApprovalsModel;
use App\Models\BotReportsModel;
use App\Models\DevRequestsModel;
use App\Models\ReposModel;
use App\Models\SettingsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class DashboardController extends BaseController
{
    public function summary(): ResponseInterface
    {
        $dev = new DevRequestsModel();
        $rep = new ReposModel();
        $apr = new ApprovalsModel();
        $bot = new BotReportsModel();

        $statusCounts = [];
        foreach (DevRequestsModel::STATUSES as $s) {
            $statusCounts[$s] = (int) $dev->where('status', $s)->countAllResults(false);
        }

        $recent = $dev->orderBy('id', 'DESC')->limit(10)->find();

        return build_json_success([
            'dev_request_status_counts' => $statusCounts,
            'total_dev_requests'        => array_sum($statusCounts),
            'enabled_repos'             => (int) $rep->where('enabled', true)->countAllResults(),
            'pending_approvals'         => (int) $apr->where('status', 'pending')->countAllResults(),
            'bot_reports_last_7d'       => (int) $bot->where("created_at >= NOW() - INTERVAL '7 days'")->countAllResults(),
            'recent_requests'           => $recent,
        ]);
    }

    public function botMode(): ResponseInterface
    {
        $settings = new SettingsModel();
        return build_json_success([
            'bot_mode'     => $settings->getValue('bot_mode', 'confirm'),
            'default_risk' => $settings->getValue('default_risk', 'medium'),
        ]);
    }

    public function setBotMode(): ResponseInterface
    {
        $data = $this->jsonInput();
        $mode = strtolower((string) ($data['bot_mode'] ?? ''));
        if (! in_array($mode, ['auto', 'confirm'], true)) {
            return build_json_error('bot_mode must be "auto" or "confirm".', ['bot_mode' => 'invalid'], 422);
        }

        $settings = new SettingsModel();
        $previous = $settings->getValue('bot_mode', 'confirm');
        $settings->setValue('bot_mode', $mode, $this->currentUserId());

        Services::auditService()->log('bot.mode_changed', [
            'old_value' => ['bot_mode' => $previous],
            'new_value' => ['bot_mode' => $mode],
            'risk_level' => 'medium',
        ]);

        try {
            Services::consoleClient()->sendBotModeStatus(['bot_mode' => $mode, 'source' => BUILD_PORTAL_SOURCE]);
        } catch (\Throwable $e) {
            log_message('warning', 'Console bot-mode notify failed: ' . $e->getMessage());
        }

        return build_json_success(['bot_mode' => $mode]);
    }
}
