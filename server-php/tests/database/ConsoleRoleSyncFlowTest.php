<?php

namespace Tests\Database;

use App\Models\UsersModel;
use App\Services\ConsoleIdentityService;
use App\Services\ConsoleRoleSyncService;
use App\Services\ConsoleUserAccessClient;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * End-to-end over the CI4 router: Console SSO sign-in, token refresh against
 * Console's user-access endpoint (HTTP stubbed), JwtFilter session cut-off,
 * and the service-to-service inbound paths that must keep working without a
 * staff role.
 *
 * Opt-in: BUILD_RUN_DB_TESTS=1 plus database_tests_* pointing at a throwaway
 * PostgreSQL database (it is migrated and wiped). See phpunit.xml.dist.
 *
 * @internal
 */
final class ConsoleRoleSyncFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    /** What the stubbed Console SSO exchange returns. */
    private ?array $identity = null;

    /** What the stubbed user-access endpoint returns: [status, body]. */
    private array $userAccess = [200, '{"success":true,"data":{}}'];

    private int $now;

    private bool $userAccessConfigured = true;

    protected function setUp(): void
    {
        if (getenv('BUILD_RUN_DB_TESTS') !== '1') {
            $this->markTestSkipped('Set BUILD_RUN_DB_TESTS=1 and database_tests_* to run the database suite.');
        }

        parent::setUp();

        $this->now = time();
        $this->setEnv('BUILD_FLOW_INBOUND_TOKEN', 'flow-inbound-test-token');
        $this->setEnv('BUILD_FLOW_INBOUND_HMAC_SECRET', '');
        $this->setEnv('BUILD_CONSOLE_INBOUND_TOKEN', 'console-inbound-test-token');
        $this->setEnv('BUILD_CONSOLE_API_BASE_URL', '');
        $this->setEnv('BUILD_CONSOLE_API_TOKEN', '');

        $this->installStubs();
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }

    private function installStubs(): void
    {
        $test = $this;

        $identityService = new class ($test) extends ConsoleIdentityService {
            public function __construct(private readonly ConsoleRoleSyncFlowTest $test)
            {
            }

            public function exchangeLaunchToken(string $token): ?array
            {
                return $this->test->stubIdentity();
            }

            public function introspectSession(string $consoleToken): ?array
            {
                return $this->test->stubIdentity();
            }
        };

        $client = new ConsoleUserAccessClient(
            fn (string $url, array $headers): array => ['status' => $this->userAccess[0], 'body' => $this->userAccess[1]],
            $this->userAccessConfigured ? 'https://console.test/api' : '',
            $this->userAccessConfigured ? 'svc-token' : '',
            'build',
        );

        Services::injectMock('consoleIdentity', $identityService);
        Services::injectMock('consoleUserAccess', $client);
        Services::injectMock('consoleRoleSync', new ConsoleRoleSyncService(new UsersModel(), $client, fn (): int => $this->now));
    }

    public function stubIdentity(): ?array
    {
        return $this->identity;
    }

    private function identity(array $extra = []): array
    {
        return $extra + [
            'active'            => true,
            'global_superadmin' => false,
            'user'              => ['id' => 501, 'name' => 'Asha Rao', 'email' => 'asha@aicountly.com', 'role' => 'staff'],
            'controller_grants' => [],
            'app_code'          => 'build',
        ];
    }

    private function userAccessAnswer(array $data): void
    {
        $this->userAccess = [200, json_encode(['success' => true, 'data' => $data + [
            'exists'          => true,
            'active'          => true,
            'app_access'      => true,
            'app_superadmin'  => false,
            'console_user_id' => 501,
            'email'           => 'asha@aicountly.com',
            'name'            => 'Asha Rao',
            'app_roles'       => ['super_admin'],
            'authz_version'   => 4,
        ]])];
    }

    private function ssoLogin(): TestResponse
    {
        return $this->withBodyFormat('json')->post('v1/auth/controller-sso', ['token' => 'one-time']);
    }

    private function bearer(string $token): self
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }

    private function signedInToken(): string
    {
        $this->identity = $this->identity(['console_user_id' => 501, 'app_roles' => ['super_admin'], 'app_superadmin' => false, 'authz_version' => 3]);
        $res = $this->ssoLogin();
        $res->assertStatus(200);

        return (string) json_decode($res->getJSON(), true)['data']['token'];
    }

    private function refreshWith(string $token): TestResponse
    {
        return $this->withBodyFormat('json')->post('v1/auth/refresh', ['token' => $token]);
    }

    private function json(TestResponse $res): array
    {
        return json_decode($res->getJSON(), true);
    }

    // ------------------------------------------------------------------ sign-in

    public function testAssignedRoleSignsInAndOpensTheAdminRoutes(): void
    {
        $token = $this->signedInToken();

        $row = (new UsersModel())->findByEmail('asha@aicountly.com');
        $this->assertSame('super_admin', $row['role']);
        $this->assertSame(501, (int) $row['console_user_id']);
        $this->assertSame('3', $row['authz_version']);
        $this->assertNotEmpty($row['roles_synced_at']);
        $this->assertSame(['super_admin'], json_decode($row['console_app_roles'], true));

        $this->bearer($token)->get('v1/flow-handoffs')->assertStatus(200);

        $me = $this->json($this->bearer($token)->get('v1/me'));
        $this->assertSame(['super_admin'], $me['data']['roles']);
    }

    public function testConsoleUserWithoutBuildRoleIsRefusedAndNotProvisioned(): void
    {
        $this->identity = $this->identity(['app_roles' => [], 'app_superadmin' => false]);

        $res = $this->ssoLogin();
        $res->assertStatus(403);
        $this->assertSame('Access not assigned. Ask a Console superadmin to assign you a role.', $this->json($res)['message']);
        $this->assertNull((new UsersModel())->findByEmail('asha@aicountly.com'));
    }

    public function testOldConsolePayloadAdmitsOnlyGlobalSuperadmin(): void
    {
        $this->identity = $this->identity(); // no app_roles key at all
        $this->ssoLogin()->assertStatus(403);
        $this->assertNull((new UsersModel())->findByEmail('asha@aicountly.com'));

        $this->identity = $this->identity(['global_superadmin' => true]);
        $this->ssoLogin()->assertStatus(200);
        $this->assertSame('super_admin', (new UsersModel())->findByEmail('asha@aicountly.com')['role']);
    }

    public function testSignInWithoutRoleEndsAnExistingUsersSessions(): void
    {
        $token = $this->signedInToken();
        $this->now += 5;

        $this->identity = $this->identity(['console_user_id' => 501, 'app_roles' => []]);
        $this->ssoLogin()->assertStatus(403);

        $row = (new UsersModel())->findByEmail('asha@aicountly.com');
        $this->assertNull($row['role']);
        $this->bearer($token)->get('v1/me')->assertStatus(401);
    }

    public function testRowFromBeforeConsoleRolesCannotUseItsToken(): void
    {
        $users = new UsersModel();
        $id    = $users->insert([
            'email'         => 'legacy@aicountly.com',
            'name'          => 'Legacy',
            'password_hash' => 'x',
            'status'        => 'active',
            'role'          => 'super_admin', // the old blanket grant
        ]);
        $token = Services::jwt()->issue((int) $id, 'legacy@aicountly.com', ['super_admin']);

        $this->bearer($token)->get('v1/flow-handoffs')->assertStatus(401);

        $this->userAccess = [502, 'Bad gateway'];
        $this->refreshWith($token)->assertStatus(503); // never synced: no offline grace
    }

    // ------------------------------------------------------------------ refresh

    public function testRefreshResyncsRolesFromConsole(): void
    {
        $token = $this->signedInToken();
        $this->userAccessAnswer(['authz_version' => 9]);

        $res = $this->refreshWith($token);
        $res->assertStatus(200);
        $this->assertSame(['super_admin'], $this->json($res)['data']['user']['roles']);
        $this->assertSame('9', (new UsersModel())->findByEmail('asha@aicountly.com')['authz_version']);
    }

    public function testRefreshAfterRevocationIsRefusedAndEndsSessions(): void
    {
        $token = $this->signedInToken();
        $this->now += 5;
        $this->userAccessAnswer(['app_access' => false, 'app_roles' => []]);

        $res = $this->refreshWith($token);
        $res->assertStatus(403);
        $this->assertSame('Access not assigned. Ask a Console superadmin to assign you a role.', $this->json($res)['message']);

        $row = (new UsersModel())->findByEmail('asha@aicountly.com');
        $this->assertNull($row['role']);
        $this->assertNotEmpty($row['sessions_revoked_at']);

        $this->bearer($token)->get('v1/me')->assertStatus(401);
        $this->bearer($token)->get('v1/flow-handoffs')->assertStatus(401);
        $this->refreshWith($token)->assertStatus(401);
    }

    public function testRefreshWhileConsoleIsDownUsesTheTwelveHourGrace(): void
    {
        $token = $this->signedInToken();
        $this->userAccess = [502, 'Bad gateway'];

        $this->refreshWith($token)->assertStatus(200);

        $this->now += 12 * 3600 + 1;
        $res = $this->refreshWith($token);
        $res->assertStatus(503);
        $this->assertSame('super_admin', (new UsersModel())->findByEmail('asha@aicountly.com')['role'], 'an outage is not a revocation');
    }

    public function testRefreshWithoutUserAccessConfiguredKeepsTheSyncedRole(): void
    {
        $token = $this->signedInToken();

        $this->userAccessConfigured = false;
        $this->installStubs();

        $res = $this->refreshWith($token);
        $res->assertStatus(200);
        $this->assertSame(['super_admin'], $this->json($res)['data']['user']['roles']);
    }

    // ------------------------------------------------------- service-to-service

    public function testFlowHandoffAndConsoleCallbackStillAuthenticateWithoutStaffRoles(): void
    {
        // The inbound filters, not JwtFilter / role:super_admin, guard these.
        // Reaching the controller's own validation (422 / 404) proves the
        // service token alone is enough.
        $this->withHeaders(['Authorization' => 'Bearer wrong'])
            ->withBodyFormat('json')
            ->post('v1/internal/flow/build-task', ['flow_handoff_id' => 1])
            ->assertStatus(401);

        foreach (['v1/tasks', 'v1/internal/flow/build-task'] as $path) {
            $res = $this->withHeaders(['Authorization' => 'Bearer flow-inbound-test-token'])
                ->withBodyFormat('json')
                ->post($path, ['title' => 'no id']);
            $res->assertStatus(422);
            $this->assertSame('flow_handoff_id is required.', $this->json($res)['message']);
        }

        $this->withHeaders(['Authorization' => 'Bearer console-inbound-test-token'])
            ->withBodyFormat('json')
            ->post('v1/internal/console/approvals/callback', ['approval_id' => 999999, 'decision' => 'approved'])
            ->assertStatus(404);
    }
}
