<?php

namespace Tests\Unit;

use App\Services\ConsoleClient;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Build sends Console one thing: the central audit (POST {CONSOLE_API_URL}/portal/audit).
 * The eight /api/v1/portal/build/* calls (approval_request, bot_report_summary,
 * high_risk_action_request, pr_created, deployment_requested, deployment_status_changed,
 * bot_mode_status, health_status) targeted routes Console never had and were removed; their
 * state changes reach Console as approval.* / pr.* / deployment.* / high_risk.* / bot.* audit
 * events. Nothing may call them again.
 *
 * @internal
 */
final class ConsoleClientSurfaceTest extends CIUnitTestCase
{
    public function testConsoleClientOnlySendsTheCentralAudit(): void
    {
        $methods = array_map(static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(ConsoleClient::class))->getMethods(\ReflectionMethod::IS_PUBLIC));
        sort($methods);
        $this->assertSame(['isConfigured', 'sendAuditEvent'], $methods);
    }

    public function testNoCodeTargetsTheMissingConsoleRoutes(): void
    {
        $hits = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            if (preg_match('#[\x27"][^\x27"\n]*(portal/build/|/api/v1/portal/audit)|->send(ApprovalRequest|BotReportSummary|HighRiskActionRequest|PrCreated|DeploymentRequested|DeploymentStatus|BotModeStatus|HealthStatus)\(#', $src) === 1) {
                $hits[] = substr($file->getPathname(), strlen(APPPATH));
            }
        }
        $this->assertSame([], $hits);
    }

    public function testTheirStateChangesAreFannedOutToTheCentralAudit(): void
    {
        $src = (string) file_get_contents(APPPATH . 'Services/AuditService.php');
        foreach (["'approval.'", "'pr.'", "'deployment.'", "'high_risk.'", "'bot.'"] as $prefix) {
            $this->assertStringContainsString($prefix, $src, "AuditService fans out {$prefix}");
        }
    }
}
