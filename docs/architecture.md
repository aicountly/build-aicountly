# AICOUNTLY Build — Architecture

`build.aicountly.org` is the **AI Development Bot Portal**. It is the single
authority for GitHub / code automation across the AICOUNTLY estate. It is
approval-gated by design — no code write, commit, PR, or production deployment
happens without an explicit superadmin decision recorded in `build_approvals`.

## 1. Deployment topology

```
                    my.aicountly.com   (login only — NOT used by Build)
                          │
                          │  users
                          ▼
   ┌──────────────────────────────────────────────────────────────┐
   │                                                              │
   │   Build UI (React 19)  <────────────────── Console UI        │
   │   github pages / cpanel                    api.aicountly.com │
   │                                                              │
   └────────────┬─────────────────────────────────────────────────┘
                │  https://build.aicountly.org/api/v1
                ▼
   ┌──────────────────────────────────────────────────────────────┐
   │  Build API — CodeIgniter 4.6 (server-php/)                   │
   │                                                              │
   │   PostgreSQL: build_* tables (independent superadmins)       │
   │   GitHubServiceInterface (Live / Null)                       │
   │   AiProviderInterface (Claude / OpenAI / Aicountly / Mock)   │
   │   Playwright worker client (worker.apis.aicountly.com)       │
   │   ConsoleClient (central audit → Console /portal/audit)      │
   │   FlowInboundService (idempotent handoff)                    │
   │   SafetyGuardService (12 rules, blocks unsafe writes)        │
   └────────────┬─────────────────────────────────────────────────┘
                │
                ├──► GitHub REST (backend only)
                ├──► AI provider APIs
                ├──► worker.apis.aicountly.com (Playwright)
                └──► console-inbound / flow-inbound
```

## 2. Staff sign-in and roles (Console)

Build does **not** use `my.aicountly.com` login, and local password login is
disabled. Console (`console.aicountly.org`) is the identity provider **and**
the source of staff roles. Build keeps a `build_users` row per staff member
only to hold the role Console last assigned.

Catalog for app_code `build`: `super_admin` (Build Administrator) — the only
role. Every portal route outside `auth/*` and the inbound
Flow/Console/worker filters requires it (`role:super_admin`).

**Sign-in** — `GET /v1/auth/sso-callback`, `POST /v1/auth/controller-sso`,
`POST /v1/auth/console-session` all hand Console's payload to
`ConsoleRoleSyncService::signIn()`:

| Console payload | Result |
| --- | --- |
| `app_roles` contains `super_admin`, or `app_superadmin: true` | role `super_admin`; row created/updated |
| `app_roles` empty (or only unknown slugs) and not `app_superadmin` | **403** "Access not assigned. Ask a Console superadmin to assign you a role." — no row created; an existing row loses its role and its sessions end |
| no `app_roles` key (Console before role assignment) | only `global_superadmin: true` gets `super_admin`; everyone else 403 (same message) |

There is no default role anywhere (create, refresh, `/me`). The sync stores
`console_user_id`, `roles_synced_at`, `authz_version` and the raw
`console_app_roles` (migration `2026-10-14-000021`).

**Refresh** — `POST /v1/auth/refresh {token}` (the SPA calls it on every
load) runs `ConsoleRoleSyncService::refresh()`, which pulls
`GET {CONSOLE_API_URL}/portal/controller-apps/user-access?app_code=build&console_user_id=…`
(or `&email=`) with `Authorization: Bearer {CONSOLE_API_TOKEN}`:

| Outcome | Result |
| --- | --- |
| user-access says not `exists` / not `active` / no `app_access` / no role (and not `app_superadmin`) | **403**, role cleared, `sessions_revoked_at` set — every earlier Build JWT stops working |
| user-access returns roles | role re-synced, new JWT |
| Console unreachable / non-2xx / malformed | allowed only if `roles_synced_at` < 12 h ago, else 503 |
| `CONSOLE_API_URL` or `CONSOLE_API_TOKEN` unset | warning logged, last synced role kept (production must set both) |

