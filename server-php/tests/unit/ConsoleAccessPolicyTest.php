<?php

namespace Tests\Unit;

use App\Services\ConsoleAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ConsoleAccessPolicyTest extends CIUnitTestCase
{
    private function identity(array $extra = []): array
    {
        return $extra + [
            'active'            => true,
            'global_superadmin' => false,
            'user'              => ['id' => 7, 'name' => 'Asha', 'email' => 'asha@aicountly.com', 'role' => 'staff'],
        ];
    }

    public function testAssignedRoleIsUsed(): void
    {
        $d = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['app_roles' => ['super_admin'], 'app_superadmin' => false]));

        $this->assertTrue($d['allowed']);
        $this->assertSame(['super_admin'], $d['roles']);
        $this->assertSame('app_roles', $d['source']);
    }

    public function testEmptyAppRolesIsDeniedWithTheAssignMessage(): void
    {
        $d = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['app_roles' => [], 'app_superadmin' => false]));

        $this->assertFalse($d['allowed']);
        $this->assertSame([], $d['roles']);
        $this->assertSame('Access not assigned. Ask a Console superadmin to assign you a role.', $d['message']);
    }

    public function testActiveConsoleGrantWithoutRoleIsNotEnough(): void
    {
        // The old behaviour provisioned this user as super_admin.
        $d = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['active' => true, 'app_roles' => []]));

        $this->assertFalse($d['allowed']);
    }

    public function testAppSuperadminGetsTheWholeCatalogEvenWithEmptyRoles(): void
    {
        $d = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['app_roles' => [], 'app_superadmin' => true]));

        $this->assertTrue($d['allowed']);
        $this->assertSame(['super_admin'], $d['roles']);
    }

    public function testUnknownSlugsGrantNothing(): void
    {
        $d = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['app_roles' => ['viewer', 'finance_admin', 42]]));

        $this->assertFalse($d['allowed']);
    }

    public function testInactiveIdentityIsDeniedEvenWithRoles(): void
    {
        $d = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['active' => false, 'app_roles' => ['super_admin']]));

        $this->assertFalse($d['allowed']);
    }

    public function testLegacyPayloadAdmitsOnlyGlobalSuperadmin(): void
    {
        $plain = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['active' => true]));
        $this->assertFalse($plain['allowed']);
        $this->assertSame(ConsoleAccessPolicy::MSG_NOT_ASSIGNED, $plain['message']);

        $gsa = ConsoleAccessPolicy::rolesFromIdentity($this->identity(['active' => false, 'global_superadmin' => true]));
        $this->assertTrue($gsa['allowed']);
        $this->assertSame(['super_admin'], $gsa['roles']);
        $this->assertSame('legacy_global_superadmin', $gsa['source']);
    }

    public function testUserAccessRevocations(): void
    {
        $ok = ['exists' => true, 'active' => true, 'app_access' => true, 'app_superadmin' => false, 'app_roles' => ['super_admin']];

        $this->assertSame(['super_admin'], ConsoleAccessPolicy::rolesFromUserAccess($ok));
        $this->assertNull(ConsoleAccessPolicy::rolesFromUserAccess(['exists' => false] + $ok));
        $this->assertNull(ConsoleAccessPolicy::rolesFromUserAccess(['active' => false] + $ok));
        $this->assertNull(ConsoleAccessPolicy::rolesFromUserAccess(['app_access' => false] + $ok));
        $this->assertNull(ConsoleAccessPolicy::rolesFromUserAccess(['app_roles' => []] + $ok));
        $this->assertSame(
            ['super_admin'],
            ConsoleAccessPolicy::rolesFromUserAccess(['app_access' => false, 'app_superadmin' => true, 'app_roles' => []] + $ok),
        );
    }

    public function testOfflineGraceIsTwelveHours(): void
    {
        $now = strtotime('2026-10-14 12:00:00');

        $this->assertTrue(ConsoleAccessPolicy::withinOfflineGrace('2026-10-14 00:00:01', $now));
        $this->assertFalse(ConsoleAccessPolicy::withinOfflineGrace('2026-10-14 00:00:00', $now));
        $this->assertFalse(ConsoleAccessPolicy::withinOfflineGrace(null, $now));
        $this->assertFalse(ConsoleAccessPolicy::withinOfflineGrace('not a date', $now));
    }
}
