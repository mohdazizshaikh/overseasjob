# Hostinger same-account, controlled preview handoff — NOT DEPLOYED

**Read this before uploading anything:** The hosting account, site type, PHP restrictions, cron availability, TLS, backup/restore access and supported document-root settings have NOT been verified. This is a **conditional, reversible layout**, not a guaranteed Hostinger configuration. No DNS or production domain should be changed without confirming ownership and existing-site contents. Legacy GitHub `overseas-platform` is an unrelated paid-SaaS architecture; DO NOT overwrite it.

## Release and theme safety — read first

The original Hostinger `public_html` was shown to contain `index.html`, `assets/` and `images/`. The generated public preview ZIP intentionally does NOT delete `index.html`, but its `.htaccess DirectoryIndex index.php` can still immediately **replace the visible homepage**, and same-path assets can overwrite the original theme. This is a code/host compatibility warning, not evidence of a completed migration.

The outer downloadable artifact includes `STOP_READ_FIRST_NO_LIVE_CUTOVER.txt`; neither ZIP is authorised for blind extraction into existing `public_html`. The private ZIP must remain outside every served webroot; sibling folder/open_basedir support is unverified and the visible `DO_NOT_UPLOAD_HERE` marker is NOT a valid filesystem path grant. New read-only downloaded-ZIP comparisons are documented in `docs/HOSTINGER_ORIGINAL_HOMEPAGE_SWITCH_GUARD_V097.md`.

**Canonical authority:** the v1.3.4 Source-of-Truth corrects the undocumented Himalayas per-job 410 claim, while retaining the verified offset+limit+totalCount and publication-time pubDate. The old v0.9.1 nextCursor note is historical and must never be used as deployment guidance.

## Preferred option: custom document root

If the actual hosting plan permits the domain/subdomain document root to point to `project/public/`, upload the whole application outside all other sites, set the document root to that `public/` directory only, and serve PHP with Apache rewrite. Files under `src/`, `config/`, `storage/`, `scripts/`, `content/`, `tests/`, `deploy/` must NEVER be web-accessible.

## Alternative: standard `public_html` document root

**Only after inspecting the actual hPanel path and Apache/PHP `open_basedir` permissions:**

```
<authorised domain-home>/
├── overseasjob_private/            # outside the public webroot
│   ├── src/ config/ content/ scripts/ storage/
│   ├── public/index.php            # real controller, no assets required here
│   └── ...
└── public_html/                  # the ONLY publicly served directory
    ├── index.php                  # generated from deploy/shared_host/index.php.template
    ├── .htaccess                  # copy from public/.htaccess
    └── assets/                   # copy from public/assets/*
```

Keep `.env`, credentials, backups and country source research OUTSIDE `public_html`. Copy the supplied `index.php.template` to `public_html/index.php` after verifying sibling path; this wrapper locates the private directory and calls the canonical PHP controller. Do NOT upload `src/`, `scripts/`, `storage/`, or other private application files to `public_html`. Shared-host `open_basedir` may prevent a sibling private folder; if so, **stop** and reassess the plan instead of putting private files in public root.

## Controlled same-account testing procedure

1. Founder preference is to use the existing production Hostinger account as the **testing environment**, without a separate paid staging site. This does NOT authorise overwriting the existing active `public_html`. First inspect existing domain/site, download current original `public_html` and any database backups, verify an actual restore/rollback independently, and retain old homepage/theme. No DNS or existing root modification before explicit owner cutover approval.
2. Verify PHP 8.2+, JSON, OpenSSL, file permissions, CLI PHP and a working schedule mechanism. Do not assume Docker, Redis, FastAPI, persistent Node or extra billing.
3. Begin only with non-public/private-root and read-only backup comparisons; do **not** extract the preview ZIP over current `public_html`. Use `python3 scripts/audit_hostinger_cutover.py --current-site-zip ORIGINAL_PUBLIC_HTML.zip --candidate-public-zip CANDIDATE_PUBLIC.zip` offline to detect homepage/asset collisions. This report always blocks cutover. Only a separately authorised, recoverable same-host preview may later be considered with `RELEASE_READY=0`; `/robots.txt` should disallow indexing and `/sitemap.xml` return 404, but changing active-domain noindex could disrupt live SEO.
4. If an authorised same-host preview URL exists and files have been safely deployed with owner approval, run `scripts/staging_probe.sh https://<authorised-test-url>` and test private paths, rewrite, SSL, secure headers and noindex. Do not assume current public domain or Hostinger path mapping equals this preview; no authorised URL or live host path has been verified.
5. Test actual PHP 8.2+, CLI/open_basedir and private `overseasjob_private/storage` permissions before any scheduled sync. **Never** execute a LIVE `php scripts/sync_jobs.php jobicy` merely because source policy is enabled: v0.9.12 requires **all** of `SOURCE_LIVE_SYNC_ENABLED=1`, provider-specific reviewed flag and a matching PRIVATE owner source-rights attestation after real legal/terms review. The safe defaults are OFF even if `config/provider_policy.json` has Jobicy `enabled=true`; a `.env.example` file does NOT automatically configure PHP CLI runtime. No real permission review or source rights evidence has been received. Himalayas remains policy-OFF.
6. Record 14 distinct **real** calendar days of source coverage before making daily-match marketing claims. Never manufacture sample counts.
7. Backups in `storage/backups` must be protected and encrypted/access-controlled by the verified hosting environment; `php scripts/backup.php` + `php scripts/restore_drill.php` provide **local file-hash rehearsal only**, not proof of host-level encryption or database recovery.
8. Conduct browser E2E and accessibility/print/export tests on staging; verify original guides, privacy/consent/legal, scam review operation, and the release checklist. Do not set release-evidence flags based only on scripts passing.
9. Keep ads, employer posting, partner API, real data sourcing and candidate accounts OFF until their external gates pass. Preserve a tested restore of the **original public site** before any owner-approved root cutover; local PHP backup rehearsal and a downloaded ZIP are insufficient.

## Nondeveloper founder evidence

Agent must give: **the actual authorised test URL if one exists**, Hostinger plan limits, redacted on-host preflight evidence, provider sync logs only after verified source rights, real sample jobs and apply targets when they exist, browser/security tests, protected-path checks, **original live-site and DB recovery evidence**, cost impact (`₹0 additional recurring` target), launch blockers and a tested rollback plan. The founder has not approved a separate paid staging purchase. There is no confirmed preview URL, verified backup restore or public deployment in this package.
