# v0.9.0 — Recovery, operational monitoring and theme-preserving staging

**Status:** Local development code, **not** a live site. Canonical Source of Truth v1.3.3 is unchanged. As of 23 Nov 2026, the existing `https://overseasjob.in/` static theme has **not** been available for a verified visual comparison; we have not replaced or restyled it.

## 1. Password recovery for invited, verified employers (M12/M15)

- New gated `/employers/reset` route: mailbox code request and confirmation, CSRF token, HTTPS-only cookie, no login bypass, generic request response for unknown or disabled accounts. The route remains `503` while the pilot is disabled.
- Random 8-digit code is valid for **15 minutes**, stored as purpose-separated HMAC, limited to five incorrect attempts and one use. Requests are rate-limited by keyed hashes of email and IP in an hourly bucket (3/email and 30/IP). Codes never appear in the URL, browser telemetry or output logs.
- Password reset updates the password hash and increments `auth_version` atomically; already logged-in employer sessions must have their exact current version. No other company's data is exposed.
- `database/005_recruiter_password_recovery.mysql.sql` adds `auth_version` and a private reset table. Apply **once** after migrations 001–004, only on inspected, backed-up staging. The table intentionally contains no applicants or candidate CVs.
- `RecruiterMailer` sends plain-text mail using an explicitly configured **existing** PHP `mail()` transport; both verify/reset URLs must be HTTPS, same host and exact employer routes. `.env.example` contains only blank configuration. Transport acceptance **does not prove inbox delivery**. The existing invite sender now uses the same validation. No paid mail API introduced.
- Confirm actual outgoing SMTP/PHP mail support, SPF/DKIM/DMARC and end-to-end mailbox receipt on authorised Hostinger staging **before enabling**. If PHP mail is blocked or unreliable, keep these features OFF; do not claim a functioning recovery channel.

## 2. Theme-preservation safety (M00/M16)

New **read-only** CLI check:

```sh
php scripts/theme_collision_audit.php /path/to/clean-static-site-backup /path/to/overseasjob/public
```

Requires **two independent local directories**. Reports hashed content collisions by relative path, CSS overrides and ambiguous `index.html` (static) / `index.php` (dynamic) entrypoints. It refuses symlinks and always concludes `safe_to_replace_current_theme: false` and exits 2: **no static check can authorise a live design replacement**. It never copies, deletes or writes to the existing site. Do not run it against a sensitive live webroot; use a clean sanitized backup instead. All credentials/secrets and unrelated Hostinger websites must stay private.

The real static HTML/CSS/assets and screenshots from `overseasjob.in` are not present in this source pack. Pixel-level theme matching, responsive navigation parity, animation and visual E2E remain **blocked** until the original site's safe static export can be inspected. Isolated staging on a separate subdomain is the only accepted first deployment target. **Do not copy `public/` into live `public_html` or change DNS.**

## 3. Operational snapshot and evidence

`php scripts/health.php` is now a read-only, privacy-safe operational report. It displays cache state, visible source jobs, actual distinct UTC days, backlog count and local backup presence; it never emits raw reports, private candidate text, HMAC secrets or tokens. Missing cache/report/backup state produces warnings, not false healthy status. It cannot prove off-site backup encryption, actual email receipt, provider permissions or Hostinger health.

`sh tests/run.sh` includes isolated fixtures and a complete SHA-256 release-manifest read-only verifier. Existing CI uses a disposable MySQL 8 service to exercise member reset, old-session invalidation, review, tenant scope and token revocation. These are not production tests. `php scripts/launch_preflight.php` still **must** return `ready:false` without real launch evidence.

## 4. Mandatory before enabling any real employer account

1. Confirm Hostinger hosting plan, PHP extensions, MySQL, HTTPS, document root separation, private storage permissions and rollback/restore.
2. Test existing mail transport in an actual inbox; configure `RECRUITER_MAIL_ENABLED`, `RECRUITER_EMAIL_FROM`, `RECRUITER_VERIFY_URL`, and `RECRUITER_RESET_URL` via private hosting environment only (not GitHub).
3. Apply 001–005 on backed-up staging, verify all independent org identity/representative approvals, then run invite/reset/login browser E2E and email abuse-rate tests.
4. Compare the static-theme backup with the staging frontend; transfer the approved theme manually with responsive acceptance tests before any production cutover.
5. Validate real job-source licensing and 14 days of actual coverage; perform full privacy/legal/editorial review and off-site backup recovery drills.

**No Hostinger, current theme or live provider was modified or verified during this code release.**
