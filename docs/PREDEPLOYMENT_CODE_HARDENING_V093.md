# OverseasJob.in v0.9.3 — Pre-deployment code hardening and evidence boundary

**Date:** 23 November 2026  
**Authority:** Canonical M00–M17/v1.3.3 scope remains unchanged. This is implementation/change evidence, NOT a replacement for normative ZIP owner files.  
**Deployment:** NOT deployed; do not overwrite existing overseasjob.in production files until host/theme/backup/launch evidence is independently verified.

## Code deficiencies addressed

1. **Ingestion retries:** new \`src/ProviderHttp.php\` enforces server-side HTTPS-only Jobicy/Himalayas upstream whitelist, no redirects or credentialed URL, timeouts and response limits, and a maximum of three attempts / twelve seconds accumulated sleep. HTTP 429/temporary 5xx honours Retry-After (seconds or date) and bounded exponential backoff; over-budget waits fail safely and defer to a future authorised sync. Complete cache remains unchanged on HTTP/parse/crawl error. Offline tests simulate retries; **no live provider was queried or re-authorised by this implementation**.
2. **Himalayas statuses:** \`scripts/check_himalayas_status.php\` is a separate **opt-in, default-disabled** CLI check of up to ten existing stored jobs per run. A strict original \`guid\` maps to the documented single-job endpoint. Exact-GUID HTTP 200 re-verifies; HTTP 410 closes/hides; HTTP 404 sets unconfirmed/hides but never claims closure. A failure aborts without committing partial updates; source policy must be enabled and reviewed. Run scheduling/quotas must be verified before activation. This does not scrape HTML listings or presume disappearance means closure.
3. **Canonical provider record:** added \`employer_country:null\`, \`reason_tags:[]\`, independent first-party \`source_policy_id\`, and explicit original Himalayas \`provider_job_id=guid\`. Legacy internal ID and existing human-readable URL route are preserved through \`provider_route_key\`. Existing cached records migrate naturally on completed source refresh; do not invent missing employer country or visa permission.
4. **Partial feed truth:** Jobicy is a bounded recent sample and \`partial_provider_sample\` must be true even on a successful response. Himalayas completed cursor traversal is only considered complete within the bounded traversal constraints.
5. **Fraud-report abuse:** public submissions require private \`REPORT_RATE_SECRET\` (>=32 chars), enforce a keyed HMAC of remote IP (never store a raw IP), six reports per client/hour and 120 total/hour on top of the existing 200-item moderation queue. Rate state is private; incomplete config fails closed. Public error strings no longer expose raw exception messages. CLI direct-review fixtures remain separately supported. Host environment setup and operational staffing are still mandatory.
6. **Backup/health:** file snapshots now include \`analytics_daily.json\`, \`report_rate.json\`, and \`owner_rate.json\` alongside original job/moderation/coverage files; stale backups become warnings. New read-only CLI \`scripts/health_guard.php\` emits sanitized health signals and exits nonzero on warnings. **This is not encrypted off-site backup, MySQL backup, live restore, guaranteed uptime monitoring or an email alert.**
7. **Host suitability:** \`scripts/gate0_preflight.php\` checks observable CLI PHP extensions, writable storage, and possible sibling \`public_html\` bootstrap/private layout. Reported observations DO NOT mark Hostinger, actual HTTP PHP-FPM, SSL, DNS, cron or \`open_basedir\` as externally certified.
8. **Status governance:** all 18 \`module_status.json\` entries now include the required \`spec_version\`, \`tests\`, \`routes\`, \`dependencies\`, \`evidence_paths\`, \`blocker_reason\`, and \`last_verified_at\`. Last-verified timestamps are null until independent per-module evidence exists. CI verifies 18 IDs, schema and real evidence paths; code presence never upgrades a module to deployed.

## Canonical provider contract reconciliation still required

The bundled **v1.3.3 ZIP** is a versioned binary export; it has earlier provider assumptions identified in \`docs/PROVIDER_CURSOR_AND_DATE_CORRECTION_v091.md\`. Its authority owner \`16_DATA_AND_OPERATIONS_CONTRACT.md\` already describes opaque nextCursor/updatedAt, but some older historical ZIP fixtures/manifest or examples remain inconsistent. The correct procedure is to change authoritative editable files, validator, fixtures, changelog, regenerate an integrated v1.3.4+ pack and human-readable exports, then verify. **No one may silently edit the old binary or declare the new pack complete without running its canonical validator.** For 2026-11-23 code, live API docs and conservative fail-closed mapping win on external facts.

## Remaining real-world blockers (not fixable from isolated source alone)

- Actual existing \`public_html\` original HTML/CSS/assets comparison and tested rollback. Its \`index.html\` is not authorised for overwrite by CI.
- Hostinger PHP-FPM/open_basedir/readable private sibling, HTTPS reverse-proxy behaviour, MySQL migrations 001–005 on isolated backed-up host, cron, real inbox receipt, and disaster recovery with encrypted off-site copies.
- Reviewed provider legal/current terms, live Jobicy and Himalayas response examples, real role/country stock over 14 distinct observation dates, 404/410 real source behaviour, source quotas.
- Five original guides' editorial/immigration/legal review, indexed content and privacy policy approvals, live trust moderation and response channel.
- Independent keyboard/screen-reader contrast/accessibility/print/performance inspection on the final original-theme host.
- Employer domain control **plus** legal organisation and representative identity; external employer pilot and Partner API remain OFF, not 'complete'.
- Google indexing, ads, AdSense, affiliate payouts, traffic, and any earnings remain unverified/disabled.

**Safe switches:** \`RELEASE_READY=0\`, \`ANALYTICS_ENABLED=0\`, \`ADS_ENABLED=0\`, \`OWNER_PANEL_ENABLED=0\`, \`RECRUITER_LITE_ENABLED=0\`, \`PARTNER_POSTING_API_ENABLED=0\`, \`RECRUITER_PORTAL_ENABLED=0\`. Do not turn these on to bypass missing evidence.

**Source:** issue #4 for code audit and issue #3 for production evidence. Code changes require 3/3 CI jobs green before merge.
