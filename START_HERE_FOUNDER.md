# OverseasJob.in v0.7.0 — Founder Status (23 Nov 2026)

**Code added to the working GitHub project, not a launched recruitment business.** The 10 career tools, Career Radar and Daily Career Mission remain locally implemented from v0.6.0.

### What's new

A genuine code foundation for **invite-only employer/partner posting**: a structured REST endpoint, permission checks so each company sees only its own jobs, private hashed API keys, duplicate-request protection, update/close with revision checks, abuse throttling, and a MySQL staging migration. A new submission is ALWAYS sent to `pending_review`; the partner cannot make it live by itself. Admin token issuance is CLI-only and disabled unless the organisation has independent identity/email/domain approval.

### What has been tested

- **509 local checks** (440 previous + 53 new partner security/domain + 16 HTTP feature-gate checks) on this code tree. Full `sh tests/run.sh` passed in the local PHP/Node runtime.
- The proposed API remains disabled (`PARTNER_POSTING_API_ENABLED=0`); no employer or candidate accounts or personal files were created.
- This environment has **no installed MySQL PDO driver**. **The actual MySQL migration, SQL queries, transactions, API HTTP when enabled, token issuance against real employer records and production hosting remain UNTESTED.** These need independent staging evidence.
- External live job-source API availability, full-browser E2E and Hostinger deployment remain pending. Public release gate stays `ready:false`.

### Still incomplete

Actual verified employer onboarding, email/domain verification, real applicant-facing direct job publication, reviewer workflow, recruiter web dashboard, deployed MySQL setup, real API integration and security testing, and content/legal signoff. Recruiter and partner API features should **NOT** be enabled or advertised as available.

### Important links

- Canonical specification: `SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.3.3_Source_of_Truth_Project_Pack.zip`
- New staging implementation + blockers: `docs/PARTNER_PILOT_STAGING_v070.md`
- Run code tests: `sh tests/run.sh`
- Development repository: https://github.com/mohdazizshaikh/overseasjob

**Recommended continuation:** Stage MySQL safely, build verifiable email/domain onboarding and manual reviewer publication controls, then test end to end on Hostinger staging; do not turn on public posting until gates pass.
