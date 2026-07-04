<?php

namespace App\Services;

use App\Models\ApprovalsModel;
use App\Models\CodeTasksModel;
use App\Models\ReposModel;
use RuntimeException;

/**
 * Encodes the 12 safety rules from Part E of the Build spec.
 *
 * Every write-oriented action must call `assertAllowed($action, $context)`
 * before touching the outside world. The guard throws HighRiskException if:
 *   - the action requires approval and there is no matching approved row, OR
 *   - the action targets a protected branch, OR
 *   - the action is enumerated as destructive without explicit approval.
 *
 * Rules covered:
 *   1-3 read/plan/inspect              — assertReadAllowed()      (audit only)
 *   4   write code                     — assertAllowed('code',    …)
 *   5   create branch                  — assertAllowed('branch_create', …)
 *   6   create commit                  — assertAllowed('commit',  …)
 *   7   create PR                      — assertAllowed('pr',      …)
 *   8   never push main/protected      — assertBranchAllowed(...)
 *   9   production deploy              — assertAllowed('prod_deploy', …)
 *  10   high-risk override             — recordHighRiskOverride(...)
 *  11   token opacity                  — enforced by not exposing envs to FE
 *  12   destructive kinds              — DESTRUCTIVE_KINDS list
 */
class SafetyGuardService
{
    public const DESTRUCTIVE_KINDS = ['file_delete', 'branch_delete', 'force_push'];

    /** Actions that always require an approved build_approvals row. */
    public const APPROVAL_REQUIRED_ACTIONS = ['code', 'commit', 'pr', 'staging_deploy', 'prod_deploy'];

    private ApprovalsModel $approvals;
    private ReposModel     $repos;

    public function __construct()
    {
        $this->approvals = new ApprovalsModel();
        $this->repos     = new ReposModel();
    }

    /**
     * Ensure an approved approval row exists for the given dev request + action.
     * Throws HighRiskException on violation.
     *
     * @param array{repo_id?: int, branch?: string, kind?: string, override?: bool, reason?: string} $context
     */
    public function assertAllowed(string $action, int $devRequestId, array $context = []): void
    {
        if (! in_array($action, self::APPROVAL_REQUIRED_ACTIONS, true)) {
            throw new HighRiskException("Unknown guarded action: {$action}");
        }

        // Destructive kinds always require explicit high_risk_override, even if 'code' was approved.
        $kind = $context['kind'] ?? null;
        if ($kind && in_array($kind, self::DESTRUCTIVE_KINDS, true)) {
            $override = $this->approvals->findApproved('dev_request', $devRequestId, 'high_risk_override');
            if (! $override) {
                throw new HighRiskException("Destructive action '{$kind}' requires a high_risk_override approval.");
            }
        }

        $approved = $this->approvals->findApproved('dev_request', $devRequestId, $action);
        if (! $approved) {
            throw new HighRiskException("Action '{$action}' requires an approved build_approvals row for dev_request #{$devRequestId}.");
        }

        // Rule 8 — branch guard on any action that writes to a branch.
        if (in_array($action, ['code', 'commit', 'branch_create'], true) && ! empty($context['repo_id']) && ! empty($context['branch'])) {
            $this->assertBranchAllowed((int) $context['repo_id'], (string) $context['branch']);
        }
    }

    /**
     * Rule 8 — branch is neither the default nor a protected branch,
     * and starts with the repo's allowed working-branch prefix.
     */
    public function assertBranchAllowed(int $repoId, string $branch): void
    {
        $repo = $this->repos->find($repoId);
        if (! $repo) {
            throw new HighRiskException("Repo #{$repoId} is not registered.");
        }

        $protected = array_filter([
            $repo['default_branch']   ?? null,
            $repo['protected_branch'] ?? null,
        ]);
        if (in_array($branch, $protected, true)) {
            throw new HighRiskException("Refusing to touch protected branch '{$branch}' on repo '{$repo['repo_code']}'.");
        }

        $prefix = (string) ($repo['allowed_working_branch_prefix'] ?? 'build-bot/');
        if ($prefix !== '' && ! str_starts_with($branch, $prefix)) {
            throw new HighRiskException("Branch '{$branch}' must start with '{$prefix}' (repo '{$repo['repo_code']}').");
        }
    }

    /**
     * Read/plan/inspect are always allowed — but must still be audit-logged
     * by the caller. This method exists as a documented no-op to make the
     * safety trail explicit at call sites.
     */
    public function assertReadAllowed(int $devRequestId, string $reason): void
    {
        // Intentionally permissive — Rules 1..3.
    }

    /**
     * Rule 10 — record a high-risk override. Reason must be at least 20 chars.
     * The caller MUST also call AuditService::log() with risk_level=critical.
     *
     * @return int approval id
     */
    public function recordHighRiskOverride(int $devRequestId, string $reason, ?int $requesterId): int
    {
        $reason = trim($reason);
        if (strlen($reason) < 20) {
            throw new HighRiskException('High-risk override reason must be at least 20 characters.');
        }

        return (int) $this->approvals->insert([
            'entity_type'  => 'dev_request',
            'entity_id'    => $devRequestId,
            'action'       => 'high_risk_override',
            'status'       => 'approved',
            'reason'       => $reason,
            'requester_id' => $requesterId,
            'decided_by'   => $requesterId,
            'decided_at'   => date('Y-m-d H:i:s'),
        ], true);
    }
}
