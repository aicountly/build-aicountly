# Development Request Workflow

Every development request lives in `build_dev_requests`. The `status` column
carries a `CHECK` constraint over the 18 canonical statuses below. The
`DevRequestWorkflowService` enforces the legal transition table and appends a
row to `build_dev_request_events` on every change.

## 18 canonical statuses

| # | status                          | meaning                                                                |
| - | ------------------------------- | ---------------------------------------------------------------------- |
| 1 | `received`                      | Landed from Flow / Console / manual entry.                             |
| 2 | `analyzing`                     | Bot is running the `analyze` step (read-only).                         |
| 3 | `plan_prepared`                 | Bot has an implementation plan ready for review.                       |
| 4 | `pending_approval`              | Waiting for superadmin `code` approval.                                |
| 5 | `approved_for_code`             | Superadmin approved code writing.                                      |
| 6 | `coding`                        | Bot is generating code (writes not yet committed).                     |
| 7 | `tests_running`                 | Tests recorded via `/tests/run` are in flight.                         |
| 8 | `pending_commit_approval`       | Bot produced a commit proposal; superadmin must approve.               |
| 9 | `committed`                     | Commit pushed to GitHub on the working branch.                         |
| 10 | `pending_pr_approval`          | Bot produced a PR proposal; superadmin must approve.                   |
| 11 | `pr_created`                   | PR is open on GitHub.                                                  |
| 12 | `pending_staging_deployment`   | Awaiting staging deploy approval.                                      |
| 13 | `staging_deployed`             | Staging deploy recorded.                                               |
| 14 | `pending_production_approval`  | Production deploy is **always** a fresh approval.                      |
| 15 | `production_deployed`          | Production deploy recorded.                                            |
| 16 | `failed`                       | Terminal — recorded reason lives in the latest event.                  |
| 17 | `rejected`                     | Superadmin rejected an approval; workflow halts.                       |
| 18 | `closed`                       | Terminal — request closed for any other reason.                        |

## Approval-gated edges

The following transitions **cannot** happen without an entry in `build_approvals`
with `status = approved`:

| From                            | To                              | Approval action needed  |
| ------------------------------- | ------------------------------- | ----------------------- |
| `pending_approval`              | `approved_for_code`             | `code`                  |
| `pending_commit_approval`       | `committed`                     | `commit`                |
| `pending_pr_approval`           | `pr_created`                    | `pr`                    |
| `pending_staging_deployment`    | `staging_deployed`              | `staging_deploy`        |
| `pending_production_approval`   | `production_deployed`           | `prod_deploy` (fresh)   |

## The 17 fields on a dev request

`requirement_text`, `product`, `repo_id`, `source_portal`, `source_id`,
`request_type`, `priority`, `risk_level`, `status`, `related_pages`,
`related_repos`, `files_likely_affected`, `applied_files`,
`test_evidence`, `commit_sha`, `pr_url`, `deployment_url` — plus timestamps,
`assigned_bot`, and `flow_handoff_id`.

## Safety rules enforced by `SafetyGuardService`

1. Never write code, create branches, commits, or PRs without a valid `code` /
   `commit` / `pr` approval.
2. Working branches must match `allowed_working_branch_prefix` of the target
   repo (default `build/bot/`).
3. Never touch the `protected_branch` of the repo (usually `main`).
4. No `force_push`, no `branch_delete`, no bulk `file_delete` without a
   `high_risk_override` approval.
5. Production deployments always require a fresh `prod_deploy` approval, even
   if the same request was previously staging-deployed.
6. Bot never writes to `my.aicountly.com` login systems or any auth-related
   files without an override.
7. Never runs shell commands with `sudo`, deletion primitives, or destructive
   database migrations unattended.
8. All AI calls are logged to `build_ai_provider_calls` (input/output digested,
   never raw secrets).
9. All GitHub calls are logged to `build_github_activity`.
10. All Console fan-outs land in `build_console_syncs`.
11. Bot Mode `auto` never bypasses code / commit / PR / prod-deploy approvals.
12. Every safety violation raises `HighRiskException`, is written to
    `build_audit_logs` at `risk_level = critical`, and is fanned out to Console.
