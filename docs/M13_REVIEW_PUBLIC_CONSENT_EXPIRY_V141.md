# M13 human reviewed publication — stored consent and genuine expiry guards

**Date:** 2026-11-24 | **Founder authority:** v1.4.1 exact `08_PARTNER_JOB_POSTING_API_OPENAPI.yaml` and `16_DATA_AND_OPERATIONS_CONTRACT.md`.

## Confirmed local gap
M12/M14 contract now denies creating or PATCHing malformed/false employer publication consent and impossible expiry. But the separate **human review / published public read** boundary (`EmployerReview::decision` and `publicProjection`) still checked only `publish_consent === true` and used permissive `strtotime` for expiry. Corrupted/legacy persisted payloads with no consent-at time or legal terms, future forged timestamps or silently normalized impossible dates could therefore pass shape-based review/public projection, even if modern create and PATCH validators would have blocked them.

## Fail-closed correction and scope
- Publish approval requires an existing true consent flag, nonblank legal terms version and an exact UTC ISO-8601 consent timestamp recorded **on/before the review time**, with calendar round trip. Reject if missing/corrupt.
- Publish and public projection both require a genuine unexpired UTC ISO-8601 `valid_through`, rejecting normalized calendar values and relative dates.
- Existing human review audit, approved organisation/company evidence, apply-link ownership, current revision, tenant scope, pending-review state and no autobypass remain unchanged.
- Public published job records without original terms/time evidence now hide rather than imply valid consent. Tests cover missing/false proof, future consent, malformed expiry and positive approved jobs.

**Important limit:** the presence of typed consent fields is necessary but **does not independently prove the human actor or legal validity**. Actual actor-specific consent audit/schema parity and original Hostinger database migration/restore must be verified before optional employer posting is enabled. No source/provider or external rights, fourteen-day role supply, Hostinger backup, live site change or production deployment is claimed. All partner, recruiter, ads and release flags remain OFF.
