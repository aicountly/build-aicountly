# GitHub Actions secrets (Build portal)

Secret **names** match `reach-aicountly` exactly. GitHub never exposes secret
values via API — copy values from reach in the browser, or use the sync script
below.

## Quick copy (recommended)

```powershell
# 1. Log in once
gh auth login

# 2. Copy template and paste values from reach-aicountly → Settings → Secrets
Copy-Item .github\secrets.env.example .github\secrets.local.env
# Edit secrets.local.env — paste PROD_SFTP_*, PROD_SSH_* from reach;
# set VITE_API_URL / SUPER_ADMIN_* for build.

# 3. Push secrets to build-aicountly
.\.github\scripts\copy-secrets-from-reach.ps1 -EnvFile .github\secrets.local.env
```

To push **placeholder** defaults first (update in GitHub UI later):

```powershell
gh auth login
.\.github\scripts\copy-secrets-from-reach.ps1
```

## Secret list (same as reach-aicountly)

| Secret | Copy from reach? | Build value (update later) |
| --- | --- | --- |
| `PROD_SFTP_REMOTE_ROOT` | Yes — usually identical | `public_html` |
| `PROD_SFTP_HOST` | Yes — identical | *same as reach* |
| `PROD_SFTP_PORT` | Yes — identical | *same as reach* |
| `PROD_SFTP_USER` | Yes — identical | *same as reach* |
| `PROD_SSH_PRIVATE_KEY` | Yes — identical | *same as reach* |
| `VITE_API_URL` | No — change host | `https://build.aicountly.org/api` |
| `VITE_APP_NAME` | No — change title | `AICOUNTLY Build` |
| `SUPER_ADMIN_EMAIL` | Optional | `pno@aicountly.com` |
| `SUPER_ADMIN_PASSWORD` | Set fresh | *(your password)* |

`GITHUB_TOKEN` is automatic — do not create manually.

## cPanel layout (deploy-production.yml)

```
public_html/              ← web/dist/   (React SPA + .htaccess)
public_html/api/          ← server-php/ (CodeIgniter 4.6 API)
public_html/api/.env      ← created manually on server (never deployed)
```

## publish-github-pages.yml

Only needs `VITE_API_URL` and `VITE_APP_NAME` (same table above).

## After secrets are set

1. **Production (cPanel):** Actions → **Deploy Production via SSH** → Run workflow.
2. **GitHub Pages:** push to `main`, or run **Publish GitHub Pages** manually.
3. Repo **Settings → Pages → Source** = **GitHub Actions**.

## Local superadmin login

Set in `server-php/.env` (never commit):

```env
SUPER_ADMIN_EMAIL=pno@aicountly.com
SUPER_ADMIN_PASSWORD=your-password-here
```

Then run:

```bash
cd server-php
php spark migrate
php spark db:seed OwnerSeeder
```
