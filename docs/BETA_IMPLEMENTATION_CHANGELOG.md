> HISTORICAL BETA CHANGELOG — RC v0.3.0 is documented separately in `RC_IMPLEMENTATION_CHANGELOG.md`.

# Gate 1 Beta — Implementation changes (22 November 2026)

**Compared with Gate 1 Alpha:**

1. Himalayas country constraints correctly accept official `string[]` and numeric timezone offsets. Missing constraints remain unknown.
2. Himalayas browse pages use official offset+limit+totalCount; cursor is only a response-detected alternative. Mismatched offsets and malformed pages fail closed. Max 200-record sample remains a deliberately incomplete sampling run, not a full inventory.
3. `pubDate` for Himalayas follows current official publication-date reference rather than old v1.3.1 updated-date assumption; provider source-updated time is not guessed.
4. Jobicy `Anywhere` is no longer counted as confirmed India eligibility when geography and timezone are not explicitly supported by source evidence.
5. Full India filter requires *both* country/location and timezone acceptance. Remote-restricted/unknown filters display uncertain roles with honest labels.
6. Fixture-mode sync writes **only** `storage/fixture_jobs.json`, never the public `storage/jobs.json`; no fake jobs are shipped publicly.
7. Salary display shows disclosed values with currency and period, explicitly saying gross/net basis is unknown.
8. Read-only CLI preflight and 14-day real-observation summary scripts added. They never invent backend access or provider results.
9. Legacy paid-SaaS GitHub repository untouched. No Hostinger or production change made.

Remaining: real provider/hosting tests, full original content review, actual 14-day inventory, SQL migrations, stage/production deploy, complete browser E2E, backup restoration, Phase 2 onwards.
