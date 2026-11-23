# Hostinger existing production hosting — split preview packaging (v0.9.5)

**Status: release artifact preparation only. NOT a public launch approval, and never an automatic production deployment.**

## Why this is necessary

The founder's real Hostinger File Manager screenshots show the current website under `public_html/` with `assets/`, `images/` and `index.html` (541 bytes). The parent also shows `DO_NOT_UPLOAD_HERE`. None of these existing files may be assumed disposable, and the screenshot is not enough to establish an actual Unix home directory or safe sibling PHP path. The founder prefers testing on the existing production hosting rather than creating a second staging subscription/domain.

**This packaging does NOT remove existing `index.html`, overwrite current `assets/`, create an `overseasjob_private/` folder on the real host, change SSL/DNS, or upload anything.**

## Safely generated artifacts (from a successful main CI)

A successful `main` GitHub Actions run generates `hostinger-preview-verified-bundle` **only after deterministic, disposable MySQL and actual Chromium browser checks all pass**. It contains:

- `overseasjob_private_verified.zip`: `overseasjob_private/` root containing the complete SHA-256-verified private project source and `RELEASE_MANIFEST.json`. Never unpack this archive inside public webroot.
- `overseasjob_public_preview_NOT_FOR_BLIND_OVERWRITE.zip`: `public_html/` root containing **only** `index.php` (the shared-host bootstrap), `.htaccess` and `assets/`. No `index.html`, private `src/`, `storage/`, API keys or database.
- `bundle_integrity_report.json`: deterministic SHA-256 values and file counts; explicitly `production_cutover_approved=false`.

Running `python3 scripts/build_hostinger_bundle.py --output artifacts/hostinger-preview` reproduces both ZIPs locally from `RELEASE_MANIFEST.json`. The Python standard library is enough to build them. `python3 tests/hostinger_bundle_tests.py` verifies the archive contents, private/public isolation, extracted PHP bootstrap route, noindex preview and reproducibility.

## Safe direct-host procedure (not an instruction to overwrite)

1. Back up the existing **entire** `public_html/`, its `index.html`, `assets/`, `images/`, existing `.htaccess` if present, and any hosting databases. Validate recovery of the actual backup before cutover. Hostinger full-backup availability and file permissions must be verified.
2. Check official Hostinger hPanel evidence for PHP 8.2+, writable private storage, `open_basedir`, Apache rewrite support, outbound HTTPS from CLI, current DNS/SSL and cron. The screenshot with `DO_NOT_UPLOAD_HERE` is a warning to verify permissions, not consent to upload outside `public_html`.
3. In a permitted location outside the document root, confirm that `../overseasjob_private` is readable by the site's web PHP runtime. If the parent folder cannot host a private sibling, STOP rather than relocating `src/` into `public_html/`.
4. Compare the existing website HTML/CSS/images against the new PHP UI. The two frontends are **not guaranteed visually identical**. Obtain the founder's approval for a visual switch if needed; preserve the actual current theme and rollback.
5. Review privacy, approved source rights and the launch/evidence flags. `RELEASE_READY`, ads, analytics, recruiter and Partner API remain OFF; candidate-facing pages are preview-only `noindex` until actual release evidence meets `src/Launch.php`.
6. Only after the real host path, actual backup/restore, original-theme preservation and approved cutover conditions are confirmed should an authorised operator manually unpack the archives into their correct **respective** destinations. Unpacking a `public_html/`-prefixed ZIP from *inside* the existing `public_html` folder would create the wrong nested path. Never overwrite existing files by guessing.
7. Run `php scripts/gate0_preflight.php` on the actual CLI, `sh scripts/staging_probe.sh https://overseasjob.in` on an authorised preview (note this probes public URLs), manual keyboard/mobile QA, sensitive-file URL denial checks, health and backup-restore drills. A site that is already indexed should **not** be switched to noindex without acknowledging possible SEO impact.

## What these packages do NOT resolve

They do **not** verify Hostinger runtime, original-theme matching, real provider permissions/14-day job inventory, 5-guide editorial/legal approval, recruiter identity, paid advertising approvals, off-site encrypted backup/production restore, screen-reader accessibility, or canonical v1.3.4 provider-contract ZIP reissue. No claim is made that a downloaded preview bundle can be safely deployed without those gates.

The source link to verified packaging tests will be included in a successful `main` workflow run after the PR has been merged. GitHub Actions artifacts have limited retention; download a fresh artifact if needed.
