# v0.9.12 — Live provider activation: explicit, default-OFF, evidence-recorded operator boundary

**State (verified 23 November 2026):** [Main GitHub Actions run 45195745006](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45195745006) passed 6/6 isolated jobs, including 21 source-gate negative checks and an actual default-denied cron invocation without real provider HTTP. NO real source permission, live upstream responses, actual inventory, Hostinger deployment, or launch approval has been independently verified.

## The gap

Before this change, `scripts/sync_jobs.php jobicy` could start an actual upstream HTTP fetch merely because `config/provider_policy.json` set `"enabled":true` and held a recent `checked_at` string. This static configuration is not proof that an owner reviewed current source rights or intended to enable network crawling on production. The previously disabled Himalayas flag stayed off, but Jobicy remained vulnerable to an accidental cron invocation.

## What now blocks accidental live fetching

`src/SourcePermissionGate.php` is invoked by `scripts/sync_jobs.php` **before any outbound provider HTTP request**. For an actual non-fixture job-source sync it requires **all** of the following:

1. `SOURCE_LIVE_SYNC_ENABLED=1` in the **real hosting PHP CLI environment**. Default is `0` in `.env.example`.
2. The specific provider operator-review setting must be `1`: `SOURCE_JOBICY_RIGHTS_REVIEWED` or `SOURCE_HIMALAYAS_RIGHTS_REVIEWED`. Both default to `0`.
3. The existing source policy must have `enabled=true`, `canonical_source_required=true`, `allow_resyndication=false` and `allow_jobposting_schema=false`. The provider's official `https://...` terms reference and `checked_at` calendar date must be valid and no older than 30 days.
4. A **private** file at `storage/provider_rights/<provider>.json` with a matching provider, current policy date and provider terms URL; an explicit owner role, link-only/no-resyndication scope, operator approval and a substantial reason note. The evidence must be a regular file, not a symlink, and within 4 KB. This file is intentionally NOT committed to Git or packaged into public assets.

A _synthetic example of the expected schema only_:

```json
{
  "schema_version": 1,
  "provider": "jobicy",
  "reviewed_at": "YYYY-MM-DD",
  "terms_url": "https://jobicy.com/jobs-rss-feed",
  "reviewer_role": "owner",
  "scope": "source_linked_listing_only",
  "approved_for_single_source_display": false,
  "review_notes": "DRAFT ONLY: obtain an actual current legal/platform usage review and permitted-source attribution before considering approval."
}
```

**Do not copy this example as permission.** It is deliberately unapproved and its date placeholder invalid. We are not asserting that Jobicy, Himalayas or an employer has agreed to commercial redistribution, caching, indexing or any API use. The human/operator must independently inspect current provider terms, origin, conditions, rate limits and appropriate links; formal permission must be obtained if needed. A passing local `SourcePermissionGate` is only proof of a deliberate machine-local attestation; it is **not independent verification of contractual rights**.

For test fixtures, `--fixture=...` remains usable and does **not** send HTTP or change real public `jobs.json`. Tests exercise default-disabled flags, absent approvals, mismatched review metadata, unsupported providers, prohibited reuse, malformed/symlink records and expiry, without actually contacting providers or claiming live data. The entire release remains in preview/noindex, ads, employer/partner and live provider gates OFF.

## Founder and hosting handoff

Do **not** set these variables to 1 or create an "approved" JSON file merely to make a cron command pass. The operator must first review real rights and Hostinger cron/private-path capability, take a recoverable original-site+database backup, verify outbound network limits, rate limits, errors and source freshness. All such steps are still outside local CI and cannot be fabricated by an AI agent.

**The complete original site and theme are still not available** and must not be overwritten. The `public_html/index.html` vs `DirectoryIndex index.php` risk remains; the preview bundle is NOT approved for root extraction.

No paid services, credentials or public website files were modified during this code work.
