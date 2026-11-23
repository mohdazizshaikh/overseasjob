# OverseasJob.in — Code Development v0.9.11 (complete Git-tracked release manifest)

**State: working local application and future-gated contract foundation; NOT deployed, NOT public-launch ready.**

**v0.9.11 manifest completeness:** `scripts/refresh_release_manifest.py --check` enforces every Git-tracked file is included in the release manifest, not only that existing listed SHA hashes match. Agent-safe `--write` regenerates all tracked paths and byte digests atomically. CI negative tests prove omissions, stale hashes, tracked secret-like filenames and symlinks are refused. This improves release accounting; not production readiness. See `docs/COMPLETE_RELEASE_MANIFEST_INVENTORY_V0911.md`.

**v0.9.10 M16 visual and keyboard regression (107/107 isolated CI passed):** 16 real Chromium routes now receive targeted landmark/heading/form name/image alt checks, keyboard skip link, 320 CSS px reflow, print-media output, and no-JS server rendering. CI uploads eight candidate-site desktop/mobile screenshots for the founder to compare with their original static theme when its backup becomes available. **This is NOT WCAG certification or actual Hostinger theme parity.** See `docs/M16_BROWSER_A11Y_REFLOW_PRINT_V0910.md`.

**v0.9.9 M17 data integrity:** The old 14-day counter accepted incomplete `{date,provider}` rows. New `src/SourceCoverage.php` uses versioned, consistent live non-fixture observation records, verified UTC days, source-review dates, and India-eligibility sum checks. `scripts/coverage_report.php` and owner health share the strict validator; legacy/fabricated fixture rows don't count. **Even 14 local valid rows cannot independently prove actual licensed provider responses or Hostinger operations.** The 14-day *real* source/2–3 role and business evidence gate remains blocked. See `docs/M17_SOURCE_OBSERVATION_EVIDENCE_V099.md`.

**v0.9.8 real Apache security regression:** The public `.htaccess` now also denies commonly exposed ZIP/backup/key/CSV formats and mistaken private-source directories even if files were accidentally placed in `public_html`. GitHub tests real isolated Apache responses (403/200), `DirectoryIndex` priority, route rewriting and security headers. **This is not evidence of Hostinger's actual LiteSpeed/Apache or PHP-FPM configuration. Private code must still remain OUTSIDE public_html.** See `docs/APACHE_WEBROOT_DEFENSE_IN_DEPTH_V098.md`.

**v0.9.7 cutover protection:** `scripts/audit_hostinger_cutover.py` compares a local copy of existing `public_html` against the exact public deployment ZIP without writing anything. It detects the **critical `DirectoryIndex index.php` homepage switch** even when `index.html` remains, plus `.htaccess` and theme asset collisions. Negative-fixture tests enforce the safety boundary; Hostinger bundle includes `STOP_READ_FIRST_NO_LIVE_CUTOVER.txt`. **Do not extract the public ZIP over live `public_html` without actual backup/restore, private-root permissions, original theme and SEO approvals.** See `docs/HOSTINGER_ORIGINAL_HOMEPAGE_SWITCH_GUARD_V097.md`.

**v0.9.6 canonical reissue (CI verified):** [Main GitHub Actions run 45138735761](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45138735761) passed all five jobs, including 76/76 canonical checks, 16 archive acceptance checks, 141 checksummed application source files and downloadable `overseasjob-canonical-v134-validated` and `hostinger-preview-verified-bundle` artifacts (expires 7 Dec 2026). No production deployment or provider/legal approval is implied. `scripts/reissue_canonical_pack_v134.py` reads the EXACT checksum-verified v1.3.3 canonical ZIP, patches only the provably stale undocumented Himalayas per-job 410/404 status assumption, extends the validator, preserves M00–M17/T01–T10 and every other owner unchanged, and produces a new versioned v1.3.4 ZIP and integrity report after CI. No Hostinger deployment or provider activation. See `docs/CANONICAL_SOURCE_PACK_REISSUE_V134.md`.

**v0.9.5 packaging:** `scripts/build_hostinger_bundle.py` creates separate SHA-256-verified private and public preview ZIPs, tested by `tests/hostinger_bundle_tests.py`. After all main-branch test jobs pass, GitHub Actions attaches them as downloadable `hostinger-preview-verified-bundle`. **This does not touch the real Hostinger hosting, does not preserve unknown existing theme automatically, and is not permission to overwrite `public_html`.** See `docs/HOSTINGER_DIRECT_HOST_BUNDLE_V095.md`.

**Critical v0.9.4 source contract correction:** The official Himalayas OpenAPI was rechecked on 23 Nov 2026 and documents offset+limit, job-publication `pubDate`, opaque GUID and external employer application URLs, with no public per-job 410 API. `docs/OPENAPI_HIMALAYAS_CORRECTION_V094.md` supersedes prior v0.9.1 cursor/date claims; Himalayas remains disabled until source re-approval, live samples and the canonical v1.3.4 pack revision.

