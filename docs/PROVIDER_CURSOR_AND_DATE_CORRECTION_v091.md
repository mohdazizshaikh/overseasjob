# v0.9.1 — Himalayas provider adapter correction (23 November 2026)

**Status: isolated GitHub CI tests passed ([run 45073631074](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45073631074)). NOT a live source integration, legal approval, public deployment or production verification.**

## Official provider evidence

- https://himalayas.app/docs/remote-jobs-api and https://himalayas.app/api, reviewed 23 November 2026: browse pagination now prefers opaque `nextCursor`; `offset` pagination is deprecated.
- In the **JSON API**, job `pubDate` is the *last updated* timestamp, not proof of the original publication date. The source-provided original posting time remains unknown.
- Every Apply link must use the unchanged provided `applicationLink`; visible Himalayas credit is mandatory. Imported provider content may not flow to partner APIs or Google Jobs / `JobPosting`.
- Incomplete traversal, 429, 5xx and 404 are not evidence of vacancy closure; use authorised per-job 410 or documented expiry, and existing stale-data safety thresholds.

## What changed

- `src/Jobs.php`: `pubDate` maps to `source_updated_at`; `source_published_at` stays null. Added validated `himalayasNextCursor` and safe opaque cursor URL builder.
- `scripts/sync_jobs.php`: uses cursor-based fetch and detects repeated cursors/pages. A stopped or capped traversal **does not overwrite** the last healthy cache. The conservative page cap is 100 and must be reviewed against real source inventory and quotas before activating this feed.
- `tests/php_tests.php`: additional happy/negative cursor fixtures and isolated Himalayas ingestion/date semantics assertions. Deprecated offset helper remains only for previous test compatibility.
- Existing Job Decision Card already shows source publish, update, last-seen and last-checked fields separately; no fake published date is added.

## Governance and remaining blockers

The versioned v1.3.3 binary Source-of-Truth ZIP bundled in this repository includes an older provider-specific publication/offset assumption. Actual official upstream API documentation overrides dated assumptions under the authority register. **This code-only correction does not secretly rewrite or regenerate that canonical binary ZIP.** Update the owner provider contract, fixtures and release manifest in the next versioned Source-of-Truth pack before source activation.

No live Himalayas job sample, 14-day India eligibility observation, rate-limit quota, Hostinger migration, DNS/theme parity, recruiter identity, browser accessibility run, live email delivery or production backup restoration was verified here. Keep Himalayas and employer/API feature gates disabled. The PR's isolated deterministic and disposable MySQL jobs passed; public launch preflight must continue to fail closed.
