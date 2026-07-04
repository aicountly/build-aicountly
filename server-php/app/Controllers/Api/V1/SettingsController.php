<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\SettingsModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class SettingsController extends BaseController
{
    public function index(): ResponseInterface
    {
        $rows = (new SettingsModel())->orderBy('key')->find();

        // Expose environment defaults alongside DB overrides for the UI.
        $env = [
            'BUILD_AI_PROVIDER'                  => (string) env('BUILD_AI_PROVIDER', 'mock'),
            'BUILD_BOT_MODE'                     => (string) env('BUILD_BOT_MODE', 'confirm'),
            'BUILD_BOT_DEFAULT_RISK'             => (string) env('BUILD_BOT_DEFAULT_RISK', 'medium'),
            'BUILD_GITHUB_ORG'                   => (string) env('BUILD_GITHUB_ORG', 'AICOUNTLY'),
            'BUILD_GITHUB_DEFAULT_BRANCH'        => (string) env('BUILD_GITHUB_DEFAULT_BRANCH', 'main'),
            'BUILD_GITHUB_WORKING_BRANCH_PREFIX' => (string) env('BUILD_GITHUB_WORKING_BRANCH_PREFIX', 'build-bot/'),
        ];

        return build_json_success(['settings' => $rows, 'env_defaults' => $env]);
    }

    public function update(): ResponseInterface
    {
        $data = $this->jsonInput();
        $errors = build_require_fields($data, ['key']);
        if ($errors !== []) {
            return build_json_error('Missing required fields.', $errors, 422);
        }

        $key   = (string) $data['key'];
        $value = $data['value'] ?? null;

        $model = new SettingsModel();
        $old = $model->getValue($key);
        $model->setValue($key, $value, $this->currentUserId());

        Services::auditService()->log('setting.updated', [
            'entity_type' => 'setting',
            'entity_id'   => $key,
            'old_value'   => ['value' => $old],
            'new_value'   => ['value' => $value],
        ]);

        return build_json_success(['key' => $key, 'value' => $value], 'Setting saved.');
    }
}
