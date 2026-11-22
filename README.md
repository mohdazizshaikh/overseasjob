# OverseasJob.in — Code Development v0.6.0

**State: working local application and future-gated contract foundation; NOT deployed, NOT public-launch ready.**  
**Canonical product Source of Truth:** `SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.3.3_Source_of_Truth_Project_Pack.zip` (unchanged business requirements).

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

All new code runs without paid AI or additional subscriptions. The deterministic local suite passes **440 checks**; the unchanged canonical specification passes **71/71 documentation checks**. This is NOT a production security certification. The E2E browser script exists but its Chromium runs were blocked by the sandbox administrator policy; do NOT call it passed. GitHub Actions success remains to be checked after pushing.

**Zero-trust defaults:** `RELEASE_READY=0`, `ANALYTICS_ENABLED=0`, `ADS_ENABLED=0`, `OWNER_PANEL_ENABLED=0`, `RECRUITER_LITE_ENABLED=0`, `PARTNER_POSTING_API_ENABLED=0`. See `.env.example`. Do not place the project root or secrets inside `public_html`.

**Unresolved external gates:** live Hostinger PHP/SSL/cron/database/storage/backup verification, licensed live Jobicy/Himalayas APIs and 14-day coverage, five reviewed original guides, independent privacy/legal review, human recruiter verification and operational moderation, real browser/accessibility/print/mobile tests, and staged disaster recovery. Public launch stays blocked.

See `START_HERE_FOUNDER.md`, `docs/GATE3_AND_FUTURE_CONTRACTS_v060.md`, `module_status.json`, `deploy/shared_host/DEPLOY_STAGING_README.md` and the bundled v1.3.3 authority register.
