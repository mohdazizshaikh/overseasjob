# M14 Partner API — owner-visible distribution consent response (v1.4.1)

**Date:** 24 November 2026. **Code-only verification: optional employer/partner access remains OFF.**

## Exact founder contract
The founder's canonical `08_PARTNER_JOB_POSTING_API_OPENAPI.yaml` defines `JobResponse.distribution_consent` as a boolean, separately from the mandatory `consent_to_publish`. It is **not** implied by publication or an employer's approved account. Existing `EmployerContracts::validateCreate` stores `distribution_consent` only when explicitly provided; otherwise it defaults to **false**, but `PartnerApi::publicJob` previously omitted this field from the authenticated employer JobResponse.

## Fix
Add a fail-closed owner-visible `distribution_consent` field in `PartnerApi::publicJob` that is true **only** for an explicitly persisted boolean true. Missing, legacy, false or invalid state returns false. Existing provider-origin isolation, actual human publication approval, token scopes and tenant filtering unchanged. Three tests cover actual false default, explicit true and legacy missing status.

## Still blocked
The API's `JobPatch` owner definition also permits `distribution_consent` updates, but the current pilot intentionally refuses them: there is **no fully implemented actor/time/terms-version consent-event audit** equivalent to the canonical `job_publication_consents` SQL table. This PR does not grant new redistribution rights or implement a new PATCH approval. A future consent-change design must verify the actor, record an immutable consent audit and ensure status review/withdrawal is effective before allowing new grants. Do not treat a bare boolean as legally verified consent.

**No Hostinger, real employer, source licence, 14-day provider pilot or production deployment occurred.** Partner/recruiter/source/ads/release flags remain OFF. Keep external compliance and canonical SQL parity open in [issue #32](https://github.com/mohdazizshaikh/overseasjob/issues/32).