**v0.9.3 isolated-CI-verified code hardening:** bounded provider 429/5xx backoff, source-specific 200/410/404 status probe (Himalayas OFF until authorised), canonical source fields and stable route key, truthful bounded feed completeness, HMAC IP report throttling (requires private REPORT_RATE_SECRET), expanded local backups, honest health guard and complete M00–M17 evidence register. See `docs/PREDEPLOYMENT_CODE_HARDENING_V093.md`. [PR validation run 45083243298](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45083243298) passed 3/3 isolated jobs, including 38 real Chromium browser tests and 133 SHA-256 source checks. Production host, original live theme, provider rights, real email and external backups are still UNVERIFIED. **Do not upload or replace existing production public_html** on the strength of this code release.

**v0.9.2 browser QA:** Real Chromium + Playwright runs on isolated GitHub CI with feature gates OFF. [Browser job, 38/38 passed](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45075331503): desktop tracker, downloads, T07–T10, Daily Mission, privacy, plus seven 390px mobile routes and uncaught-JavaScript checks. Fixed a real interview checkbox / country form-change reset issue in `public/assets/advanced_tools_ui.mjs`. This is **not** production, Hostinger, original-theme parity or formal accessibility certification.

**Historical v0.9.1 provider patch — SUPERSEDED by v0.9.4 and canonical v1.3.4 (DO NOT DEPLOY nextCursor):** Himalayas browse now uses official opaque `nextCursor` instead of deprecated offset; a capped or incomplete traversal preserves last healthy cache; JSON `pubDate` maps to source-updated time, never an invented original publication date. See `docs/PROVIDER_CURSOR_AND_DATE_CORRECTION_v091.md`. Himalayas remains disabled by default; no actual live listing is claimed.  
**Canonical product Source of Truth:** The validated v1.3.4 versioned ZIP is reproducibly generated from the immutable v1.3.3 original (with corrected API-status owner contract); see `docs/CANONICAL_SOURCE_PACK_REISSUE_V134.md` and the latest Actions artifact.

**Verified CI evidence (23 Nov 2026):** [GitHub Actions run 45069896338](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45069896338) passed both the deterministic regression job (610 local checks) and an isolated **MySQL 8** integration job (37 assertions). The MySQL run used a disposable `oj_test` container and injected test-only email delivery; real Hostinger, real inbox delivery and public launch have NOT been verified.

**v0.9.1 isolated evidence:** [GitHub Actions run 45073631074](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45073631074) passed deterministic regression + disposable MySQL 8 jobs; 126 source files verified in SHA-256 manifest and canonical 71/71 spec checks passed. These are NOT live provider, Hostinger, mailbox, browser E2E or production release proofs.

## Implemented code

- Source-attributed job discovery, conservative India location + timezone eligibility, Job Decision Cards, atomic import/cache and safe empty-state handling. **There are currently no verified live jobs** in this repository.
- All **10 free career tools (T01–T10)**: salary/savings, offer comparison, resume/cover letters, relocation budget, DST-aware time-zone overlap, device-local application tracker, interview practice, country readiness checklist, self-reported portfolio and 30/60/90-day roadmap.
- **Version-aware Opportunity Radar** (M07) compares the locally seen provider listing fingerprint on the next visit. “New to your Radar” is NOT “newly published”, and details changes are NOT a verified employer update. Browser-only v2 import/export; previous v1 backups accepted.
- **Daily Career Mission** (M08) with 21 original exercises in 3 tracks, deterministic UTC rotation, per-track streak and browser-only backup/delete. Original drafts remain noindex until reviewed.
- **Opt-in privacy controls** (M09/M15), first-party anonymous aggregate counting (disabled by default), browser Global Privacy Control/Do Not Track respect, and “delete all OverseasJob device data”. No ads, candidate account, payments, candidate CV or passport database.
- **Owner moderation panel code** (M10) with HTTPS, password hash, session CSRF and login rate limit, reports/quarantine/reopen. **The panel defaults OFF** until real hosting secrets/HTTPS are verified. Existing trusted CLI moderation remains available.
- **Future employer/partner domain foundation** (M12–M14): strict validation, pending review, controlled apply domains, tenant scope, revision conflict, token fingerprint and idempotency-key validation. **No employer signup or partner API operates in this release**; database, identity and full API gates remain unimplemented.
- PHP CLI backups, isolated restore drills, source health checks, release-preflight gate, and GitHub Actions read-only CI workflow.

## Run (PHP 8.2+, Node 18+, curl, sh)

```sh
sh tests/run.sh
php scripts/launch_preflight.php   # 'ready': false and exit code 2 are EXPECTED
php -S 127.0.0.1:8000 -t public public/router.php
# Open http://127.0.0.1:8000/tools and /daily
```

