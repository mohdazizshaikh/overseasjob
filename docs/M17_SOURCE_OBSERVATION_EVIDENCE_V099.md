# v0.9.9 — M17 Source observation evidence integrity (2026-11-23)

**Code status:** implementation candidate until isolated GitHub CI has passed. The real platform has **zero independently verified 14-day source observations** in this repository. This change does not start ingestion or enable a provider. It does not make genuine inventory exist.

## Source-of-Truth requirement

Canonical `15_CANONICAL_MODULE_REQUIREMENTS.md` defines M17: **14 actual observed days of job supply and weekly business feasibility**, not arbitrary file rows or invented job counts. `04_QA_AND_LAUNCH_CHECKLIST.md` requires an actual permitted-source 14-day sample for 2–3 roles with India-eligible/ineligible/unknown shown separately. `16_DATA_AND_OPERATIONS_CONTRACT.md` distinguishes source provenance and truthful freshness. These are **not** satisfied by code alone.

## Finding and corrective implementation

Before this fix, `scripts/coverage_report.php` and `src/OpsSnapshot.php` incremented actual-sample days from records with only `{"date":"YYYY-MM-DD","provider":"jobicy"}`. A record did not need a successful live sync, real job count, source-policy review, country classification or timestamp; a forged/legacy log could produce a misleading 14/14.

New `src/SourceCoverage.php` centralizes a versioned v2 observation generated **only after a live (non-fixture) cache is atomically published** by `scripts/sync_jobs.php`. The observation contains an exact UTC `at` and `date`, source ID, non-fixture origin, random per-run ID, `cache_published=true`, current source review date, nonzero valid job count, invalid count, eligibility allowed/unknown/not_allowed counts summing exactly to the valid rows, and a truthful `partial_provider_sample` flag.

Both `php scripts/coverage_report.php` and `OpsSnapshot::collect` use the **same strict validator**. A 14-day window only counts distinct eligible UTC dates containing valid v2 live-sync-shaped records; a provider/day contributes the latest observation, preventing repeated fetches from inflating the daily totals. Bare legacy or fixture rows are not silently upgraded. The report explicitly separates:

- `14_distinct_calendar_days_measured`: **local observation log** contract met, NOT verified third-party response truth.
- `live_upstream_source_independently_verified=false` and `hostinger_14_day_run_verified=false`: independent source-rights and real Hostinger timeline still need human/operator evidence.
- `unverified_legacy_or_invalid_records`: records excluded from the count.
- `observed_india_eligibility_rows`: rows in one latest sample per provider/day; repeat appearances are **not unique vacancies or traffic**.
- `partial_provider_day_samples`: samples from incomplete/bounded feeds (Jobicy remains a recent sample), never all-market inventory.

An invalid or truncated source coverage log fails closed and cannot claim the 14-day sample completed. Missing log files report **zero** without fabricated retrospective observations.

## What still needs actual external evidence

1. Written provider terms/rights checked again before any real sync; Himalayas stays OFF until expressly reviewed and live OpenAPI data observed.
2. A real hosting/cron observation for each day, not a synthetic 14-day test fixture.
3. **2–3 relevant job role sample categories** with independent India-allowed/not-allowed/unknown classification and first-hand source URLs, not invented from all-provider totals.
4. Actual source inventory, expiration/closure and false-positive review; dated weekly funnel/traffic and returning user metrics, not revenue claims.
5. A separate actual Hostinger backup/restore and operator signoff if the user elects same-production-host testing.
6. M17 module status remains `blocked_with_reason` and public launch stays OFF.

**No new paid services, no change to `RELEASE_READY`, no live network fetches and no change to existing Hostinger `public_html`.**
