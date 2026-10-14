<?php

namespace Tests\Unit;

use App\Services\ConsoleUserAccessClient;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Console's user-access endpoint, with the HTTP transport stubbed.
 *
 * @internal
 */
final class ConsoleUserAccessClientTest extends CIUnitTestCase
{
    /** @var list<array{url:string, headers:list<string>}> */
    private array $calls = [];

    private function client(int $status, string|false $body, string $base = 'https://console.test/api', string $token = 'svc-token'): ConsoleUserAccessClient
    {
        return new ConsoleUserAccessClient(function (string $url, array $headers) use ($status, $body): array {
            $this->calls[] = ['url' => $url, 'headers' => $headers];

            return ['status' => $status, 'body' => $body];
        }, $base, $token, 'build');
    }

    public function testNotConfiguredWithoutTokenOrUrl(): void
    {
        $this->assertSame('not_configured', $this->client(200, '{}', 'https://console.test/api', '')->lookup(1, 'a@b.c')['status']);
        $this->assertSame('not_configured', $this->client(200, '{}', '', 'svc')->lookup(1, 'a@b.c')['status']);
        $this->assertSame([], $this->calls);
    }

    public function testLooksUpByConsoleUserIdWithServiceToken(): void
    {
        $data = ['exists' => true, 'active' => true, 'app_access' => true, 'app_roles' => ['super_admin'], 'authz_version' => 3];
        $res  = $this->client(200, json_encode(['success' => true, 'data' => $data]))->lookup(42, 'x@y.z');

        $this->assertSame('ok', $res['status']);
        $this->assertSame($data, $res['data']);
        $this->assertSame(
            'https://console.test/api/portal/controller-apps/user-access?app_code=build&console_user_id=42',
            $this->calls[0]['url'],
        );
        $this->assertContains('Authorization: Bearer svc-token', $this->calls[0]['headers']);
    }

    public function testFallsBackToEmailLookup(): void
    {
        $this->client(200, json_encode(['success' => true, 'data' => ['exists' => false]]))->lookup(null, ' Asha@Aicountly.com ');

        $this->assertSame(
            'https://console.test/api/portal/controller-apps/user-access?app_code=build&email=asha%40aicountly.com',
            $this->calls[0]['url'],
        );
    }

    public function testFailuresAreUnavailableNotRevoked(): void
    {
        $this->assertSame('unavailable', $this->client(0, false)->lookup(1, '')['status']);
        $this->assertSame('unavailable', $this->client(500, '{"success":false}')->lookup(1, '')['status']);
        $this->assertSame('unavailable', $this->client(403, '{"success":false,"message":"missing capability staff.roles"}')->lookup(1, '')['status']);
        $this->assertSame('unavailable', $this->client(200, '<html>')->lookup(1, '')['status']);
        $this->assertSame('unavailable', $this->client(200, '{"success":true,"data":null}')->lookup(1, '')['status']);
    }
}
