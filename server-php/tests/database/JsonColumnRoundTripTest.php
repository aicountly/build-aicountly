<?php

namespace Tests\Database;

use App\Models\CodeTasksModel;
use App\Models\CommitsModel;
use App\Models\DevRequestsModel;
use App\Models\ReposModel;
use App\Services\Ai\MockProvider;
use App\Services\Github\LiveGitHubService;
use App\Services\JsonColumnRepairService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\StreamFilterTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * JSONB fields round-trip through the real router: what a writer stores is
 * what a reader gets back, as arrays, never a JSON string inside JSON.
 *
 * Regression for the double-encoding bug: writers json_encode()d values into
 * fields that already carry a JSON cast, so a valid Flow handoff
 * (POST v1/tasks) and every later read of those rows returned HTTP 500.
 * Also covers rows an older release already stored double-encoded (tolerant
 * reads + `php spark build:repair-json`).
 *
 * Opt-in: BUILD_RUN_DB_TESTS=1 plus database_tests_* pointing at a throwaway
 * PostgreSQL database (it is migrated and wiped). See phpunit.xml.dist.
 *
 * @internal
 */
final class JsonColumnRoundTripTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use StreamFilterTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private const FLOW_TOKEN = 'flow-inbound-test-token';

    private string $adminToken;

    /** GitHub calls the stubbed transport received: [method, url, body]. */
    private array $githubCalls = [];

    protected function setUp(): void
    {
        if (getenv('BUILD_RUN_DB_TESTS') !== '1') {
            $this->markTestSkipped('Set BUILD_RUN_DB_TESTS=1 and database_tests_* to run the database suite.');
        }

        parent::setUp();

        $this->setEnv('BUILD_FLOW_INBOUND_TOKEN', self::FLOW_TOKEN);
        $this->setEnv('BUILD_FLOW_INBOUND_HMAC_SECRET', '');
        $this->setEnv('BUILD_CONSOLE_INBOUND_TOKEN', 'console-inbound-test-token');
        // Console outbound unconfigured: the central-audit outbox records each event in
        // build_console_syncs (skipped_not_configured) instead of calling out.
        $this->setEnv('BUILD_CONSOLE_API_BASE_URL', '');
        $this->setEnv('BUILD_CONSOLE_API_TOKEN', '');
        $this->setEnv('BUILD_GITHUB_TOKEN', 'ghp-test-token');
        $this->setEnv('BUILD_GITHUB_API_BASE_URL', 'https://github.test');

        $this->adminToken = $this->superAdminToken();
    }

    /** @var array<string, string|false> env values to restore after each test */
    private array $envBefore = [];

    protected function tearDown(): void
    {
        foreach ($this->envBefore as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
        Services::reset(true);
        parent::tearDown();
    }

    private function setEnv(string $key, string $value): void
    {
        if (! array_key_exists($key, $this->envBefore)) {
            $this->envBefore[$key] = getenv($key);
        }
        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }

    /** A Build user whose super_admin role was synced from Console. */
    private function superAdminToken(): string
    {
        $this->db->table('build_users')->insert([
            'email'           => 'asha@aicountly.com',
            'name'            => 'Asha Rao',
            'password_hash'   => 'x',
            'status'          => 'active',
            'role'            => 'super_admin',
            'roles_synced_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();

        return Services::jwt()->issue($id, 'asha@aicountly.com', ['super_admin']);
    }

    private function admin(): self
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken]);
    }

    /**
     * assertSame, ignoring the order of object keys: JSONB stores keys in its
     * own order (shorter first), so a document comes back equal, not
     * byte-identical. List order and value types still count.
     */
    private function assertSameDoc(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertSame(self::sortKeys($expected), self::sortKeys($actual), $message);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::sortKeys(...), $value);
    }

    /** @return array<string, mixed> */
    private function json(TestResponse $res): array
    {
        return json_decode($res->getJSON(), true);
    }

    /** @return array<string, mixed> response `data` of a 200 GET */
    private function getData(string $path): mixed
    {
        $res = $this->admin()->get($path);
        $res->assertStatus(200);

        return $this->json($res)['data'];
    }

    private function postFlow(array $payload): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . self::FLOW_TOKEN,
            'X-Source'      => 'flow.aicountly.org',
        ])->withBodyFormat('json')->post('v1/tasks', $payload);
    }

    /** Stored JSONB type of one column, straight from PostgreSQL. */
    private function jsonbType(string $table, string $column, int $id): string
    {
        return (string) $this->db->query("SELECT jsonb_typeof({$column}) AS t FROM {$table} WHERE id = ?", [$id])->getRow()->t;
    }

    private function devRequest(string $status = 'received'): int
    {
        return (int) (new DevRequestsModel())->insert([
            'source_portal'    => 'manual',
            'requirement_text' => 'Fix the GST rounding on invoice totals',
            'request_type'     => 'bug',
            'priority'         => 'high',
            'risk_level'       => 'medium',
            'status'           => $status,
        ], true);
    }

    // ------------------------------------------------------------ Flow → Build

    /**
     * Payload exactly as Flow's FlowBotService (action create_build_task) builds
     * it and BuildApiClient::createTask() POSTs it to {BUILD_API_BASE_URL}/tasks.
     */
    public function testFlowBotHandoffIsAcceptedAndReadsBackAsArrays(): void
    {
        $payload = [
            'source_type'     => 'bug',
            'source_id'       => 314,
            'title'           => 'Invoice PDF total is off by ₹0.01',
            'description'     => "Steps:\n1. Create invoice with 3 lines at 18% GST\n2. Download PDF\nTotal shows 1,180.01 — expected 1,180.00. See https://books.aicountly.com/invoices/77",
            'priority'        => 'high',
            'product'         => 'books',
            'requested_by'    => 'Priya Nair',
            'flow_handoff_id' => 57,
        ];

        $res = $this->postFlow($payload);
        $res->assertStatus(201);
        $body = $this->json($res);
        $this->assertTrue($body['success']);
        $this->assertTrue($body['data']['created']);
        $this->assertSameDoc(57, $body['data']['flow_handoff_id']);
        $devRequestId = $body['data']['build_task_ref'];
        $this->assertGreaterThan(0, $devRequestId);
        $this->assertSameDoc($devRequestId, $body['data']['dev_request_id']);
        $this->assertSameDoc('received', $body['data']['status']);

        // Stored as JSON objects, not as strings wrapping JSON.
        $this->assertSameDoc('object', $this->jsonbType('build_dev_requests', 'metadata', $devRequestId));
        $handoffRowId = (int) $this->db->table('build_flow_handoffs')->where('flow_handoff_id', 57)->get()->getRow()->id;
        $this->assertSameDoc('object', $this->jsonbType('build_flow_handoffs', 'raw_payload', $handoffRowId));
        $this->assertSameDoc('57', $this->db->query("SELECT metadata->>'flow_handoff_id' AS v FROM build_dev_requests WHERE id = ?", [$devRequestId])->getRow()->v);

        // The created task, as the Build UI reads it.
        $task = $this->getData("v1/dev-requests/{$devRequestId}");
        $this->assertSameDoc('flow', $task['source_portal']);
        $this->assertSameDoc('314', $task['source_reference_id']);
        $this->assertSameDoc('bug', $task['request_type']);
        $this->assertSameDoc('high', $task['priority']);
        $this->assertSameDoc('books', $task['product']);
        $this->assertSameDoc("Invoice PDF total is off by ₹0.01\n\n" . $payload['description'], $task['requirement_text']);
        $this->assertSameDoc([
            'flow_handoff_id' => 57,
            'source'          => 'flow.aicountly.org',
            'requested_by'    => 'Priya Nair',
            'raw'             => $payload,
        ], $task['metadata']);
        $this->assertNull($task['files_likely_affected']);

        $handoffs = $this->getData('v1/flow-handoffs');
        $this->assertCount(1, $handoffs);
        $this->assertSameDoc($payload, $handoffs[0]['raw_payload']);
        $this->assertSameDoc($devRequestId, (int) $handoffs[0]['dev_request_id']);
        $this->assertSameDoc($payload, $this->getData("v1/flow-handoffs/{$handoffRowId}")['raw_payload']);

        // Flow retries (BuildHandoffController::retry): same id, no duplicate, 200.
        $again = $this->postFlow($payload);
        $again->assertStatus(200);
        $this->assertFalse($this->json($again)['data']['created']);
        $this->assertSameDoc($devRequestId, $this->json($again)['data']['build_task_ref']);
        $this->assertSameDoc(1, $this->db->table('build_dev_requests')->countAllResults());

        // The audit entry written for the handoff reads back too.
        $audit = array_values(array_filter($this->getData('v1/audit-logs'), static fn ($r) => $r['action'] === 'dev_request.created_from_flow'));
        $this->assertSameDoc(['flow_handoff_id' => 57, 'source_type' => 'bug', 'source_id' => 314], $audit[0]['metadata']);
    }

    /**
     * Shape sent by Flow's Desk → Build Handoff screen (BuildHandoffController):
     * the operator's stored payload plus flow_handoff_id/source_type/source_id,
     * with nested values and a Flow priority Build maps to "normal".
     */
    public function testDeskHandoffWithNestedPayloadRoundTrips(): void
    {
        $payload = [
            'title'           => 'Feature: bulk-approve purchase bills',
            'description'     => 'Approvers want to select many bills at once.',
            'priority'        => 'medium',
            'product'         => 'purchases',
            'acceptance'      => ['select all on page', 'confirm dialog shows count', 'audit entry per bill'],
            'links'           => ['flow' => 'https://flow.aicountly.org/desk/features/88', 'figma' => null],
            'flow_handoff_id' => 58,
            'source_type'     => 'feature',
            'source_id'       => 88,
        ];

        $this->withHeaders(['Authorization' => 'Bearer ' . self::FLOW_TOKEN])
            ->withBodyFormat('json')
            ->post('v1/internal/flow/build-task', $payload)
            ->assertStatus(201);

        $task = $this->getData('v1/dev-requests')[0];
        $this->assertSameDoc('feature', $task['request_type']);
        $this->assertSameDoc('normal', $task['priority']);
        $this->assertSameDoc($payload, $task['metadata']['raw']);
        $this->assertSameDoc($payload, $this->getData('v1/flow-handoffs')[0]['raw_payload']);
    }

    // ------------------------------------------------------------- approvals

    public function testApprovalPayloadRoundTripsThroughRequestApproveAndConsoleCallback(): void
    {
        $devRequestId = $this->devRequest('pending_approval');
        $approvalPayload = ['plan_id' => 12, 'files' => ['app/Services/InvoiceTotals.php'], 'note' => 'Rounding fix — "half-up"'];

        $res = $this->admin()->withBodyFormat('json')->post('v1/approvals', [
            'entity_id' => $devRequestId,
            'action'    => 'code',
            'payload'   => $approvalPayload,
        ]);
        $res->assertStatus(201);
        $approvalId = $this->json($res)['data']['approval_id'];
        $this->assertSameDoc('object', $this->jsonbType('build_approvals', 'payload', $approvalId));

        $listed = $this->getData('v1/approvals');
        $this->assertSameDoc($approvalPayload, $listed[0]['payload']);

        // Console was not configured: the approval.requested central-audit event (the only way an
        // approval reaches Console) is logged with the payload intact.
        $sync = $this->getData('v1/console-syncs')[0];
        $this->assertSameDoc('skipped_not_configured', $sync['status']);
        $this->assertSameDoc('audit_event', $sync['kind']);
        $this->assertSameDoc('/portal/audit', $sync['request']['path']);
        $event = $sync['request']['payload']['events'][0];
        $this->assertSameDoc('approval.requested', $event['action']);
        $this->assertSameDoc($approvalPayload, $event['metadata']['metadata']['payload']);

        // Console calls back with the decision.
        $this->withHeaders(['Authorization' => 'Bearer console-inbound-test-token'])
            ->withBodyFormat('json')
            ->post('v1/internal/console/approvals/callback', [
                'approval_id'      => $approvalId,
                'console_ref'      => 'CON-APR-901',
                'decision'         => 'approved',
                'reason'           => 'Looks right',
                'decided_by_email' => 'ops@aicountly.com',
            ])->assertStatus(200);

        $inbound = $this->getData('v1/console-syncs?direction=inbound')[0];
        $this->assertSameDoc($approvalId, $inbound['request']['approval_id']);
        $this->assertSameDoc(['ok' => true], $inbound['response']);

        $approval = $this->getData('v1/approvals')[0];
        $this->assertSameDoc('approved', $approval['status']);
        $this->assertSameDoc($approvalPayload, $approval['payload']);
    }

    public function testApprovalDecidedInBuildReturnsThePayload(): void
    {
        $devRequestId = $this->devRequest('pending_approval');
        $approvalId   = Services::approvalService()->request($devRequestId, 'commit', null, ['branch' => 'build-bot/gst-rounding']);

        $res = $this->admin()->withBodyFormat('json')->post("v1/approvals/{$approvalId}/approve", ['reason' => 'ok']);
        $res->assertStatus(200);
        $this->assertSameDoc(['branch' => 'build-bot/gst-rounding'], $this->json($res)['data']['payload']);
    }

    // ------------------------------------------------------------ test runner

    public function testTestRunRecordsSuitesAndTheTimelineReadsBack(): void
    {
        $devRequestId = $this->devRequest('coding');

        $res = $this->admin()->withBodyFormat('json')->post('v1/tests/run', [
            'dev_request_id' => $devRequestId,
            'suites'         => ['unit', 'database'],
        ]);
        $res->assertStatus(202);
        $codeTaskId = $this->json($res)['data']['code_task_id'];
        $this->assertSameDoc('object', $this->jsonbType('build_code_tasks', 'payload', $codeTaskId));

        $latest = $this->getData("v1/tests/latest/{$devRequestId}");
        $this->assertSameDoc($codeTaskId, (int) $latest['id']);
        $this->assertSameDoc(['suites' => ['unit', 'database']], $latest['payload']);
        $this->assertNull($latest['result']);

        $this->assertSameDoc(['suites' => ['unit', 'database']], $this->getData("v1/code-tasks/{$codeTaskId}")['payload']);

        $timeline = $this->getData("v1/dev-requests/{$devRequestId}/timeline");
        $this->assertSameDoc('tests_running', $timeline[0]['to_status']);
        $this->assertSameDoc(['code_task_id' => $codeTaskId], $timeline[0]['payload']);

        $audit = array_values(array_filter($this->getData('v1/audit-logs'), static fn ($r) => $r['action'] === 'dev_request.status_changed'));
        $this->assertSameDoc(['status' => 'coding'], $audit[0]['old_value']);
        $this->assertSameDoc(['status' => 'tests_running'], $audit[0]['new_value']);
        $this->assertSameDoc(['code_task_id' => $codeTaskId], $audit[0]['metadata']);
    }

    // ----------------------------------------------------------------- GitHub

    /** LiveGitHubService over a stubbed transport (no network). */
    private function stubGitHub(): void
    {
        $responses = [
            'GET /repos/aicountly/books-react-app/git/refs/heads/build-bot%2Fgst-rounding' => ['object' => ['sha' => 'tip111']],
            'GET /repos/aicountly/books-react-app/git/commits/tip111'                     => ['sha' => 'tip111', 'tree' => ['sha' => 'tree000']],
            'POST /repos/aicountly/books-react-app/git/trees'                             => ['sha' => 'tree222'],
            'POST /repos/aicountly/books-react-app/git/commits'                           => ['sha' => 'c0ffee42', 'html_url' => 'https://github.test/c/c0ffee42'],
            'PATCH /repos/aicountly/books-react-app/git/refs/heads/build-bot%2Fgst-rounding' => ['ref' => 'refs/heads/build-bot/gst-rounding'],
        ];

        $transport = function (string $method, string $url, array $headers, ?string $body) use ($responses): array {
            $this->githubCalls[] = [$method, $url, $body];
            $key = $method . ' ' . substr($url, strlen('https://github.test'));

            return isset($responses[$key])
                ? ['status' => 200, 'body' => json_encode($responses[$key]), 'error' => '']
                : ['status' => 404, 'body' => '{"message":"Not Found"}', 'error' => ''];
        };

        Services::injectMock('github', new LiveGitHubService($transport));
    }

    public function testCommitExecutionLogsGithubActivityThatReadsBack(): void
    {
        $this->stubGitHub();
        $devRequestId = $this->devRequest('pending_commit_approval');

        $repoId = (int) (new ReposModel())->insert([
            'repo_code'      => 'books',
            'repo_name'      => 'Books',
            'github_org'     => 'aicountly',
            'github_repo'    => 'books-react-app',
            'default_branch' => 'main',
            'product'        => 'books',
        ], true);

        $diff = [
            ['path' => 'server-php/app/Libraries/InvoiceTotals.php', 'after' => "<?php // round half-up\n"],
            ['path' => 'docs/rounding.md', 'after' => "# Rounding\n"],
        ];
        $commitId = (int) (new CommitsModel())->insert([
            'dev_request_id' => $devRequestId,
            'repo_id'        => $repoId,
            'branch'         => 'build-bot/gst-rounding',
            'message'        => 'Round GST half-up on invoice totals',
            'diff_summary'   => $diff,
            'status'         => 'approved',
        ], true);
        $this->assertSameDoc('array', $this->jsonbType('build_commits', 'diff_summary', $commitId));

        $res = $this->admin()->post("v1/commits/{$commitId}/execute");
        $res->assertStatus(200);
        $commit = $this->json($res)['data'];
        $this->assertSameDoc('committed', $commit['status']);
        $this->assertSameDoc('c0ffee42', $commit['sha']);
        $this->assertSameDoc($diff, $commit['diff_summary']);

        // The tree GitHub received carries the file contents.
        $treeCall = array_values(array_filter($this->githubCalls, static fn ($c) => str_ends_with($c[1], '/git/trees')))[0];
        $this->assertSameDoc('docs/rounding.md', json_decode($treeCall[2], true)['tree'][1]['path']);

        $activity = $this->getData('v1/github-activity');
        $this->assertCount(1, $activity);
        $this->assertSameDoc('commit.create', $activity[0]['kind']);
        $this->assertSameDoc([
            'owner' => 'aicountly', 'repo' => 'books-react-app', 'commit' => 'c0ffee42', 'file_count' => 2,
        ], $activity[0]['payload']);
        $this->assertSameDoc('object', $this->jsonbType('build_github_activity', 'payload', (int) $activity[0]['id']));

        $this->assertSameDoc($diff, $this->getData('v1/commits')[0]['diff_summary']);
    }

    // ------------------------------------------------- bot plan + Playwright

    public function testBotPlanStoresPlanAndReportAsArrays(): void
    {
        Services::injectMock('aiProvider', new MockProvider());
        $devRequestId = $this->devRequest('analyzing');

        $res = $this->admin()->withBodyFormat('json')->post('v1/bot/plan', ['dev_request_id' => $devRequestId]);
        $res->assertStatus(200);
        $plan   = $this->json($res)['data'];
        $planId = (int) $plan['plan_id'];

        $this->assertSame('array', $this->jsonbType('build_bot_plans', 'plan', $planId));
        $stored = $this->db->query('SELECT plan::text AS p FROM build_bot_plans WHERE id = ?', [$planId])->getRow()->p;
        $this->assertSameDoc($plan['steps'], json_decode($stored, true));

        $report = $this->getData('v1/bot-reports')[0];
        $this->assertSameDoc($plan['steps'], $report['plan']);
        $this->assertSameDoc(['stage' => 'plan', 'plan_id' => $planId], $report['raw_metadata']);
        $this->assertSameDoc($report, $this->getData('v1/bot-reports/' . $report['id']));
    }

    public function testPlaywrightJobAndWorkerResultRoundTrip(): void
    {
        // Worker token set, worker URL not: the job is recorded as disabled
        // without an outbound call, and the worker callback still authenticates.
        $this->setEnv('BUILD_WORKER_API_TOKEN', 'worker-test-token');
        $this->setEnv('BUILD_WORKER_BASE_URL', '');
        $devRequestId = $this->devRequest('coding');

        $res = $this->admin()->withBodyFormat('json')->post('v1/playwright/jobs', [
            'kind'           => 'after_screenshot',
            'target_url'     => 'https://books.aicountly.com/invoices/77',
            'dev_request_id' => $devRequestId,
            'extra'          => ['viewport' => ['width' => 1280, 'height' => 800]],
        ]);
        $res->assertStatus(202);
        $jobId = $this->json($res)['data']['job_id'];

        $result = [
            'status'      => 'completed',
            'screenshots' => [[
                'phase' => 'after', 'url' => 'https://worker.test/s/1.png', 'width' => 1280, 'height' => 800,
                'meta'  => ['console_errors' => [], 'title' => 'Invoice #77'],
            ]],
        ];
        $this->withHeaders(['X-Worker-Token' => 'worker-test-token'])
            ->withBodyFormat('json')
            ->post("v1/internal/worker/results/{$jobId}", $result)
            ->assertStatus(200);

        $shown = $this->getData("v1/playwright/jobs/{$jobId}");
        $this->assertSame('completed', $shown['job']['status']);
        $this->assertSameDoc(['width' => 1280, 'height' => 800], $shown['job']['request']['viewport']);
        $this->assertSameDoc($result, $shown['job']['response']);
        $this->assertSameDoc(['console_errors' => [], 'title' => 'Invoice #77'], $shown['screenshots'][0]['meta']);
    }

    // --------------------------------------------------------------- settings

    public function testSettingsAndBotModeReadAndWritePlainValues(): void
    {
        // Seeded by the settings migration as JSON strings.
        $this->assertSameDoc(['bot_mode' => 'confirm', 'default_risk' => 'medium'], $this->getData('v1/dashboard/bot-mode'));

        $this->admin()->withBodyFormat('json')->post('v1/dashboard/bot-mode', ['bot_mode' => 'auto'])->assertStatus(200);
        $this->assertSameDoc('auto', $this->getData('v1/dashboard/bot-mode')['bot_mode']);
        $this->assertSameDoc('"auto"', $this->db->query("SELECT value_json::text AS v FROM build_settings WHERE key = 'bot_mode'")->getRow()->v);

        $this->admin()->withBodyFormat('json')->put('v1/settings', ['key' => 'review_window', 'value' => ['hours' => 4, 'weekends' => false]])->assertStatus(200);
        $byKey = array_column($this->getData('v1/settings')['settings'], 'value_json', 'key');
        $this->assertSameDoc(['hours' => 4, 'weekends' => false], $byKey['review_window']);
        $this->assertSameDoc('auto', $byKey['bot_mode']);
    }

    public function testDevRequestPatchStoresArraysAndRejectsStrings(): void
    {
        $id = $this->devRequest();

        $res = $this->admin()->withBodyFormat('json')->patch("v1/dev-requests/{$id}", [
            'files_likely_affected' => ['app/Services/InvoiceTotals.php'],
            'metadata'              => ['ticket' => 'BUG-314'],
        ]);
        $res->assertStatus(200);
        $this->assertSameDoc(['app/Services/InvoiceTotals.php'], $this->json($res)['data']['files_likely_affected']);
        $this->assertSameDoc(['ticket' => 'BUG-314'], $this->getData("v1/dev-requests/{$id}")['metadata']);

        $this->admin()->withBodyFormat('json')->patch("v1/dev-requests/{$id}", ['metadata' => 'not-an-object'])->assertStatus(422);
    }

    // ------------------------------------------------- already double-encoded

    /** Rows as the old writers left them: the cast encoded a json_encode() result. */
    private function insertLegacyDoubleEncodedRows(): array
    {
        $doubled = static fn (mixed $v): string => json_encode(json_encode($v));

        $payload = ['flow_handoff_id' => 41, 'source_type' => 'bug', 'source_id' => 9, 'title' => 'Legacy “quoted” handoff'];
        $this->db->table('build_dev_requests')->insert([
            'source_portal'    => 'flow',
            'requirement_text' => 'Legacy handoff',
            'request_type'     => 'bug',
            'priority'         => 'normal',
            'risk_level'       => 'medium',
            'status'           => 'received',
            'metadata'         => $doubled(['flow_handoff_id' => 41, 'raw' => $payload]),
        ]);
        $devRequestId = (int) $this->db->insertID();

        $this->db->table('build_flow_handoffs')->insert([
            'flow_handoff_id' => 41,
            'source_type'     => 'bug',
            'source_id'       => 9,
            'source_portal'   => 'flow.aicountly.org',
            'raw_payload'     => $doubled($payload),
            'dev_request_id'  => $devRequestId,
            'status'          => 'acknowledged',
        ]);
        $this->db->table('build_approvals')->insert([
            'entity_type' => 'dev_request', 'entity_id' => $devRequestId, 'action' => 'code',
            'status'      => 'pending', 'payload' => $doubled([]),
        ]);
        $this->db->table('build_code_tasks')->insert([
            'dev_request_id' => $devRequestId, 'kind' => 'test_run', 'status' => 'pending',
            'payload'        => $doubled(['suites' => ['default']]),
        ]);
        $this->db->table('build_github_activity')->insert([
            'kind' => 'branch.create', 'ref' => 'build-bot/x', 'actor' => 'build.bot',
            'payload' => $doubled(['owner' => 'aicountly', 'repo' => 'books-react-app', 'from' => 'main']),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('build_audit_logs')->insert([
            'action' => 'setting.updated', 'risk_level' => 'info',
            'old_value' => $doubled('confirm'), 'new_value' => $doubled(['value' => 'auto']),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->query("UPDATE build_settings SET value_json = ?::jsonb WHERE key = 'bot_mode'", [$doubled('auto')]);

        return [$devRequestId, $payload];
    }

    public function testLegacyDoubleEncodedRowsReadAsArraysAndTheRepairUnwrapsThem(): void
    {
        [$devRequestId, $payload] = $this->insertLegacyDoubleEncodedRows();
        $this->assertSameDoc('string', $this->jsonbType('build_flow_handoffs', 'raw_payload', 1));

        // Tolerant reads: no 500, real arrays.
        $this->assertSameDoc($payload, $this->getData('v1/flow-handoffs')[0]['raw_payload']);
        $this->assertSameDoc($payload, $this->getData("v1/dev-requests/{$devRequestId}")['metadata']['raw']);
        $this->assertSameDoc([], $this->getData('v1/approvals')[0]['payload']);
        $this->assertSameDoc(['suites' => ['default']], $this->getData("v1/tests/latest/{$devRequestId}")['payload']);
        $this->assertSameDoc('main', $this->getData('v1/github-activity')[0]['payload']['from']);
        $this->assertSameDoc('auto', $this->getData('v1/dashboard/bot-mode')['bot_mode']);
        $audit = $this->getData('v1/audit-logs')[0];
        $this->assertSameDoc('confirm', $audit['old_value']);
        $this->assertSameDoc(['value' => 'auto'], $audit['new_value']);

        // Flow re-sending a legacy handoff hits the idempotent read path.
        $this->postFlow($payload + ['title' => 'x'])->assertStatus(200);

        // Dry run: counts, changes nothing.
        $service = new JsonColumnRepairService($this->db);
        $dry     = $this->indexRepair($service->run(false));
        $this->assertSameDoc(1, $dry['build_flow_handoffs.raw_payload']['candidates']);
        $this->assertSameDoc(1, $dry['build_dev_requests.metadata']['candidates']);
        $this->assertSameDoc(1, $dry['build_settings.value_json']['candidates']);
        $this->assertSameDoc(0, $dry['build_flow_handoffs.raw_payload']['repaired']);
        $this->assertSameDoc('string', $this->jsonbType('build_flow_handoffs', 'raw_payload', 1));

        // The spark command, applied.
        command('build:repair-json --apply');
        $this->assertStringContainsString('build_flow_handoffs.raw_payload: 1 double-encoded, 1 repaired', $this->getStreamFilterBuffer());
        $this->assertStringContainsString('Repaired 8 of 8 double-encoded JSON values', $this->getStreamFilterBuffer());
        $this->resetStreamFilterBuffer();

        $this->assertSameDoc('object', $this->jsonbType('build_flow_handoffs', 'raw_payload', 1));
        $this->assertSameDoc('object', $this->jsonbType('build_dev_requests', 'metadata', $devRequestId));
        $this->assertSameDoc('array', $this->jsonbType('build_approvals', 'payload', 1));
        $this->assertSameDoc('41', $this->db->query("SELECT raw_payload->>'flow_handoff_id' AS v FROM build_flow_handoffs")->getRow()->v);
        $this->assertSameDoc('"auto"', $this->db->query("SELECT value_json::text AS v FROM build_settings WHERE key = 'bot_mode'")->getRow()->v);
        $this->assertSameDoc('"confirm"', $this->db->query('SELECT old_value::text AS v FROM build_audit_logs')->getRow()->v);
        // Untouched: the seeded default_risk was never double-encoded.
        $this->assertSameDoc('"medium"', $this->db->query("SELECT value_json::text AS v FROM build_settings WHERE key = 'default_risk'")->getRow()->v);

        // Same answers after the repair.
        $this->assertSameDoc($payload, $this->getData('v1/flow-handoffs')[0]['raw_payload']);
        $this->assertSameDoc('auto', $this->getData('v1/dashboard/bot-mode')['bot_mode']);

        // Idempotent: nothing left to do.
        $again = $service->run(true);
        $this->assertSameDoc(0, array_sum(array_column($again, 'candidates')));
        command('build:repair-json');
        $this->assertStringContainsString('Dry run: 0 double-encoded', $this->getStreamFilterBuffer());
    }

    /** @return array<string, array<string, mixed>> */
    private function indexRepair(array $results): array
    {
        $out = [];
        foreach ($results as $r) {
            $out[$r['table'] . '.' . $r['column']] = $r;
        }

        return $out;
    }
}
