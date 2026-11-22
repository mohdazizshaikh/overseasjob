# Gate 0: existing GitHub repository compatibility

**Source inspected read-only:** `https://github.com/rehman2671/overseas-platform` (default branch `main`) on 22 November 2026, using the user's connected GitHub integration. Existing repository was NOT modified.

## Significant conflict with canonical v1.3.1 specification

| Existing repository's README / tree | Current v1.3.1 requirements | Handling |
|---|---|---|
| Next.js 14 + Laravel 11 + Redis + FastAPI/PyTorch + Docker | Lightweight PHP/MySQL or host-compatible equivalent, zero additional recurring subscriptions, no always-on AI process | Do not reuse blindly; run cost/infrastructure assessment |
| Mandatory JWT job-seeker accounts and server-side resumes | No candidate account required; browser-local personal data by default | Product/architecture migration needed |
| Premium resume templates, subscriptions, Razorpay/Stripe | Free original tools; no paid candidate flows at launch | Keep payment/subscription routes OFF, never import into new default product |
| Candidate applications and employer ATS functionality | Apply at source only, no candidate CV/application server storage | Do not expose old application endpoints |
| AI optimisers, ATS scores and embeddings | No paid AI inference and no invented scores | Exclude unless explicitly re-approved later |
| Extensive admin/recruiter screens | Recruiter Lite and inbound API require separate identity, legal and moderation gates | Keep disabled until independently validated |

The old code may contain valuable reusable components, but compatibility is **not established**. Do not replace the current production domain or force a database migration until actual hosting, existing data, business scope and rollback paths are audited. The isolated alpha package is a safe engineering reference and does not alter the old repo.

## Follow-up work for autonomous coding agent

1. Determine whether the GitHub repo is deployed anywhere and inspect existing site/data/backups. Do not conflate a GitHub README with a live application.
2. Verify Hostinger account/domain and whether `overseasjob.in` points to a production website; this session could not confirm it.
3. Decide whether safe feature extraction into a new branch is worthwhile, or bootstrap the lean version in a new directory/repo.
4. Record module-by-module migration, private-data retention/erasure, security, cutover and rollback tests.
5. Never advertise the legacy paid/AI capabilities as part of the current no-login public MVP.
