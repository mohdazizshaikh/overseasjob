# M17: 14-day role-cohort source feasibility evidence — schema v1

**State:** GitHub code/fixture contract only until actual permitted provider calls have run across **14 distinct recent UTC calendar days**. No real source permission, Hostinger continuity, inventory, copyright clearance or public launch is verified here. Preserve `SOURCE_LIVE_SYNC_ENABLED=0` and both provider-rights flags OFF until actual permission is reviewed.

## What the founder v1.4.1 blueprint requires

The canonical `10_DECISION_COMPANION_PRODUCT_SPEC.md` §9 requires real permitted 14-day source sampling for 2–3 priority roles with per-source/day fetched rows, fully paginated versus bounded-feed status, unique/duplicate counts, India allowed / not allowed / unknown, role-relevant rows, source-date expiry, safe Apply URLs, errors and current terms. Do not invent retrospectively observed dates, qualified job counts or a magic positive-yield threshold. If supply is weak, put career tools and decision support ahead of claims of daily India-qualified matches.

The existing `storage/coverage.ndjson` v2 log recorded only provider/day totals. That alone could not substantiate role-specific supply. The new `src/RoleCoverage.php` and `scripts/role_coverage_report.php` add **independent role-specific observation metadata**.

## Role definitions / accounting (starter pilot, not guaranteed demand)

Three initial configurable-in-next-version *fixed code* cohorts match case-insensitive listing titles using the following terms:

- `it_support`: IT support, help desk/helpdesk, service desk, technical support.
- `customer_support`: customer support, customer service, customer care, client support.
- `junior_web_developer`: junior web developer, junior frontend/front end/front-end, web developer.

A listing can match multiple cohorts, so **sum of cohort rows is not a global unique-job count**. Title matching is a heuristic; it does not prove entry level, citizenship, licence or employer eligibility. All three cohorts get an explicit sample record each successful live sync **even if zero roles match**; reporting zero is useful evidence, not failure. The founder may revise these priority roles with a versioned config + fresh start; never retroactively relabel stored observations.

The `role_coverage.ndjson` record schema includes `schema_version=1`, `origin=authorised_live_provider_sync`, `from_fixture=false`, `cache_published=true`, unique random `run_id`, exact UTC timestamp and day, provider and source-policy review date, `fetched_total`, `unique_valid`, `invalid_rows`, `duplicate_rows`, `raw_apply_url_failures`, `partial_provider_sample`, `fully_paginated`, and each cohort's `relevant`, `india_allowed`, `india_unknown`, `india_not_allowed`, `safe_apply_https`, and `expired_by_source_date` counts. The row's fetched total must reconcile with unique + invalid + duplicate. The source policy must have passed the independent private current-rights boundary BEFORE any live HTTP. Jobicy's bounded latest feed is always marked **partial/unpaginated**; completed Himalayas offset/limit browsing is marked **fully paginated** only when all pages succeeded. A provider disappearing from a bounded feed is *not* evidence of closure.

## Collection and report

- `scripts/sync_jobs.php` appends a role row only after a successful permission-gated **live** feed sync and atomic private cache publish. `--fixture=` never writes this real-observation log, and the script never backdates dates.
- Run `php scripts/role_coverage_report.php` on a trusted private CLI. The report returns distinct recent UTC dates, cohort breakdowns, provider coverage, total/duplicate/invalid-apply counts and the `14_days_for_2_to_3_role_cohorts_shape_valid` boolean. An invalid/truncated/symlink log fails closed. Only one latest observation per provider per UTC day contributes to aggregate totals.
- The **public** release gate checks this strict role-cohort shape **in addition to** the existing aggregate coverage, current provider attestation, reviewed human evidence and owner approval. Passing a local log-format check does *not* independently verify API authenticity, commercial rights, Hostinger uptime, source completeness or the suitability of counts. Real source screenshots/terms, source errors, unresolved legitimacy, cohort role relevance and a human go/no-go judgment belong in the private evidence register.
- No raw applicant data, CVs, emails or full job descriptions are stored in this aggregate log. Keep the `.ndjson` under private `storage/` and include it in approved retention/backup policy only after actual hosting review.

**Important:** No programmatic positive-yield threshold is set. The founder's go/no-go decision must use the actual observed counts, separate India eligibility states and product-positioning tradeoffs. Historical fabricated rows are never acceptable evidence.
