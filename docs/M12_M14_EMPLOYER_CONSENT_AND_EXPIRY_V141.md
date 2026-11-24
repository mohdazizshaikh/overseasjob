# Employer / Partner first-party job contract: immutable consent and valid expiry
**Canonical v1.4.1** | **24 November 2026** | **Local/CI tests only; NOT a Hostinger deployment**

## Exact owner requirement
Founder `08_PARTNER_JOB_POSTING_API_OPENAPI.yaml` defines a required employer-supplied RFC3339 `valid_through` and `consent_to_publish: true`; the latter must have actor/org, UTC timestamp and legal-terms-version evidence in server-controlled audit. `16_DATA_AND_OPERATIONS_CONTRACT.md` requires truthful UTC source/expiry data and no unsupported active publication.

## Reproducible local discrepancy
1. `EmployerContracts::fixedDate` previously passed RFC3339-shaped dates directly to PHP `DateTimeImmutable`. PHP can silently normalize an impossible calendar day such as **2026-11-31** into **2026-12-01**, potentially creating a job with a future expiry the employer did not actually supply.
2. `validatePatch` previously set `consent_to_publish=true` unconditionally and copied `consent_at` and `consent_terms_version` from the existing job without first asserting that the persisted publication consent was actually true and both pieces of proof were valid. A corrupted/restored legacy job could be edited into pending review without genuine stored consent evidence.

## Correction and regression
- Preserve RFC3339 timezone offsets and convert to UTC, but reject calendar normalization by comparing parsed local calendar and exact input.
- Require existing `publish_consent===true`, nonempty versioned legal terms and strict round-trippable UTC consent time **before** accepting a PATCH; protect original timestamps/terms against implicit replacement.
- Add negative and positive assertions for impossible calendar day, verified offset expiry, absent/false consent, absent terms and relative consent timestamps. Keep existing tenant checks, optimistic revision, and mandatory return-to-review on edits.
- The optional employer API/portal, human moderation and source feeds remain **OFF** by default. No database migrations are performed.

## External and specification gaps remain
This does not authenticate the original consent actor independently or provide the final human audit workflow defined in `08_PARTNER_JOB_POSTING_API_OPENAPI.yaml` and `24_DATABASE_SCHEMA_REFERENCE.sql`. Full consent actor capture, `distribution_consent` PATCH semantics, DNS/identity proofs, secure Hostinger runtime and real backup restore remain in [issue #32](https://github.com/mohdazizshaikh/overseasjob/issues/32) and [release issue #3](https://github.com/mohdazizshaikh/overseasjob/issues/3). Neither CI synthetic organisations nor a "pending_review" return can be treated as approval.
