# OverseasJob.in v0.8.0 — Founder Report (23 November 2026)

**Status: meaningful new code implemented; public launch remains BLOCKED.** The live overseasjob.in site and hosting have not been modified.

### What was actually implemented

The 10 career tools, job discovery, Radar, Daily Mission, privacy controls and partner API core remain intact. This update adds genuine first-party job publication plumbing and an employer dashboard foundation: **invite-only emailed one-time confirmation**, strong password and login throttling, organisation-scoped job creation/edit/close, review-required job changes and separate owner-controlled job approval with a permanent audit trail. An independent DNS TXT checker can record domain control; the founder still must approve company legitimacy, representative authority and applicable recruiting rules using real evidence.

Public direct employer cards are separate from feed listings, require a human `publish` audit for the *current revision*, valid company evidence, a controlled HTTPS application link and future expiry. Suspended/closed/edited/unapproved jobs are hidden. The code never turns provider jobs into first-party inventory.

### Tests and deployment honesty

- Local PHP/JavaScript/HTTP deterministic test suite is run from `sh tests/run.sh`; see `docs/LOCAL_TEST_OUTPUT_2026-11-23_v080.txt` for **measured** check count.
- **Verified GitHub CI SUCCESS:** [run #45069896338](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45069896338) passed deterministic checks and **37 MySQL 8 integration assertions** in a disposable `oj_test` database: migrations, test-only email challenge, recruiter login, owner review, audit, edit/close, idempotence and token revocation. This is NOT evidence of Hostinger staging readiness or real email delivery.
- MySQL PDO is absent in this local container. Real Hostinger MySQL, DNS, outbound email, SSL, migration rollback and full browser tests are **not** verified. No actual employer has been approved or invited, and the jobs cache is still deliberately empty.
- New employer features, Partner Posting API, analytics, advertising and `RELEASE_READY` are all OFF by default. The v1.3.3 canonical specifications remain unchanged. No extra paid APIs or recurring subscriptions have been introduced.

**Next substantive work:** On authorised Hostinger staging, inspect actual PHP/MySQL/mail/DNS and verified backups; test private MySQL migration, member email delivery, original browser E2E, accessibility, legal/editorial review and real source feeds. Do not activate real employers or public jobs until those facts are observed.

Repository: https://github.com/mohdazizshaikh/overseasjob

Technical staging runbook: `docs/EMPLOYER_VERIFICATION_AND_MODERATION_v080.md`.