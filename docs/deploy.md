# Deploying AICOUNTLY Build

Build ships two artefacts:

1. **`server-php/`** — CodeIgniter 4.6 API at `build.aicountly.org/api/`.
2. **`web/`** — Vite/React SPA at `build.aicountly.org/` (or GitHub Pages).

Deployment mirrors **reach-aicountly** (SSH + scp, not rsync):

| Workflow | Trigger | Target |
| --- | --- | --- |
| `deploy-production.yml` | Manual (`workflow_dispatch`) | `web/dist` → `public_html/`, `server-php/` → `public_html/api/` |
| `publish-github-pages.yml` | Push to `main` / manual | `gh-pages` branch |

GitHub secret names are identical to reach — see [github-secrets.md](github-secrets.md).

## 1. Backend + SPA: cPanel (deploy-production.yml)

### One-time setup

- Create a subdomain `build.aicountly.org` pointing at a directory (e.g.
  `~/public_html/build`).
- Copy `server-php/.env.example` to `server-php/.env` **on the server**,
  and fill in real values:
  - `BUILD_DB_*` — PostgreSQL details.
  - `BUILD_JWT_SECRET` — 32+ random chars.
  - `BUILD_VAULT_KEY` — 64 hex chars (AES-256 key material).
  - `BUILD_GITHUB_TOKEN` — a fine-grained PAT scoped only to the 18 repos.
  - `BUILD_AI_PROVIDER` — `claude`, `openai`, `aicountly`, or leave unset
    (the Null provider is used automatically).
  - `BUILD_WORKER_TOKEN` — shared secret with the Playwright worker.
  - `BUILD_FLOW_INBOUND_TOKEN`, `BUILD_FLOW_HMAC_SECRET` — from Flow.
  - `BUILD_CONSOLE_INBOUND_TOKEN`, `BUILD_CONSOLE_OUTBOUND_URL`,
    `BUILD_CONSOLE_OUTBOUND_TOKEN` — from Console.
  - `BUILD_OWNER_EMAIL`, `BUILD_OWNER_NAME`, `BUILD_OWNER_PASSWORD` (or
    `SUPER_ADMIN_*` — same as GitHub secrets; default email `pno@aicountly.com`).
- Add GitHub Actions secrets — **same names as reach-aicountly** (see
  [github-secrets.md](github-secrets.md)): `PROD_SFTP_*`, `PROD_SSH_PRIVATE_KEY`,
  `VITE_API_URL`, `VITE_APP_NAME`, `SUPER_ADMIN_EMAIL`, `SUPER_ADMIN_PASSWORD`.

### Deploy via GitHub Actions

1. Copy secrets from the reach repo (update `VITE_API_URL` for build).
2. Actions → **Deploy Production via SSH** → Run workflow.

The workflow will:

1. Build the React SPA into `web/dist/`
2. Run `composer install --no-dev` in CI (cPanel often has no global composer)
3. Upload `web/dist/` → **`public_html/`** (via `PROD_SFTP_REMOTE_ROOT`)
4. Upload `server-php/` **including `vendor/`** → **`public_html/api/`**
   - **Never** ships or overwrites `api/.env` — the workflow backs up the
     existing server `.env` before replacing `api/`, then restores it unchanged.
5. Run `php spark migrate` and seed the superadmin when `SUPER_ADMIN_*` secrets are set

### First-time admin bootstrap

On the server, after the first deploy:

```
php spark seed:owner
php spark seed:repos
```

## 2. Frontend: GitHub Pages (publish-github-pages.yml)

- Uses `actions/upload-pages-artifact` + `actions/deploy-pages` (official
  GitHub Actions Pages flow).
- **Settings → Pages → Build and deployment → Source = GitHub Actions.**
- Set repository secrets `VITE_API_URL` and `VITE_APP_NAME` (optional — defaults
  are baked into the workflow).
- Push to `main`, or run **Publish GitHub Pages** manually.
- Site URL: `https://aicountly.github.io/build-aicountly/` (project page).

## 3. Manual migration steps

If a deploy needs to be reproduced by hand:

```
cd server-php
composer install --no-dev --optimize-autoloader
cp .env.example .env         # fill in real values
php spark migrate --all -n
php spark seed:owner         # once
php spark seed:repos         # once (or when repo list changes)
```

## 4. Rollback

Because the DB migrations are additive, you can safely re-deploy an older
commit. If you must roll back a migration:

```
php spark migrate:rollback -b <target_batch>
```

Do this only in coordination with a superadmin — dev requests, approvals, and
bot reports depend on the current schema.
