> HISTORICAL BETA NOTE: v1.3.3 canonical contract and `docs/RC_IMPLEMENTATION_CHANGELOG.md` supersede any inconsistent ISO/name/object country-field examples here. No real live-source request has yet been executed.

# Live primary-source documentation verification — 22 November 2026

**Scope:** Official online documentation, not successful runtime network calls or live inventory. Site/network calls to the providers failed from this isolated local development runtime; no real listing was imported.

## Verified documentation and required implementation changes

**Himalayas** https://himalayas.app/docs/remote-jobs-api and https://himalayas.app/api
- Browse endpoint: `GET /jobs/api?limit=20&offset=N` with `totalCount`. Earlier alpha incorrectly assumed `nextCursor` was mandatory. Beta supports offset/limit and a cursor variant only if the live response supplies it. Partial pagination does not imply job closure.
- Job shape: `locationRestrictions` is `string[]` containing ISO alpha-2 country codes; `timezoneRestrictions` is `number[]` of UTC-hour offsets. Empty arrays mean worldwide/any timezone respectively. Alpha's object-style country fixture masked this discrepancy.
- Both currently available official public API references describe job-level `pubDate` as a **publication date**. `updatedAt` on the top-level response is a feed refresh timestamp. Beta maps only documented job-level `pubDate` to `source_published_at` and does not invent a job update date.
- `applicationLink` must be used without modification, and source credit must be visible; third-party job records must not be redistributed or added to Google Jobs/JobPosting schema.

**Jobicy** https://jobicy.com/jobs-rss-feed and https://github.com/Jobicy/remote-jobs-api
- `jobGeo` value `Anywhere` is documented as **no region specified**, not evidence that the employer explicitly hires anywhere in the world. Beta avoids confirming eligibility from that label alone. Unknown timezone acceptance remains unknown.
- Public API returns canonical Jobicy URLs; not direct ATS application URLs. Absence from a bounded recent-job feed is never automatic closure.

**Safety boundary:** Actual provider terms, schema and rate limits must be checked again from the deployment server. Any source/version divergence is a provider-specific blocker, not permission to invent eligibility or dates.

**Source of Truth:** Version 1.3.2 updates authoritative `16_DATA_AND_OPERATIONS_CONTRACT.md` to reflect these corrections, while retaining the same scope, 18 modules and ten tools. Historic version 1.3.1 documents are superseded for these provider-specific behaviours.
