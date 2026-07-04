# GitHub Actions secrets (Build portal)

Copy the **same secret names** from the `reach-aicountly` repository into this
repo under **Settings → Secrets and variables → Actions**. Values can stay
identical for shared cPanel SSH credentials; update `VITE_API_URL` and deploy
path for `build.aicountly.org` when ready.

## deploy-production.yml (manual workflow_dispatch)

| Secret | Used for | Suggested Build value |
| --- | --- | --- |
| `PROD_SFTP_REMOTE_ROOT` | Remote document root (`.` if SSH is chrooted to the subdomain folder) | `.` or path to `build.aicountly.org` docroot |
| `PROD_SFTP_HOST` | cPanel SSH hostname | *copy from reach* |
| `PROD_SFTP_PORT` | SSH port (usually `22`) | *copy from reach* |
| `PROD_SFTP_USER` | cPanel SSH username | *copy from reach* |
| `PROD_SSH_PRIVATE_KEY` | PEM private key for SSH | *copy from reach* |
| `VITE_API_URL` | Frontend build + HTTP health checks | `https://build.aicountly.org/api/v1` |
| `VITE_APP_NAME` | Frontend title | `AICOUNTLY Build` |
| `SUPER_ADMIN_EMAIL` | OwnerSeeder on deploy | `pno@aicountly.com` |
| `SUPER_ADMIN_PASSWORD` | OwnerSeeder on deploy | *(set in GitHub — do not commit)* |

## publish-github-pages.yml (push to main / manual)

| Secret | Used for | Suggested Build value |
| --- | --- | --- |
| `VITE_API_URL` | API base for the SPA | `https://build.aicountly.org/api/v1` |
| `VITE_APP_NAME` | App title | `AICOUNTLY Build` |

`GITHUB_TOKEN` is provided automatically by GitHub Actions.

## After adding secrets

1. **Production (cPanel):** Actions → **Deploy Production via SSH** → Run workflow.
2. **GitHub Pages:** push to `main`, or run **Publish GitHub Pages** manually.
3. In repo **Settings → Pages**, set source to branch `gh-pages` / `(root)`.

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

Sign in at the Build portal with that email and password.