**Per request** — `JwtFilter` loads the `build_users` row and uses the
**stored** role, not the role claim in the token. It refuses (401) tokens for
inactive users, tokens issued before `sessions_revoked_at`, and tokens for
rows never synced under these rules (rows from before the migration — those
users simply sign in again through Console).

JWTs are signed with `BUILD_JWT_SECRET` and expire after
`BUILD_JWT_TTL_MINUTES` (default 720).

## 3. Filters (server-php/app/Filters)

| Filter                | Purpose                                                                 |
| --------------------- | ----------------------------------------------------------------------- |
| `CorsFilter`          | CORS for `BUILD_ALLOWED_ORIGINS`.                                       |
| `JwtFilter`           | Decodes bearer JWT, loads `build_users`, hydrates current user with the stored (Console-synced) role. |
| `RoleFilter`          | `role:super_admin` — enforced on every portal endpoint.                 |
| `WorkerAuthFilter`    | `X-Worker-Token` from `BUILD_WORKER_TOKEN` (Playwright callbacks).      |
| `FlowInboundFilter`   | `Authorization: Bearer` + optional `X-Signature` HMAC (Flow → Build).    |
| `ConsoleInboundFilter`| `Authorization: Bearer` from `BUILD_CONSOLE_INBOUND_TOKEN`.             |

## 4. Data model (`build_*`)

All tables are prefixed `build_`. Highlights:

- `build_dev_requests` — 18-status CHECK constraint drives the workflow.
- `build_dev_request_events` — append-only timeline of transitions and bot events.
- `build_approvals` — every high-risk action lives here.
- `build_flow_handoffs` — idempotency on `flow_handoff_id`.
- `build_bot_reports` — the full report per bot action (Part L).
- `build_playwright_jobs` — worker jobs and their kind/status.
- `build_commits`, `build_pull_requests`, `build_deployment_requests` — code lifecycle.
- `build_github_activity`, `build_console_syncs`, `build_ai_provider_calls`,
  `build_audit_logs` — everything the bot does is auditable.

### JSONB columns

Every JSONB column is declared on its model as a cast — `?json-array` when the
column is nullable, `json-array` for `build_flow_handoffs.raw_payload` (NOT
NULL) — and the model extends `App\Models\BuildModel`. **Write PHP arrays to
these fields, never `json_encode()` output**: the cast encodes once on write
and decodes on read.

Until October 2026 several writers (`FlowInboundService`, `ApprovalService`,
`TestRunnerController`, `LiveGitHubService`, `AuditService`, `ConsoleClient`,
`DevRequestWorkflowService`, `BotReportService`, `PlaywrightWorkerService`,
`BotWorkbenchController`, `ConsoleCallbackController`,
`DevRequestsController::update`, `SettingsModel`) did encode first, so the cast
encoded again and the column held a JSON *string* wrapping the document.
CodeIgniter's `JsonCast::get()` then returned a string where it must return an
array and the request failed with HTTP 500 — a valid Flow handoff
(`POST /api/v1/tasks`) among them. The casts were also non-nullable, so any row
with a NULL JSON column (most of them) failed to load, and every audit entry
without `old_value` was silently dropped.

- **Reads are tolerant.** `BuildModel` swaps CodeIgniter's `json` cast handler
  for `App\Models\Cast\TolerantJsonCast`, which unwraps string-wrapped JSON
  (up to four layers) and passes JSON scalars through (`build_settings` stores
  values such as `"confirm"`). As a backstop, a `json-array` field handed a
  string that is itself a JSON object/array stores the document instead of
  encoding it again, and logs a warning.
- **Stored rows are repaired by command**, not by a migration:
  `php spark build:repair-json` (dry run) then `php spark build:repair-json --apply`.
  It only rewrites values whose JSONB type is `string` and whose text is itself
  JSON (`{…}`, `[…]` or `"…"`), re-checks each value as it updates it, and is a
  no-op when run again. Bare numbers/booleans stored as strings (`"5"`, `"true"`)
  are left alone because they are ambiguous. It is a command rather than a
  migration so an operator sees the dry-run counts before audit data is
  rewritten; nothing depends on it running, because the reads already cope.
- `TolerantJsonCastTest` (unit) fails if a model with a JSON cast does not
  extend `BuildModel`, uses a non-nullable cast for a nullable column, or is
  missing from the repair command.

