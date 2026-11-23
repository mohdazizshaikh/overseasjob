# OverseasJob.in v0.9.14 — Founder handoff (23 November 2026)

**Current position: code development ongoing; PUBLIC DEPLOYMENT IS NOT APPROVED.**

**Backup safety:** v0.9.14 makes the project's local private-file copy and isolated restore rehearsal refuse unsafe filesystem links and checksum metadata. This is extra code protection—not Hostinger's complete backup of your original website. Before any production-as-test deployment, download and independently restore-check original `public_html` and any database. See `docs/M11_LOCAL_BACKUP_SYMLINK_DEFENSE_V0914.md`.

**No-developer backup comparison preparation:** You can download an original-site ZIP of the existing Hostinger `public_html` and keep it private. `scripts/audit_hostinger_cutover.py` now accepts the ZIP directly, compares it with the candidate public ZIP, warns if screenshot's `assets/` or `images/` files are missing, and will never upload/extract/change anything or approve deployment. Keep original site, backups and DB untouched. This does not verify recoverability or the actual host. See `docs/HOSTINGER_ORIGINAL_HOMEPAGE_SWITCH_GUARD_V097.md`.

**Important live job-feed safety:** v0.9.12 blocks real Jobicy/Himalayas fetches by default, even if the configuration says the provider is enabled. An authorised operator must separately review the provider's current terms, record private evidence and explicitly activate both host and provider flags. This protects against an accidentally scheduled live cron before legal review; no real provider approval has been received. Do not enable the flags just to make tests pass. See `docs/LIVE_PROVIDER_SOURCE_PERMISSION_GATE_V0912.md`.

**Code-release accuracy improvement:** Earlier SHA-256 checks could miss an entirely unlisted new file. v0.9.11 adds automatic Git-tracked inventory comparison and safe manifest regeneration, with negative tests. It does not change your existing Hostinger files, create subscriptions or enable job feeds. See `docs/COMPLETE_RELEASE_MANIFEST_INVENTORY_V0911.md`.

**New screenshot comparison preparation:** Code now generates desktop/mobile pictures of the **new candidate site only** on GitHub CI, alongside keyboard, form label, 320px screen, print and no-JS tests. Your original live static theme has NOT been copied into the repository and remains untouched. These screenshots are for comparison later, not a recommendation to overwrite the homepage. See `docs/M16_BROWSER_A11Y_REFLOW_PRINT_V0910.md`.

**14-day source evidence guard:** The old metric could count rows containing only a date and provider name. Version 0.9.9 accepts only complete non-fixture live-sync records with matching UTC timestamp, source review, nonzero valid jobs, and India-allowed/unknown/restricted totals. The report cannot produce real 14 days without actual permitted daily source fetching on an authorised host. Use `php scripts/coverage_report.php` after real configured syncs, and read `docs/M17_SOURCE_OBSERVATION_EVIDENCE_V099.md`. Local tests never create genuine production history.

**Extra public webroot protection in v0.9.8:** Apache-compatible denial rules now also reject accidental uploads of backup ZIPs, database dumps, secret key files, and internal PHP source paths. They are tested only on a disposable GitHub Apache service. Real Hostinger settings, complete original-site backup/restore, `DO_NOT_UPLOAD_HERE` semantics and theme compatibility remain unverified. Never place the whole source ZIP into `public_html`.

**Production file safety:** Existing Hostinger `public_html/index.html` remaining intact does not preserve the homepage when the new `.htaccess` sets `DirectoryIndex index.php`. The candidate public ZIP can switch the homepage and replace same-path theme assets. v0.9.7 ships a read-only original-backup-versus-candidate audit and a `STOP_READ_FIRST_NO_LIVE_CUTOVER.txt` warning; this is NOT proof of actual host backup or permission. See `docs/HOSTINGER_ORIGINAL_HOMEPAGE_SWITCH_GUARD_V097.md`. The existing overseasjob.in theme and website are untouched. User prefers that current theme; it will be preserved and separately compared, not overwritten by this project's newer CSS.

**New engineering evidence:** [GitHub Chromium job 45075331503](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45075331503) executed 38 browser checks on a disposable local preview, including seven mobile 390px layouts. The newly identified interview checkbox reset was fixed; real hosting, user devices and accessibility audit are still pending.

## Implemented and tested locally

- Core search/Job Decision Cards, all ten free tools, Career Radar, Daily Mission, privacy controls, moderation and owner panel code from earlier releases are preserved.
- Invite-only employer/partner API, DNS proof, first-party publication approval, recruiter dashboard and MySQL schema from v0.8.0 are preserved behind default-OFF feature gates.
- New **recruiter forgot-password flow**: time-limited one-time mailbox code, bounded attempts, password hashing and forced invalidation of previous browser sessions. Email configuration is required and actual Hostinger inbox delivery is **not** verified.
- New **theme collision audit** is strictly read-only, detects static HTML and PHP/CSS collisions on a **local backup**, and intentionally cannot approve any replacement of live website files. Current static site files/screenshots have not been supplied for visual parity checking.
- New **operational snapshot** gives owner-safe counts and honest missing-cache/backup warnings without leaking report contents.
- Full source ZIP manifest with SHA-256 verification. Unit and HTTP checks run in the local toolchain; the new disposable MySQL checks passed 49/49 in [GitHub Actions run 45072238748](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45072238748).

## Required real-world gates

The current website's original static HTML/CSS and visual screenshots, Hostinger plan and private staging environment, actual emailed confirmation, MySQL migration/rollback on staging, live Jobicy/Himalayas providers, 14 observed days of job inventory, on-Hostinger browser E2E, assistive-technology accessibility audit, editorial/legal approvals and production backup restore are **not independently verified**. The release preflight stays `ready:false`. Real employers and their application data must not be activated until these checks pass.

**Next safe handoff:** obtain a recoverable complete copy of the actual current `public_html` and any database, verify Hostinger private PHP folder and rollback, then run the read-only ZIP-vs-original comparison before any authorised same-production-host trial. No separate paid staging site is required merely to conduct read-only backup comparisons, but the public site must not be blindly replaced. No need to change current production site now.

GitHub repository: https://github.com/mohdazizshaikh/overseasjob

**Test evidence:** 652/652 deterministic local checks; separate 49/49 disposable MySQL CI assertions; canonical specification 71/71 documentation checks. 38 real Chromium preview checks passed on GitHub, but no live-domain theme comparison or Hostinger staging evidence.
