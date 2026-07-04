# server-php/scripts/

Utility scripts intended to be run over SSH on the cPanel host. All scripts read
their configuration from `server-php/.env` — never from hardcoded values.

- `db-ping.php` (repo root) — quick PostgreSQL connectivity probe.
- `check-env.php` (repo root) — sanity-check required env keys.

Add new scripts here as needed. Anything that touches production must be
audit-logged via `service('auditService')`.
