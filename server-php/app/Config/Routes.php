<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static function () {
    return service('response')->setJSON([
        'success' => true,
        'message' => 'AICOUNTLY Build API',
        'data'    => [
            'service' => 'aicountly-build-api',
            'version' => 'v1',
            'docs'    => '/api/v1',
        ],
        'errors'  => [],
    ]);
});

$routes->get('/health', static function () {
    $jwtSecret = (string) env('BUILD_JWT_SECRET', '');
    $jwtOk     = $jwtSecret !== '' && strlen($jwtSecret) >= 32;
    $vaultKey  = (string) env('BUILD_VAULT_KEY', '');
    $vaultOk   = $vaultKey !== '' && strlen($vaultKey) === 64;

    return service('response')->setJSON([
        'success' => $jwtOk && $vaultOk,
        'message' => ($jwtOk && $vaultOk) ? 'ready' : 'misconfigured',
        'data'    => [
            'service'   => 'aicountly-build-api',
            'timestamp' => gmdate('c'),
            'checks'    => [
                'jwt_secret' => $jwtOk  ? 'ok' : 'missing or too short (need 32+ chars in server-php/.env)',
                'vault_key'  => $vaultOk ? 'ok' : 'missing or wrong length (need 64 hex chars in server-php/.env)',
            ],
        ],
        'errors'  => [],
    ]);
});

