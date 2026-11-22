# RC v0.3.0 implementation and specification alignment

**Reference:** Source of Truth v1.3.3. Status: tested local implementation, NOT deployed. Older `BETA_*` docs are historical; this changelog and `README.md` are current local implementation guidance.

## Material code changes

- `src/Countries.php`, `country_aliases.json`: 249 ISO country codes plus recognised names/aliases; unknown and conflicted provider country object safely resolve to `unknown` rather than India allowed/denied. `src/Jobs.php` preserves source fields on Decision Cards and requires Himalayas provider-host Apply links.
- `public/assets/tracker.mjs`, `tracker_ui.mjs`, `/tools/application-tracker`: full manual T06 browser-local application workflow with JSON/CSV backup, CSV formula injection guard, `.ics` reminder; integration with source-job Save action. No backend candidate records. `My Career > Delete all local data` clears both profile and tracker stores.
- `content/guides.json`, `src/Guides.php`, five `/guides/{slug}` pages: original guide drafts with separate structured sources and editorial status. Draft pages remain noindex.
- `src/Reports.php`, `scripts/review_reports.php`: bounded private queue, trusted CLI moderation, persistent quarantine separate from refreshed provider cache, decision audit and explicit reopen. No public moderation endpoint.
- `src/Launch.php`, `config/release_evidence.json`, `scripts/launch_preflight.php`: evidence-led release/SEO index readiness gate; env variable alone is insufficient.
- Extended `scripts/health.php`, `scripts/backup.php` to report/protect moderation state.

## Reproducible test evidence

`./tests/run.sh`: 54 PHP + 21 operational/guide + 16 original JS + 24 tracker JS + 25 HTTP + 2 meta checks = **142 passed**.
`Source of Truth v1.3.3/23_VALIDATE_SPEC.py`: **71/71** document contract checks.
Chromium local browser navigation: **FAILED due to environment network block**, not counted as passed.

## Open blockers

No live job API run; no verified Hostinger staging; no full SQL migration; no independent editorial/legal review; no production security/restore drills; not authorised to flip public release. All provider jobs in `storage/jobs.json` remain empty. No automatic visa sponsorship or revenue claims.
