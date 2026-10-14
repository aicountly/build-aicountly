#!/usr/bin/env python3
"""live-feature-checks.py — Build's Console staff roles, proven against the live Build.

    python3 scripts/ci/live-feature-checks.py production

Run by verify-live.yml (workflow_dispatch) and by deploy-production.yml after its post-deploy checks. Signs the
verification accounts in (a Build session each); changes nothing else.

  * GET /api/health reports console_role_sync configured (CONSOLE_API_URL + this portal's own
    CONSOLE_API_TOKEN: without them a token refresh cannot re-check roles with Console).
  * Sign-in without a Console session is refused; a forged token is not refreshed.
  * The no-role verification account (Console signs it in) is refused by Build.
  * The staff verification account signs in with its Console roles (VERIFY_STAFF_EXPECTED_ROLES
    compares them) and a refresh — which re-checks Console — succeeds.
  * With LIVE_VERIFY_REVOCATION_DRILL=1 (input revocation_drill): the drill account signs in while
    it holds a build role, an operator revokes it in Console while the job waits, and the next
    refresh must be refused (live_verify.revocation_drill).

Build only:
  * The Flow → Build handoff refuses a call without Flow's token (POST /api/v1/tasks, and
    /api/v1/internal/flow/build-task with a wrong token → 401; 503 = BUILD_FLOW_INBOUND_TOKEN
    unset). Build has no test / dry-run handoff, so no handoff is posted.
  * Over SSH (VERIFY_SSH, BUILD_API_DIR): `php spark build:repair-json` without --apply — a dry run
    that only counts double-encoded JSON values left in the database (0 expected; more warns).

Secrets: the shared VERIFY_IMAP_*, VERIFY_STAFF_*, VERIFY_NOROLE_*, VERIFY_DRILL_* (live_verify.py).
VERIFY_BASE_URL overrides https://build.aicountly.org (tests).
"""
from __future__ import annotations

import os
import re
import shlex
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import live_verify as lv  # noqa: E402

BASE = lv.env("VERIFY_BASE_URL", "https://build.aicountly.org").rstrip("/")
API = f"{BASE}/api"

PORTAL = lv.Portal(
    app_code="build", name="Build",
    health_url=f"{API}/health",
    session_url=f"{API}/v1/auth/console-session",
    refresh_url=f"{API}/v1/auth/refresh", refresh_mode="body_token",
)


def handoff_and_repair(r: lv.Report) -> None:
    junk = {"live_verify": "refusal probe — not a handoff"}
    for path, bearer, label in (("/v1/tasks", None, "without Flow's token"),
                                ("/v1/internal/flow/build-task", "live-verify-wrong-token", "with a wrong token")):
        ans = lv.request("POST", f"{API}{path}", json_body=junk, bearer=bearer, headers={"X-Source": "flow.aicountly.org"})
        if ans.status == 503:
            r.fail(f"Build handoff {label}", "HTTP 503: BUILD_FLOW_INBOUND_TOKEN is not set — Flow cannot hand anything to Build")
        else:
            r.expect(ans.status == 401, f"Build handoff {label}", "HTTP 401", f"expected 401: {ans.brief()}")

    api_dir = lv.env("BUILD_API_DIR")
    if api_dir in ("auto/api", "/api"):  # the deploy's "auto" / empty root means public_html
        api_dir = "public_html/api"
    if not lv.env("VERIFY_SSH") or not api_dir:
        r.skip("Build repair-json dry run", "VERIFY_SSH / BUILD_API_DIR not set")
        return
    code, out, err = lv.on_server(f"cd {shlex.quote(api_dir)} && php spark build:repair-json 2>&1")
    m = re.search(r"Dry run: (\d+) double-encoded", out)
    if code != 0 or not m:
        r.fail("Build repair-json dry run", f"exit {code}: {' '.join((out or err).split())[:200]}")
    elif int(m.group(1)) == 0:
        r.ok("Build repair-json dry run", "0 double-encoded JSON values")
    else:
        r.warn("Build repair-json dry run", f"{m.group(1)} double-encoded JSON values remain — run php spark build:repair-json --apply on the server")


def main() -> int:
    target = sys.argv[1] if len(sys.argv) > 1 else "production"
    if target != "production":
        print("usage: live-feature-checks.py production    (Build has production only)", file=sys.stderr)
        return 2
    r = lv.Report("Build live feature checks")
    lv.portal_access_suite(r, PORTAL, lv.Mailbox())
    r.run("Build handoff / repair-json", lambda: handoff_and_repair(r))
    return r.finish()


if __name__ == "__main__":
    lv.main_guard(main)
