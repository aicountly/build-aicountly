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

## 2. Independent superadmin auth

Build does **not** use `my.aicountly.com` login. It has its own JWT-based
superadmin auth backed by `build_users`:

- Owner is seeded from `BUILD_OWNER_EMAIL` + `BUILD_OWNER_PASSWORD_HASH`
  via `spark seed:owner`.
- JWT is signed with `BUILD_JWT_SECRET` and expires after
  `BUILD_JWT_TTL_MINUTES` (default 720).
- Refresh is available at `POST /api/v1/auth/refresh`.
- Only `super_admin` role can access any Build endpoint outside `auth/*` and
  the inbound Flow/Console/worker filters.

## 3. Filters (server-php/app/Filters)

| Filter                | Purpose                                                                 |
| --------------------- | ----------------------------------------------------------------------- |
| `CorsFilter`          | CORS for `BUILD_ALLOWED_ORIGINS`.                                       |
| `JwtFilter`           | Decodes bearer JWT, hydrates current user.                              |
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
