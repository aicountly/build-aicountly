<?php

namespace Tests\Database;

use App\Services\AuditService;
use App\Services\ConsoleAuditOutbox;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * The central-audit outbox (build_console_syncs, migration 2026-10-14-000022): queued first,
 * delivered at once when Console takes it (body cleared), kept pending with back-off when it
 * does not, retried in one batch by console:deliver-audit, rejected kept and not retried, dead
 * after 12 attempts; AuditService queues each fanned-out action once, as build:audit_logs:<id>.
 *
 * Opt-in: BUILD_RUN_DB_TESTS=1 plus database_tests_* (see phpunit.xml.dist).
 *
 * @internal
 */
final class ConsoleAuditOutboxFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    /** @var list<array<string,mixed>> */
    private array $sent = [];

    /** @var array{status:int, body:string|null}|callable */
    private $next;

    protected function setUp(): void
    {
        if (getenv('BUILD_RUN_DB_TESTS') !== '1') {
            $this->markTestSkipped('Set BUILD_RUN_DB_TESTS=1 and database_tests_* to run the database suite.');
        }
        parent::setUp();
        $this->sent = [];
        $this->next = $this->consoleAnswers('stored');
    }

    private function outbox(): ConsoleAuditOutbox
    {
        return new ConsoleAuditOutbox(function (string $url, array $headers, string $body): array {
            $req = json_decode($body, true);
            $this->sent[] = $req;

            return is_callable($this->next) ? ($this->next)($req) : $this->next;
        }, 'https://console.test/api', 'cnnk_build', Database::connect());
    }

    private function consoleAnswers(string $status): callable
    {
        return static function (array $req) use ($status): array {
            $results = [];
            foreach ($req['events'] as $i => $e) {
                $results[] = ['index' => $i, 'external_id' => $e['external_id'], 'status' => $status, 'id' => 500 + $i];
            }

            return ['status' => 200, 'body' => json_encode(['success' => true, 'data' => ['results' => $results]])];
        };
    }

    /** @return array<string,mixed>|null */
    private function row(string $ext): ?array
    {
        return Database::connect()->query('SELECT * FROM build_console_syncs WHERE external_id = ?', [$ext])->getRowArray();
    }

    public function testDeliveredAtOnceAndQueuedOnce(): void
    {
        $this->outbox()->record('build.pr.created', ['action' => 'pr.created', 'actor_email' => 'dev@x.test'], 'build:audit_logs:1');
        $row = $this->row('build:audit_logs:1');
        $this->assertSame('delivered', $row['status']);
        $this->assertSame('audit_event', $row['kind']);
        $this->assertStringNotContainsString('dev@x.test', (string) $row['request'], 'the delivered row keeps no personal data');
        $this->assertSame(500, json_decode((string) $row['request'], true)['console_audit_id']);

        $this->outbox()->record('build.pr.created', ['action' => 'pr.created'], 'build:audit_logs:1');
        $this->assertCount(1, $this->sent, 'the same external_id is not queued or sent twice');
    }

    public function testFailuresAreRetriedRejectedOrDead(): void
    {
        $this->next = ['status' => 404, 'body' => '{"success":false,"message":"Route not found."}'];
        $this->outbox()->record('build.auth.login', ['action' => 'auth.login', 'actor_email' => 'a@x.test'], 'build:audit_logs:2');
        $this->next = ['status' => 0, 'body' => null];
        $this->outbox()->record('build.deployment.requested', ['action' => 'deployment.requested'], 'build:audit_logs:3');

        $r2 = $this->row('build:audit_logs:2');
        $this->assertSame(['pending', 1], [$r2['status'], (int) $r2['retry_count']]);
        $this->assertStringContainsString('Route not found', (string) $r2['error']);
        $this->assertGreaterThan(time() + 60, strtotime((string) $r2['next_attempt_at']), 'backed off');

        // Due now: one batch, both delivered (duplicate counts as delivered).
        Database::connect()->query("UPDATE build_console_syncs SET next_attempt_at = NOW() - INTERVAL '1 second' WHERE status = 'pending'");
        $this->sent = [];
        $this->next = $this->consoleAnswers('duplicate');
        $res = $this->outbox()->deliverDue();
        $this->assertCount(1, $this->sent);
        $this->assertCount(2, $this->sent[0]['events']);
        $this->assertSame('auth.login', $this->sent[0]['events'][0]['action']);
        $this->assertSame('a@x.test', $this->sent[0]['events'][0]['actor_email']);
        $this->assertSame(2, $res['delivered']);
        $this->assertSame('delivered', $this->row('build:audit_logs:3')['status']);

        // Rejected: kept, not retried.
        $this->next = static fn (array $req): array => ['status' => 200, 'body' => json_encode(['success' => true, 'data' => ['results' => [
            ['index' => 0, 'status' => 'rejected', 'error' => 'action_required'],
        ]]])];
        $this->outbox()->record('build.approval.x', ['action' => 'approval.x'], 'build:audit_logs:4');
        $this->assertSame('rejected', $this->row('build:audit_logs:4')['status']);
        $this->assertStringContainsString('action_required', (string) $this->row('build:audit_logs:4')['error']);

        // The 12th failure: dead.
        $this->next = ['status' => 503, 'body' => null];
        $this->outbox()->record('build.bot.x', ['action' => 'bot.x'], 'build:audit_logs:5');
        Database::connect()->query("UPDATE build_console_syncs SET retry_count = 11, next_attempt_at = NOW() - INTERVAL '1 second' WHERE external_id = 'build:audit_logs:5'");
        $this->outbox()->deliverDue();
        $this->assertSame(['dead', 12], [$this->row('build:audit_logs:5')['status'], (int) $this->row('build:audit_logs:5')['retry_count']]);
    }

    public function testAuditServiceQueuesFannedOutActionsByAuditLogId(): void
    {
        $this->setEnv('CONSOLE_API_URL', 'http://127.0.0.1:9/api');   // nothing listens: the event stays queued
        $this->setEnv('CONSOLE_API_TOKEN', 'cnnk_build');

        (new AuditService())->log('pr.created', ['actor_email' => 'dev@x.test', 'entity_type' => 'pull_request', 'entity_id' => 9, 'new_value' => ['n' => 1]]);
        (new AuditService())->log('repo.synced', ['entity_type' => 'repo', 'entity_id' => 1]);  // not fanned out

        $auditId = (int) Database::connect()->query("SELECT id FROM build_audit_logs WHERE action = 'pr.created'")->getRow()->id;
        $row = $this->row('build:audit_logs:' . $auditId);
        $this->assertNotNull($row);
        $this->assertSame('pending', $row['status']);
        $event = json_decode((string) $row['request'], true)['payload']['events'][0];
        $this->assertSame(['pr.created', 'build', 'dev@x.test', ['n' => 1]], [$event['action'], $event['app_code'], $event['actor_email'], $event['new_value']]);
        $this->assertSame(1, (int) Database::connect()->query("SELECT COUNT(*) AS n FROM build_console_syncs WHERE kind = 'audit_event'")->getRow()->n);
    }

    public function testApprovalsAndDeploymentsReachConsoleOnlyThroughTheCentralAudit(): void
    {
        $this->setEnv('CONSOLE_API_URL', 'http://127.0.0.1:9/api');   // nothing listens: events stay queued
        $this->setEnv('CONSOLE_API_TOKEN', 'cnnk_build');

        $approvalId = \Config\Services::approvalService()->request(41, 'prod_deploy', null, ['summary' => 'ship it']);
        \Config\Services::approvalService()->approve($approvalId, 1, 'zz-test');
        $deploymentId = \Config\Services::deploymentRequestService()->requestStaging(41, 7, null);
        \Config\Services::deploymentRequestService()->markApproved($deploymentId, 1);

        $kinds = array_column(Database::connect()->query('SELECT DISTINCT kind FROM build_console_syncs')->getResultArray(), 'kind');
        $this->assertSame(['audit_event'], $kinds, 'no approval_request / deployment_* calls to routes Console does not have');

        $events = [];
        foreach (Database::connect()->query("SELECT request FROM build_console_syncs WHERE kind = 'audit_event' ORDER BY id")->getResultArray() as $r) {
            $e = json_decode((string) $r['request'], true)['payload']['events'][0];
            $events[$e['action']] = $e;
        }
        $this->assertSame(['approval.requested', 'approval.approved', 'deployment.requested.staging', 'deployment.approved.staging'], array_keys($events));
        $this->assertSame(['prod_deploy', $approvalId, ['summary' => 'ship it']],
            [$events['approval.requested']['metadata']['metadata']['action'], $events['approval.requested']['metadata']['metadata']['approval_id'],
             $events['approval.requested']['metadata']['metadata']['payload']]);
        $this->assertSame([$deploymentId, 'staging', 7],
            [$events['deployment.requested.staging']['metadata']['metadata']['deployment_id'], $events['deployment.requested.staging']['metadata']['metadata']['environment'],
             $events['deployment.requested.staging']['metadata']['metadata']['repo_id']]);
        $this->assertSame(['dev_request', '41'], [$events['approval.requested']['entity_type'], (string) $events['approval.requested']['entity_id']]);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    protected function tearDown(): void
    {
        foreach (['CONSOLE_API_URL', 'CONSOLE_API_TOKEN'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        parent::tearDown();
    }
}
