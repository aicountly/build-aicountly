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
   │   ConsoleClient (outbound 8 event kinds)                     │
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

## 5. Services (`server-php/app/Services`)

| Service                       | Responsibility                                                     |
| ----------------------------- | ------------------------------------------------------------------ |
| `SafetyGuardService`          | Enforces the 12 strict safety rules; throws `HighRiskException`.   |
| `DevRequestWorkflowService`   | Legal transitions across the 18 statuses; writes timeline events.  |
| `ApprovalService`             | Creates, approves, rejects, and applies the effect of approvals.   |
| `DeploymentRequestService`    | Staging + production deployment tracking (separate approvals).     |
| `BotReportService`            | Persists the full "what the bot did" report.                       |
| `FlowInboundService`          | Idempotent handoff processing, dedupes on `flow_handoff_id`.       |
| `PlaywrightWorkerService`     | Enqueues + completes Playwright jobs.                              |
| `ConsoleClient`               | 8 outbound event kinds to Console.                                 |
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