$routes->group('v1', static function ($routes) {
    // ------------------------------------------------------------------
    // Public auth endpoints.
    // ------------------------------------------------------------------
    $routes->post('auth/login',           'Api\\V1\\AuthController::login');
    $routes->post('auth/refresh',         'Api\\V1\\AuthController::refresh');
    $routes->get('auth/sso-callback',     'Api\\V1\\AuthController::ssoCallback');
    $routes->post('auth/controller-sso',  'Api\\V1\\AuthController::controllerSso');
    $routes->post('auth/console-session', 'Api\\V1\\AuthController::consoleSession');

    // ------------------------------------------------------------------
    // Public health snapshots (safe, non-sensitive).
    // ------------------------------------------------------------------
    $routes->get('health',                'Api\\V1\\HealthController::index');
    $routes->get('health/integrations',   'Api\\V1\\HealthController::integrations');

    // ------------------------------------------------------------------
    // Flow -> Build handoff. Both paths route to the same controller and
    // dedupe on flow_handoff_id (Part I of spec).
    // ------------------------------------------------------------------
    $routes->group('', ['filter' => 'flow-inbound'], static function ($routes) {
        $routes->post('tasks',                    'Api\\V1\\FlowHandoffController::create');
        $routes->post('internal/flow/build-task', 'Api\\V1\\FlowHandoffController::create');
    });

    // ------------------------------------------------------------------
    // Console -> Build approval callback + audit acks (Part J of spec).
    // ------------------------------------------------------------------
    $routes->group('internal/console', ['filter' => 'console-inbound'], static function ($routes) {
        $routes->post('approvals/callback', 'Api\\V1\\ConsoleCallbackController::approval');
    });

    // ------------------------------------------------------------------
    // Playwright worker -> Build callback (job results).
    // ------------------------------------------------------------------
    $routes->group('internal/worker', ['filter' => 'worker-auth'], static function ($routes) {
        $routes->post('results/(:num)', 'Api\\V1\\WorkerResultsController::submit/$1');
    });

    // ------------------------------------------------------------------
    // Superadmin-only portal endpoints. Every route in this block is
    // guarded by jwt + role:super_admin.
    // ------------------------------------------------------------------
    $routes->group('', ['filter' => 'jwt'], static function ($routes) {
        $routes->get('me',           'Api\\V1\\AuthController::me');
        $routes->post('auth/logout', 'Api\\V1\\AuthController::logout');

        // Enforce super_admin for everything else in this block.
        $routes->group('', ['filter' => 'role:super_admin'], static function ($routes) {
            // Dashboard
            $routes->get('dashboard/summary',       'Api\\V1\\DashboardController::summary');
            $routes->get('dashboard/bot-mode',      'Api\\V1\\DashboardController::botMode');
            $routes->post('dashboard/bot-mode',     'Api\\V1\\DashboardController::setBotMode');

            // Repo Registry
            $routes->get('repos',        'Api\\V1\\ReposController::index');
            $routes->post('repos',       'Api\\V1\\ReposController::create');
            $routes->get('repos/(:num)', 'Api\\V1\\ReposController::show/$1');
            $routes->put('repos/(:num)', 'Api\\V1\\ReposController::update/$1');
            $routes->delete('repos/(:num)', 'Api\\V1\\ReposController::delete/$1');
            $routes->post('repos/(:num)/sync', 'Api\\V1\\ReposController::sync/$1');

            // Dev Requests
            $routes->get('dev-requests',              'Api\\V1\\DevRequestsController::index');
            $routes->post('dev-requests',             'Api\\V1\\DevRequestsController::create');
            $routes->get('dev-requests/(:num)',       'Api\\V1\\DevRequestsController::show/$1');
            $routes->patch('dev-requests/(:num)',     'Api\\V1\\DevRequestsController::update/$1');
            $routes->post('dev-requests/(:num)/transition', 'Api\\V1\\DevRequestsController::transition/$1');
            $routes->get('dev-requests/(:num)/timeline', 'Api\\V1\\DevRequestsController::timeline/$1');

            // Flow Handoff Queue (admin-side view of inbound handoffs)
            $routes->get('flow-handoffs',            'Api\\V1\\FlowHandoffController::index');
            $routes->get('flow-handoffs/(:num)',     'Api\\V1\\FlowHandoffController::show/$1');

            // Approvals
            $routes->get('approvals',                'Api\\V1\\ApprovalsController::index');
            $routes->post('approvals',               'Api\\V1\\ApprovalsController::create');
            $routes->post('approvals/(:num)/approve', 'Api\\V1\\ApprovalsController::approve/$1');
            $routes->post('approvals/(:num)/reject',  'Api\\V1\\ApprovalsController::reject/$1');
            $routes->post('approvals/high-risk-override', 'Api\\V1\\ApprovalsController::highRiskOverride');

            // Bot Workbench (read + plan generation only)
            $routes->post('bot/analyze',             'Api\\V1\\BotWorkbenchController::analyze');
            $routes->post('bot/plan',                'Api\\V1\\BotWorkbenchController::plan');
            $routes->post('bot/generate-code',       'Api\\V1\\BotWorkbenchController::generateCode');

            // Playwright UI Review
            $routes->get('playwright/jobs',          'Api\\V1\\PlaywrightReviewController::index');
            $routes->post('playwright/jobs',         'Api\\V1\\PlaywrightReviewController::enqueue');
            $routes->get('playwright/jobs/(:num)',   'Api\\V1\\PlaywrightReviewController::show/$1');

            // Test Runner
            $routes->post('tests/run',               'Api\\V1\\TestRunnerController::run');
            $routes->get('tests/latest/(:num)',      'Api\\V1\\TestRunnerController::latest/$1');

            // Commit Queue
            $routes->get('commits',                  'Api\\V1\\CommitsController::index');
            $routes->post('commits/(:num)/approve',  'Api\\V1\\CommitsController::approve/$1');
            $routes->post('commits/(:num)/execute',  'Api\\V1\\CommitsController::execute/$1');

            // Pull Requests
            $routes->get('pull-requests',            'Api\\V1\\PullRequestsController::index');
            $routes->post('pull-requests/(:num)/approve', 'Api\\V1\\PullRequestsController::approve/$1');
            $routes->post('pull-requests/(:num)/execute', 'Api\\V1\\PullRequestsController::execute/$1');
            $routes->get('pull-requests/(:num)',     'Api\\V1\\PullRequestsController::show/$1');

            // Deployments
            $routes->get('deployments',              'Api\\V1\\DeploymentsController::index');
            $routes->post('deployments/staging',     'Api\\V1\\DeploymentsController::requestStaging');
            $routes->post('deployments/production',  'Api\\V1\\DeploymentsController::requestProduction');
            $routes->post('deployments/(:num)/approve', 'Api\\V1\\DeploymentsController::approve/$1');
            $routes->post('deployments/(:num)/deployed', 'Api\\V1\\DeploymentsController::markDeployed/$1');

            // Bot Reports
            $routes->get('bot-reports',              'Api\\V1\\BotReportsController::index');
            $routes->get('bot-reports/(:num)',       'Api\\V1\\BotReportsController::show/$1');

            // GitHub Activity Log
            $routes->get('github-activity',          'Api\\V1\\GithubActivityController::index');

            // Console Sync log
            $routes->get('console-syncs',            'Api\\V1\\ConsoleSyncController::index');

            // Settings + Audit
            $routes->get('settings',                 'Api\\V1\\SettingsController::index');
            $routes->put('settings',                 'Api\\V1\\SettingsController::update');
            $routes->get('audit-logs',               'Api\\V1\\AuditLogsController::index');

            // Code tasks (planner)
            $routes->get('code-tasks',               'Api\\V1\\CodeTasksController::index');
            $routes->get('code-tasks/(:num)',        'Api\\V1\\CodeTasksController::show/$1');
        });
    });
});