## 5. Services (`server-php/app/Services`)

| Service                       | Responsibility                                                     |
| ----------------------------- | ------------------------------------------------------------------ |
| `SafetyGuardService`          | Enforces the 12 strict safety rules; throws `HighRiskException`.   |
| `DevRequestWorkflowService`   | Legal transitions across the 18 statuses; writes timeline events.  |
| `ApprovalService`             | Creates, approves, rejects, and applies the effect of approvals.   |
| `DeploymentRequestService`    | Staging + production deployment tracking (separate approvals).     |
| `BotReportService`            | Persists the full "what the bot did" report.                       |
| `FlowInboundService`          | Idempotent handoff processing, dedupes on `flow_handoff_id`.       |
| `JsonColumnRepairService`     | Unwraps double-encoded JSONB values (`spark build:repair-json`).   |
| `PlaywrightWorkerService`     | Enqueues + completes Playwright jobs.                              |
| `ConsoleClient`               | Central audit to Console (`ConsoleAuditOutbox`, see §Console below). |
| `Github\*`                    | `LiveGitHubService` (REST) or `NullGitHubService` fallback.        |
| `Ai\*`                        | `AiProviderInterface`, Mock/Claude/OpenAI/Aicountly implementations. |

## 6. React UI

- Vite/React 19, Tailwind, `aicountly-*` green scale.
- Independent JWT stored in `localStorage`, sent as `Bearer`.
- Envelope-unwrapping HTTP client in `src/lib/api.js`.
- 17 modules, list + detail patterns, compact Console/Flow-style UI.

## 7. Configuration

Everything sensitive lives in `server-php/.env` (see `.env.example`). Nothing
is hardcoded, and secrets never leak to the client. Frontend only knows
`VITE_API_URL` + `VITE_APP_NAME`.

## Console: what Build sends (2026-10-14)

Build sends Console **one** thing: its central audit — every `AuditService` action whose prefix is
in `CONSOLE_FANOUT_PREFIXES` (`approval.`, `bot.`, `commit.`, `pr.`, `deployment.`, `dev_request.`,
`auth.`, `high_risk.`), to `POST {CONSOLE_API_URL}/portal/audit` with Build's own Console key
(capability `audit.write`), through the `build_console_syncs` outbox (`ConsoleAuditOutbox`, retried by
`php spark console:deliver-audit`). In Console: Audit Logs, app = Build.

Until 2026-10-14 `ConsoleClient` also sent eight event kinds to `/api/v1/portal/build/*`
(`approval_request`, `bot_report_summary`, `high_risk_action_request`, `pr_created`,
`deployment_requested`, `deployment_status_changed`, `bot_mode_status`, `health_status`). Console has
never served those paths (no such route anywhere in its history), so each call ended as a `failed` /
`skipped_not_configured` row and Console learnt nothing. They were **removed**, not built in Console:

| Removed call | What it reported | Where it is now |
|---|---|---|
| approval_request | an approval Build needs | Build decides approvals itself (Approvals page, `build_approvals`); `approval.requested` / `.approved` / `.rejected` audit events (with action, approval id and the payload when ≤ 16 KB) |
| high_risk_action_request | a high-risk override was recorded | `high_risk.override_recorded` audit event (approval id, reason) |
| pr_created | Build opened a PR | `pr.created` audit event (PR number, URL, dev request, repo) |
| deployment_requested / deployment_status_changed | a deployment was requested / approved | `deployment.requested.<env>` / `deployment.approved.<env>` / `deployment.deployed.<env>` audit events (deployment id, environment, repo) |
| bot_mode_status | the bot mode changed | `bot.mode_changed` audit event (old / new mode) |
| bot_report_summary, health_status | — | had no caller |

Console's cross-portal approval queue is the Bot Orchestrator (`POST /portal/bot-approval-request`,
`bot_portals` key) and is meant for bot actions; Build's human approvals stay in Build, as Flow's
superadmin approvals stay in Flow. `POST /api/v1/internal/console/approvals/callback`
(`BUILD_CONSOLE_INBOUND_TOKEN`) is kept for a Console that decides Build approvals; Console does not
call it today.
