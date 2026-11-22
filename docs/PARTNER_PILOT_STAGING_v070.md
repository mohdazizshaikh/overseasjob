# OverseasJob.in v0.7.0 — gated Partner Posting Pilot

**Status: CODE PRESENT, NOT DEPLOYED OR ENABLED.** Canonical v1.3.3 files 07/08/16/24 remain the authority. This work implements a subset of Gate 2B/3B and does not change product scope.

## Implemented now

- `src/PartnerApi.php`: request size and JSON checks, strict HTTPS, bearer + `jobs:read`/`jobs:write`/`jobs:close` permissions, per-token minute throttling, exact organisation isolation, signed idempotency lookup, optimistic `If-Match`, read/list/close/usage routes and safe error envelopes.
- `src/PartnerMysqlStore.php` + `database/001_partner_pilot.mysql.sql`: native prepared SQL, InnoDB foreign keys, per-org atomic create and daily pilot quota, status/revision/audit, hashed-only token storage, short-lived request counters and pending-review job records. The pilot tables are separate to avoid accidental merging of third-party feeds into employer exports.
- `src/PartnerTokenAdmin.php` + `scripts/partner_tokens.php`: **trusted CLI-only** issuance (30-day by default, 90-day maximum) and immediate revocation. A token can be issued only for a genuinely approved organisation that already has trusted recorded identity, email, domain verification and independent decision evidence.
- `tests/partner_api_tests.php`: deterministic in-memory contract/abuse cases; `tests/http_partner_pilot_smoke.sh`: verifies public HTTP endpoints stay disabled, private scripts are not exposed and existing visitor functionality remains working.
- All API jobs are `pending_review`; no API operation can publish, certify an employer, generate a public job URL or access provider-feed entries.

## External **hard blockers** — fail closed

1. Existing Hostinger staging plan, Apache/PHP settings, HTTPS termination, *`pdo_mysql`*, database credentials stored in the hosting secret manager, cron/log retention, rate state and offsite database backups must be observed. This coding sandbox has **no MySQL PDO driver**, so real SQL migration, transactions and idempotency under concurrent API requests are **NOT tested**.
2. Complete independent employer email confirmation and domain proof, genuine business identity review, agency/legal restrictions where applicable, representative authority and recorded approval. No public `register`/login/email-verification workflow exists in this release. The organisation verification rows must not be self-populated by API users.
3. Build and test a separate owner-reviewed publication workflow before any first-party job appears on `/jobs`. This API **does not publish its submissions**. Public `GET /jobs` remains provider-only and cannot be silently merged with unreviewed employer jobs.
4. Perform OWASP-oriented staging penetration, brute-force and concurrency tests, DB backup/restore rehearsals, expiration/retention jobs, review audit integrity and real-browser usability/accessibility checks.
5. Preserve original job-provider permissions: third-party Jobicy/Himalayas imports **never** enter the partner tables, inbound API or outbound export.

## Staging-only procedure (operator, not user-facing)

- Back up and document database/restore path first. Verify that the target schema is a **dedicated staging database**, not production. Compare `database/001_partner_pilot.mysql.sql` to canonical `24_DATABASE_SCHEMA_REFERENCE.sql` and test migration + rollback on backup.
- The migration is intentionally an *isolated pilot extension*, not an assertion of deployed canonical SQL; it must be reconciled before production.
- Use a dedicated MySQL credential with only required permissions and no root/admin database grants; set DNS/HTTPS normally.
- Keep `PARTNER_POSTING_API_ENABLED=0`, `RECRUITER_LITE_ENABLED=0`, `PARTNER_PILOT_VERIFIED=0`, `PARTNER_TOKEN_ADMIN_ENABLED=0` unless the verification gate has actually been completed. Use `php scripts/partner_preflight.php` for a **configuration-only** diagnostic; it does not prove legal, email or domain checks.
- After the independently reviewed organisation is in server-owned DB state, `PARTNER_TOKEN_ADMIN_ENABLED=1` *on a private terminal only* can authorise `php scripts/partner_tokens.php issue org_EXAMPLE jobs:read,jobs:write,jobs:close`. The token is printed **once**, must never be pasted into a chat, committed, or shown in frontend JavaScript. Immediately rotate/revoke if leaked. Avoid capturing stdout in a CI log.
- Enable the HTTP feature flags **only** after all external gates, signoff and staging tests. No paid provider or new subscription is required for the code, though actual hosting database availability must be verified.
- Demo calls may be made using an approved test organisation on **private HTTPS staging** only. Verify POST retry with identical/different payloads, blocked cross-tenant GET/PATCH/close, revocation, request burst 429, malformed JSON 422, missing HTTPS, wrong token scopes, failed SQL transaction rollback, and pending-review visibility. Test with real MySQL 8.0+/MariaDB compatibility in a *backup copy*.
- Before any public pilot: implement and test authenticated employer browser onboarding/dashboard, publication human-review + expiry and tenant-scoped public rendering. Do not misrepresent first-party jobs as source-verified imported listings.

## Emergency rollback

1. Disable `PARTNER_POSTING_API_ENABLED` and `PARTNER_TOKEN_ADMIN_ENABLED` in the hosting environment; verify `/api/v1/partner/jobs` returns the disabled/no-data response.
2. Revoke affected tokens in private MySQL state; restore from a verified safe backup if schema/data integrity is threatened.
3. Quarantine suspicious jobs, document incidents in the private audit record, and avoid deleting provider listings.
4. The new tables are deliberately isolated; **never drop data tables as an automatic rollback**. Keep records and audit trails for review.

**Test accounting:** Existing 440 local checks + 53 new PHP partner-contract tests + 16 HTTP safe-gate checks = **509 local checks**. These are unit, fixture and HTTP responses, *not* a real database integration, security audit, or live deployment. GitHub Actions must independently pass after push.
