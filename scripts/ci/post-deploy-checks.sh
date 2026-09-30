#!/usr/bin/env bash
# post-deploy-checks.sh — is the live Build really this app, and is it the build just deployed?
#
# Run by the deploy workflow after a deploy, and by verify-live.yml to check the live app without
# deploying anything. Every check goes through verify-live.sh, which passes only on the app's real
# answer, never on the host's anti-bot page, and repeats the request from the server (VERIFY_SSH)
# when the runner is shown that page or cannot connect. All checks run; the script exits 1 if any
# of them failed.
#
# Usage: scripts/ci/post-deploy-checks.sh production    (Build has no sandbox)
#
# Environment (all optional):
#   VERIFY_SSH         command prefix that runs one command on the server, e.g. "ssh -T cpanel-prod";
#                      see verify-live.sh.
#   EXPECTED_ENTRY     the hashed entry script of the build just deployed, e.g.
#                      assets/index-C5tx8mVh.js from web/dist/index.html. Empty (checking without a
#                      deploy): the page's <title> is checked instead.
#   EXPECTED_REVISION  the commit just deployed. The Build API does not report the revision it runs,
#                      so it is not compared; the entry script stands for the build.
#   SITE_HOST          the production host (default build.aicountly.org), as the deploy workflow
#                      sets it.
#   VERIFY_BASE_URL    tests only: check this origin (e.g. http://127.0.0.1:18961) instead of the
#                      real one.
#   VERIFY_ATTEMPTS, VERIFY_TIMEOUT are passed on to verify-live.sh.
set -uo pipefail

target="${1:-}"
case "$target" in
  production) origin="https://${SITE_HOST:-build.aicountly.org}" ;;
  *) echo "usage: $0 production    (Build has no sandbox)" >&2; exit 2 ;;
esac
base="${VERIFY_BASE_URL:-$origin}"
base="${base%/}"
verify="$(cd "$(dirname "$0")" && pwd)/verify-live.sh"
entry="${EXPECTED_ENTRY:-}"

echo "Checking Build ${target} at ${base}"
if [ -n "${EXPECTED_REVISION:-}" ]; then
  echo "The Build API does not report its revision, so ${EXPECTED_REVISION} is not compared; the web entry script stands for the build."
fi

failed=0
# check <verify-live.sh arguments...>: one check; a failure is counted, the rest still run.
check() {
  bash "$verify" "$@" && return 0
  failed=$((failed + 1))
  return 1
}
# check_with_hint <hint> <verify-live.sh arguments...>: the same, and prints <hint> when it warned.
check_with_hint() {
  local hint="$1" out
  shift
  out="$(bash "$verify" "$@")" || failed=$((failed + 1))
  printf '%s\n' "$out"
  case "$out" in
    *'::warning title=Post-deploy check::'*) printf '  %s\n' "$hint" ;;
  esac
}

# The web root serves the build just deployed, or at least the Build page (fatal). This replaces a
# grep for "<html", which the host's anti-bot page satisfies too.
if [ -n "$entry" ]; then
  check page "Build web (${target})" "${base}/" "$entry"
else
  check page "Build web (${target})" "${base}/" '<title>AICOUNTLY Build</title>'
fi

# It is Build's API (fatal, as before). "misconfigured" depends on the server's api/.env, not on
# this deploy, so it only warns. The answer is always HTTP 200.
check_with_hint \
  "The answer's data.checks say which: BUILD_JWT_SECRET (32+ characters) or BUILD_VAULT_KEY (64 hex characters) in api/.env." \
  json "Build API (${target})" "${base}/api/health" \
  '.data.service == "aicountly-build-api"' \
  '.success == true and .message == "ready"'

if [ "$failed" -gt 0 ]; then
  echo "::error title=Post-deploy checks::${failed} check(s) failed for Build ${target} at ${base}"
  exit 1
fi
echo "All post-deploy checks passed for Build ${target}."
