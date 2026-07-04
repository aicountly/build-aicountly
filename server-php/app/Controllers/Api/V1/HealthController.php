<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\Ai\AiProviderFactory;
use App\Services\Github\GitHubServiceFactory;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class HealthController extends BaseController
{
    public function index(): ResponseInterface
    {
        $jwtSecret = (string) env('BUILD_JWT_SECRET', '');
        $vaultKey  = (string) env('BUILD_VAULT_KEY', '');

        $db = false;
        try {
            $rows = \Config\Database::connect()->query('SELECT 1 AS one')->getResultArray();
            $db   = isset($rows[0]['one']);
        } catch (\Throwable $e) {
            $db = false;
        }

        return build_json_success([
            'service'   => 'aicountly-build-api',
            'timestamp' => gmdate('c'),
            'checks'    => [
                'jwt_secret' => strlen($jwtSecret) >= 32,
                'vault_key'  => strlen($vaultKey) === 64,
                'database'   => $db,
            ],
        ]);
    }

    public function integrations(): ResponseInterface
    {
        $github  = GitHubServiceFactory::status();
        $ai      = AiProviderFactory::status();
        $worker  = [
            'configured' => Services::playwrightWorker()->isConfigured(),
            'base_url'   => (string) env('BUILD_WORKER_BASE_URL', ''),
        ];
        $console = [
            'configured'  => Services::consoleClient()->isConfigured(),
            'base_url'    => (string) env('BUILD_CONSOLE_API_BASE_URL', ''),
            'inbound_configured' => (string) env('BUILD_CONSOLE_INBOUND_TOKEN', '') !== '',
        ];
        $flow    = [
            'inbound_configured' => (string) env('BUILD_FLOW_INBOUND_TOKEN', '') !== '',
        ];
        $deploy  = [
            'configured' => Services::deploymentRequestService()->isDeployProviderConfigured(),
            'url'        => (string) env('BUILD_DEPLOY_API_URL', ''),
        ];

        return build_json_success([
            'github'  => $github,
            'ai'      => $ai,
            'worker'  => $worker,
            'console' => $console,
            'flow'    => $flow,
            'deploy'  => $deploy,
        ]);
    }
}