All new code runs without paid AI or additional subscriptions. The deterministic local suite passes **440 checks**; the unchanged canonical specification passes **71/71 documentation checks**. This is NOT a production security certification. The 38 actual Chromium browser checks passed on GitHub's isolated local preview; live hosting, production theme parity, assistive technology and real user testing remain unverified. GitHub Actions success remains to be checked after pushing.

**Zero-trust defaults:** `RELEASE_READY=0`, `ANALYTICS_ENABLED=0`, `ADS_ENABLED=0`, `OWNER_PANEL_ENABLED=0`, `RECRUITER_LITE_ENABLED=0`, `PARTNER_POSTING_API_ENABLED=0`. See `.env.example`. Do not place the project root or secrets inside `public_html`.

**Unresolved external gates:** live Hostinger PHP/SSL/cron/database/storage/backup verification, licensed live Jobicy/Himalayas APIs and 14-day coverage, five reviewed original guides, independent privacy/legal review, human recruiter verification and operational moderation, real browser/accessibility/print/mobile tests, and staged disaster recovery. Public launch stays blocked.

See `START_HERE_FOUNDER.md`, `docs/GATE3_AND_FUTURE_CONTRACTS_v060.md`, `module_status.json`, `deploy/shared_host/DEPLOY_STAGING_README.md` and the bundled v1.3.3 authority register.

## v0.7.0 — inbound partner pilot implementation (GATED)

The invite-only API application layer, isolated MySQL pilot repository and schema, token issuance/revocation CLI, strict scopes and ownership, idempotency, optimistic revisions, rate limits and pending-review lifecycle have been implemented **without enabling the public API**. This is *not* a finished employer onboarding/dashboard or a production-tested database feature.

Run `php tests/partner_api_tests.php`, `sh tests/http_partner_pilot_smoke.sh`, `php scripts/partner_preflight.php` (expected `pilot_http_configured: false`, exit 2). See `docs/PARTNER_PILOT_STAGING_v070.md` for real hosting blockers. Existing career tools and third-party provider jobs are unchanged. The canonical v1.3.3 specifications are unchanged.

## v0.8.0 — employer identity, human review and invite-only dashboard (STAGING CODE; DEFAULT OFF)

`src/EmployerReview.php` and SQL migration `002` add revision-specific owner publication decisions, audit trail, expiry and first-party-only search projection. `src/RecruiterPortal.php` and migration `003` add invite-only emailed one-time challenge, verified member login with rate-limits, organisation-scoped dashboard, create/edit/close forms. `src/EmployerDomainProof.php` and migration `004` add a controlled DNS TXT ownership check, **not automatic company approval**. Third-party feeds are never imported into employer records or APIs. All these switches are default OFF; even a `published` database status cannot appear publicly without current revision-specific human audit and global launch readiness.

Read **`docs/EMPLOYER_VERIFICATION_AND_MODERATION_v080.md`** before any hosting changes. `php tests/mysql_integration_tests.php` requires `OJ_MYSQL_INTEGRATION=1` and a disposable `oj_test` database, and is intentionally not run by the ordinary local suite. The new GitHub Actions job exercises a disposable MySQL 8 container (check the **actual workflow result** before claiming success). Local environment has no MySQL PDO. Email delivery, real employer identity, Hostinger staging, browser E2E and public launch are not verified.

## v0.9.0 — guarded recruiter recovery, theme-safe staging audit, and operations

`database/005_recruiter_password_recovery.mysql.sql`, `src/RecruiterMailer.php` and the gated `/employers/reset` flow introduce one-time email-code password recovery, per-mailbox/IP rate limits, replay protection, and a credential `auth_version` that invalidates prior sessions. No live email delivery or Hostinger MySQL migration has been performed; all employer switches remain OFF.

The read-only `php scripts/theme_collision_audit.php /path/to/current-static-backup public` explicitly reports HTML/PHP entrypoint and CSS collisions and **never approves overwriting the current overseasjob.in theme**. We have no verified current static-theme export yet. `php scripts/health.php` now fails honestly on missing/corrupt caches and reports only aggregate operational facts. See `docs/RELEASE_V090_SECURITY_AND_THEME_HANDOFF.md`.

Run `sh tests/run.sh` and `php scripts/verify_release_manifest.php` to check source code and SHA-256 release packaging. GitHub CI also executes a disposable MySQL integration harness; see the **actual latest Actions run** before claiming the new reset path passed in MySQL. Source of Truth remains **v1.3.3**, with unchanged feature scope.

**v0.9.0 verified CI:** [GitHub Actions run 45072238748](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45072238748) passed both the deterministic checks (652 local checks including the manifest) and **49 disposable MySQL 8 integration assertions** for the new reset + prior employer workflows. This does not verify actual outbound email receipt or Hostinger.
