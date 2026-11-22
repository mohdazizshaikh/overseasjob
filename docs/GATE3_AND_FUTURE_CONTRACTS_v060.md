# v0.6.0 Implementation Changelog and Security/Gate Notes

**Date:** 2026-11-23. **Canonical requirements:** Source of Truth v1.3.3 unchanged.

### Local code changes

- M07: version-aware `radar.mjs`, import/export v2 with v1 migration, source-evidence gated matching, no background polling, no false “new posting” claims.
- M08: `daily.mjs` + `daily_ui.mjs` and 21 original offline original draft missions. One question per role/day, UTC deterministic, browser-local per-track streak. Import replacement explicit, bounded files, validation, export/delete, no-JS practice prompt.
- M09/M15: privacy controls and first-party optional event classes with no cookies or user identifiers. `GPC`, `DNT` and explicit opt-in enforced in UI; aggregate PHP endpoint requires `ANALYTICS_ENABLED=1` AND verified launch gate, same-origin HTTPS and allowlisted event. No revenue measurement or ads.
- M10: `OwnerAuth` HTTP moderation protected by owner-only hash, independent rate secret, HTTPS, CSRF, strict session and persistent HMAC-keyed 5/15-min attempts. Deliberately OFF unless authorised secrets and TLS exist; no owner passwords in repository. No browser login exercise yet.
- M12–M14: validation of future approved-org job contract without exposing routes. `EmployerContracts` cannot self-approve org, cannot accept external apply domain, cannot mutate provider jobs, cannot auto-publish. Only pure PHP unit checks exist; **does not replace verified identity, MySQL, full API, token expiry/revocation, web auth, or pilot moderation**.
- M16: Added 143 deterministic tests and a read-only GitHub Actions CI workflow. Existing 267 local checks preserved.
- Navigation, source evidence, `robots.txt`, noindex, privacy pages and `/contact` complete for development. Future `/employers` routes return 503 and `/developers` is informational, not an API announcement.

### Automated evidence

- Existing prior checks: 267
- M07/M08/M09/M15 JavaScript: 61
- Aggregate metrics PHP: 16
- Owner authentication PHP: 19
- Gated employer/partner validation PHP: 47
- New HTTP/feature flag/private-path smoke: 30
- **Total local deterministic checks: 440.** Source documentation validator separate 71/71.

### Explicit exclusions and blockers

1. **No real-live APIs:** cannot certify current Jobicy/Himalayas response, licensing, job coverage, quotas, source update dates, legal reuse or 14 calendar days.
2. **No Hostinger deployment:** no verified host PHP configuration, TLS, MySQL, mail, storage permissions, cron, backup/restore, rollback or operational metrics.
3. **No browser E2E:** existing `tests/browser_e2e.py` extended with M08/M09/M15 tests; Chrome localhost blocked in current sandbox. No passing Playwright claim.
4. **No human editorial approval** for guides/mission content or legal/privacy notices. Ads remain OFF.
5. **No recruiter/ATS live service:** future contract validator is foundation only, not a released registration/dashboard/API. All flags remain OFF.
6. **Actual site launches only after verified gate evidence.** `php scripts/launch_preflight.php` must truthfully report ready=false now.

### Security review follow-ups at staging

- Test owner TLS, `Secure; HttpOnly; SameSite=Strict` cookies, reverse-proxy HTTPS detection, session renewal, password rate limits and CSRF from an actual browser. Rotate owner secrets through hosting, never Git.
- Review global anonymous usage cap/abuse exposure, normal HTTPS Origin behaviour on Hostinger, and Apache private-path denials. Keep optional counters OFF until legal/consent approval.
- Confirm source credit/permissions and no unauthorised `JobPosting` or resyndication. Confirm queue/backup retention under lawful schedule and restore from genuine encrypted/offsite backups.
- Run mobile/keyboard/screen-reader and print/PDF reviews, staged performance baseline, link audit and verified 14-day source coverage before launch.
