# v0.9.4 — Himalayas official OpenAPI contract revalidation (23 November 2026)

**STATUS: Official published API contract independently rechecked; code/fixed fixtures require CI. Not a live API sample, provider legal approval or hosting verification. The Himalayas provider flag remains OFF.**

## Primary provider sources

- https://himalayas.app/docs/openapi.json — OpenAPI 3.1.0; currently declares exactly TWO public JSON endpoints: `GET /jobs/api` and `GET /jobs/api/search`.
- https://himalayas.app/docs/remote-jobs-api — documented browse `offset` / `limit` / `totalCount`; feed refresh every 24h and rate limits.
- https://himalayas.app/api — explicitly shows browse offset, direct `applicationLink` and public-source attribution requirement.

These public docs **contradict the previous 2026-11-23 implementation note** `docs/PROVIDER_CURSOR_AND_DATE_CORRECTION_v091.md`, which claimed `nextCursor` and a last-updated meaning for `pubDate`. Do not rely on that superseded interpretation. The current official OpenAPI, not a previous implementation claim or dated specification, determines external API compatibility. Recheck on actual source activation.

## Verified *documented* contract and conservative local changes

| Property | Documented provider API | v0.9.4 implementation |
|---|---|---|
| Browse pagination | `offset` + `limit` (max 20), `totalCount`, `jobs` | Strict sequential offset traversal; reject mismatched offsets, missing total, anomalous page size or repeated page; retain last healthy cache on failed/capped walk |
| Publication time | `pubDate` = job publication Unix milliseconds | Normalize to `source_published_at`; never treat it as update |
| Feed update | response-level `updatedAt` = feed refresh time | Never silently map feed refresh time to per-job `source_updated_at` |
| Job identifier | `guid` = opaque string, example `stripe-senior-software-engineer-abc123` | Retain exact `provider_job_id` and stable hash-based internal ID/URL key |
| Application | `applicationLink` may be external ATS/employer HTTPS | Preserve validated original HTTPS link unchanged; never rewrite through an invented Himalayas URL |
| Original listing URL | NOT separately guaranteed when `guid` is opaque | Retain the actual `guid`; use Himalayas browse directory as non-job-specific attribution link rather than fabricating a detailed URL |
| Per-job status | **No publicly documented per-job status endpoint** | Forbid production calls in `scripts/check_himalayas_status.php`, retain clearly labelled fixture-only tests; no fabricated 200/410 claims |
| Attribution | Link to Himalayas and name source; no export to third-party job boards | Keep provider credit; partner/export and JobPosting remain prohibited |
| Rate limit | 429, daily-refreshed source; recommended <= daily cadence | Existing bounded backoff, no premature retry on long Retry-After; actual scheduling remains unverified |

## Release boundary and corrective actions

The versioned Source of Truth v1.3.3 owner `16_DATA_AND_OPERATIONS_CONTRACT.md` and some previous AI-generated documents describe a cursor and 410/404 reconciliation. These assertions conflict with the now-checked official OpenAPI. Per canonical `00_SOURCE_OF_TRUTH.md` authority rules, current API facts supersede stale provider assumptions. **A new canonical v1.3.4+ ZIP and readable export is required** to reconcile the normative owner, fixture tests and manifest; do not silently rewrite the old archive and pretend it was never issued. Keep Himalayas **disabled** until actual terms and real responses can be verified.

This code correction is not permission to deploy over the current Hostinger `public_html`, change SEO/noindex gates, alter the existing theme, enable employer/partner pathways or claim real India job supply. Use read-only host inspection and full backup/restore evidence first.

**Sources reviewed:** 2026-11-23; provider docs may change without notice. GitHub issue #4 documents the previous claim and this correction.
