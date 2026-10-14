<?php

namespace Tests\Unit;

use App\Services\ConsoleAuditOutbox;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Central audit to Console: the event shape and the request (no database).
 * Build used to post to /api/v1/portal/audit, which Console never had (aicountly-smoke-app e2e).
 *
 * @internal
 */
final class ConsoleAuditOutboxTest extends CIUnitTestCase
{
    public function testMapsAuditServicePayloadToConsoleEvent(): void
    {
        $e = ConsoleAuditOutbox::toEvent('build.pr.created', [
            'action' => 'pr.created', 'entity_type' => 'pull_request', 'entity_id' => '42', 'risk_level' => 'medium',
            'actor_id' => 7, 'actor_email' => 'dev@aicountly.com', 'old_value' => null, 'new_value' => ['number' => 12],
            'ip_address' => '10.0.0.1', 'user_agent' => 'UA', 'metadata' => ['repo' => 'build'],
        ], 'build:audit_logs:5');

        $this->assertSame('pr.created', $e['action']);
        $this->assertSame('build', $e['app_code']);
        $this->assertSame('build:audit_logs:5', $e['external_id']);
        $this->assertSame(['pull_request', '42', 'dev@aicountly.com'], [$e['entity_type'], $e['entity_id'], $e['actor_email']]);
        $this->assertSame(['number' => 12], $e['new_value']);
        $this->assertSame(['10.0.0.1', 'UA'], [$e['ip'], $e['user_agent']]);
        $this->assertSame('build.pr.created', $e['metadata']['type']);
        $this->assertSame(['medium', 7, ['repo' => 'build']], [$e['metadata']['risk_level'], $e['metadata']['actor_id'], $e['metadata']['metadata']]);
        $this->assertNotFalse(strtotime($e['occurred_at']));
    }

    public function testPostsToConsolePortalAuditWithBuildsOwnKey(): void
    {
        $calls = [];
        $outbox = new ConsoleAuditOutbox(static function (string $url, array $headers, string $body) use (&$calls): array {
            $calls[] = [$url, $headers, json_decode($body, true)];

            return ['status' => 200, 'body' => json_encode(['success' => true, 'data' => ['results' => [['index' => 0, 'status' => 'stored', 'id' => 3]]]])];
        }, 'https://console.test/api/', 'cnnk_build');

        $event = ConsoleAuditOutbox::toEvent('build.auth.login', ['action' => 'auth.login'], 'build:audit_logs:1');
        $res = $outbox->send([$event]);

        $this->assertSame('https://console.test/api/portal/audit', $calls[0][0]);
        $this->assertContains('Authorization: Bearer cnnk_build', $calls[0][1]);
        $this->assertSame(['events' => [$event]], $calls[0][2]);
        $this->assertSame([200, 'stored'], [$res['status'], $res['results'][0]['status']]);
    }

    public function testAFailedAnswerHasNoResults(): void
    {
        $outbox = new ConsoleAuditOutbox(static fn (): array => ['status' => 404, 'body' => '{"success":false,"message":"Route not found."}'], 'https://c.test/api', 'k');
        $res = $outbox->send([['action' => 'x']]);
        $this->assertNull($res['results']);
        $this->assertStringContainsString('Route not found', $res['error']);

        $down = new ConsoleAuditOutbox(static fn (): array => ['status' => 0, 'body' => false, 'error' => 'Connection refused'], 'https://c.test/api', 'k');
        $this->assertSame([0, 'Connection refused'], array_values(array_intersect_key($down->send([['action' => 'x']]), ['status' => 1, 'error' => 1])));
    }

    public function testConfiguredOnlyWithUrlAndToken(): void
    {
        $this->assertFalse((new ConsoleAuditOutbox(null, '', 'k'))->isConfigured());
        $this->assertFalse((new ConsoleAuditOutbox(null, 'https://c.test/api', ''))->isConfigured());
        $this->assertTrue((new ConsoleAuditOutbox(null, 'https://c.test/api', 'k'))->isConfigured());
    }
}
