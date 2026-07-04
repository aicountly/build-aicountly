# AICOUNTLY Build

Internal AI Development Bot portal — `build.aicountly.org`.

Build is the **code-automation authority** for AICOUNTLY. It receives approved
development requirements (from Flow / Console), inspects repositories and UI
(via the Playwright worker), prepares plans, writes code **only after
superadmin approval**, runs tests, creates commits and PRs, requests
deployments, and generates verifiable bot reports.

> Build is **not** a customer SaaS product. It does not use `my.aicountly.com`
> and does not create sandbox-domain logic for itself.

## Stack

| Layer      | Choice |
|------------|--------|
| Frontend   | Vite 8 + React 19 + Tailwind 3 + axios + react-router-dom 7 |
| Backend    | CodeIgniter 4.6 (PHP 8.1+) |
| Database   | PostgreSQL (`build_*` table prefix) |
| Auth       | Independent superadmin JWT (`Authorization: Bearer …`) |
| Deploy     | cPanel — SPA to `public_html/`, API to `public_html/api/` |
| Integrations | GitHub REST v3, Playwright worker (`worker.apis.aicountly.com`), Flow, Console |

## Repo layout

```
build-aicountly/
├── server-php/                       CI 4.6 API (deploys to public_html/api)
│   ├── app/
│   │   ├── Config/                   Paths, Routes, Filters, Cors, Database, Services
│   │   ├── Controllers/Api/V1/       17 module controllers
│   │   ├── Filters/                  JwtFilter, RoleFilter, WorkerAuthFilter, FlowInboundFilter, ConsoleInboundFilter, CorsFilter
│   │   ├── Helpers/                  response_helper, validation_helper
│   │   ├── Libraries/                Jwt, Vault
│   │   ├── Models/                   Thin CI4 Model classes, one per build_* table
│   │   ├── Services/                 SafetyGuard, DevRequestWorkflow, RepoRegistry, Approval, Deployment, BotReport, Audit, FlowInbound, PlaywrightWorker, ConsoleClient
│   │   ├── Services/Github/          GitHubServiceInterface + Live/Null impls
│   │   ├── Services/Ai/              AiProviderInterface + Claude/OpenAI/Aicountly/Mock providers
│   │   └── Database/Migrations       Numbered CI4 migrations for every build_* table
│   ├── composer.json, spark, index.php, .htaccess, .env.example
│   └── writable/                     Cache, logs, sessions (git-ignored)
├── web/                              React SPA (deploys to public_html)
│   ├── src/
│   │   ├── pages/                    All 17 modules
│   │   ├── components/               Sidebar, Topbar, layout, brand, common, build/*
│   │   ├── lib/                      api.js, auth.jsx, format.js, repos.js
│   │   └── constants/routes.js
│   ├── package.json, vite.config.js, tailwind.config.js, postcss.config.js
│   └── .env.example
├── docs/
│   ├── architecture.md
│   ├── dev-request-workflow.md
│   └── deploy.md
└── .github/workflows/
    ├── deploy-production.yml      (cPanel — mirror reach-aicountly)
    └── publish-github-pages.yml   (GitHub Actions Pages — upload artifact + deploy-pages)
```

## Safety model (Part E of the spec — enforced end-to-end)

1. Read / analyse repo metadata — allowed after configuration, audit-logged.
2. Prepare plan — allowed without approval; no code changes.
3. Inspect UI via Playwright worker — allowed; screenshots audited.
4. **Write code** — requires an `approved` `build_approvals` row.
5. **Create branch** — requires code approval AND branch name must start with `WORKING_BRANCH_PREFIX`.
6. **Commit** — separate `commit` approval.
7. **Create PR** — separate `pr` approval.
8. **Never push to `main` / protected branch** — enforced in `GitHubService::createBranch/createCommit` and `SafetyGuardService`.
9. **Production deployment** — always a **separate** `prod_deploy` approval, even if a `staging_deploy` was previously approved for the same request.
10. **High-risk override** — dedicated endpoint requiring `reason` (min 20 chars); writes a `high_risk_override` approval AND a `risk_level=critical` audit entry.
11. GitHub / AI / worker tokens **never** leave the PHP process — frontend only sees `configured / not configured` booleans via `/health/integrations`.
12. Destructive kinds (`file_delete`, `branch_delete`, `force_push`) are enumerated in `SafetyGuardService::DESTRUCTIVE_KINDS` and blocked unless explicitly approved.

## Cross-portal integrations

| Direction | Endpoint | Auth | Purpose |
|-----------|----------|------|---------|
| Flow → Build | `POST /api/v1/tasks` **and** `POST /api/v1/internal/flow/build-task` | `Authorization: Bearer FLOW_INBOUND_TOKEN` | Handoff of approved bug/feature (dedup on `flow_handoff_id`) |
| Console → Build | `POST /api/v1/internal/console/approvals/callback` | `Authorization: Bearer CONSOLE_INBOUND_TOKEN` | Approval decisions replayed into `build_approvals` |
| Build → Console | 8 event kinds via `CONSOLE_API_BASE_URL` | `Authorization: Bearer CONSOLE_API_TOKEN`, `X-Source: build.aicountly.org` | approval_request, audit_event, bot_report_summary, high_risk_action_request, pr_created, deployment_requested, deployment_status_changed, bot_mode_status, health_status |
| Build → Worker | `POST {WORKER_BASE_URL}/api/playwright/*` | `Authorization: Bearer WORKER_API_TOKEN` | Screenshot / UI inspection / smoke navigation / visual evidence |
| Worker → Build | `POST /api/v1/internal/worker/results` | `X-Worker-Token: WORKER_API_TOKEN` | Playwright job results returned to portal |

## Quick start (local dev)

```bash
# API
cd server-php
composer install
cp .env.example .env    # fill BUILD_DB_*, BUILD_JWT_SECRET, BUILD_VAULT_KEY
php spark migrate
php spark db:seed OwnerSeeder      # SUPER_ADMIN_EMAIL=pno@aicountly.com in .env
php spark db:seed ReposSeeder      # populates the 18 AICOUNTLY repos
php spark serve --port 8080

# Web
cd ../web
cp .env.example .env
npm install
npm run dev
```

Visit http://localhost:5173 and sign in with the seeded superadmin credentials.

## Documentation

- [Architecture overview](docs/architecture.md)
- [Development request workflow](docs/dev-request-workflow.md)
- [Deployment guide](docs/deploy.md)
- [GitHub Actions secrets](docs/github-secrets.md) (same names as reach-aicountly)

## License

Proprietary — AICOUNTLY internal use only.
