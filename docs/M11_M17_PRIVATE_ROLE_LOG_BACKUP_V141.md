# M11 / M17 — Private role-cohort observation recovery

**2026-11-24. Local application backups ONLY. Not a Hostinger backup/restore certificate.**

## Gap
After M17 introduced `storage/role_coverage.ndjson`, M11's hardcoded `Backups::SOURCES` still included only the older `coverage.ndjson`. A local restore could recover most observations but silently drop the required 2–3-cohort / 14-day evidence log.

## Correction
- Include only `role_coverage.ndjson` in private source whitelist. The existing non-symlink safeguards, per-file SHA-256, `0700` backup directory, `0600` data/metadata files, two-generation retention, and isolated restore verification remain enforced.
- `tests/backup_tests.php` uses a **clearly labelled synthetic test-only** row. It proves a role log is copied with the correct checksum, isolated restoration accepts it, and a symlinked original role log fails closed.
- Never fabricate historical observations. **No** local backup is independent evidence of the provider's actual API responses or a completed real 14-day sampling window.
- The allowlist deliberately excludes sensitive `storage/provider_rights/*.json`, `storage/release_authorization.json` and database credentials; operator approval must be revalidated after recovery.

## Still blocked
The actual Hostinger account inventory, original `public_html` theme/SEO backup, independently restorable MySQL database, offsite encrypted backup policy, real job-provider permissions and public release are **NOT verified** here. Run only on a copy of private application data; this does not authorise a production cutover. Source, recruiter, partner and ads runtime switches remain OFF.
