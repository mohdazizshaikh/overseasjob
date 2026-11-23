# Canonical Source of Truth — v1.3.4 narrow provider-status reissue

**Date:** 23 November 2026  
**Authority input:** Exact tracked `SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.3.3_Source_of_Truth_Project_Pack.zip` (not the older v1.3 user ZIP). The original is SHA-256 checked, not modified or replaced.

## Verified source facts, and why the patch is narrow

Actual v1.3.3 inspection in [PR #8](https://github.com/mohdazizshaikh/overseasjob/pull/8) confirms that its `16_DATA_AND_OPERATIONS_CONTRACT.md` owner **already correctly** requires offset+limit+totalCount browse pagination and publication-date `pubDate`, with unknown country entries treated conservatively. **Do not reintroduce v0.9.1 nextCursor assumptions or call this part outdated.**

The remaining contradiction is a sentence calling Himalayas HTTP 410 authoritative closure, even though official public OpenAPI at https://himalayas.app/docs/openapi.json defines only `GET /jobs/api` and `GET /jobs/api/search`. Any imagined per-job `/jobs/api/companies/.../jobs/...` endpoint must remain disabled. Other sources may implement 410 on *documented authorised endpoints*; this is an evidence-dependent rule, not a global Himalayas guarantee.

## Reissue procedure (fully local and deterministic)

`python3 scripts/reissue_canonical_pack_v134.py --output artifacts/canonical-v134`

1. Validate ZIP CRC, root prefix, original file manifest and SHA-256 of **every** original v1.3.3 canonical member. Reject missing/duplicate/path-traversal or corrupted source.
2. Change only actual contract wording in `16_DATA_AND_OPERATIONS_CONTRACT.md`, its related `05_SOURCE_POLICY_MATRIX.csv` row, `20_VERIFICATION_LEDGER.md`, release readme, `09_CHANGELOG.md`, `23_VALIDATE_SPEC.py`, and metadata/manifest. Versioned fixture `18_TEST_FIXTURES.json` changes **only if** its version field was v1.3.3.
3. Preserve **every other canonical source file bit-for-bit**. Do not edit business scope, module M00–M17, tool T01–T10, recruiter OpenAPI, SQL or historical archived audits.
4. Run reissued canonical validator from extracted temporary source; require all checks pass. Recompute its release manifest SHA-256 records, produce a new ZIP under `OverseasJob_v1.3.4_Canonical_Specification/`, and include `v134_integrity_and_scope.json` with original and final SHA-256, changed and unchanged file lists.
5. GitHub CI `tests/canonical_pack_reissue_tests.py` additionally checks archive repeatability, actual manifest and readme version, and absence of invented production evidence. A `main` run that also passes PHP/Node, disposable MySQL and real Chromium checks uploads downloadable `overseasjob-canonical-v134-validated`.

**There is no Word/PDF export in the tracked v1.3.3 canonical ZIP**; the older uploaded v1.3 Word document remains a historical snapshot. We do not create a new Word export by rewriting the older version independently. A human-readable v1.3.4 version of the specific changed contracts can be read directly as Markdown inside the versioned ZIP.

## Release boundaries not closed

Not proof of actual Hostinger permissions, current-theme preservation, backups or restoration, real API licence or successful provider sync, employment claims, verified employer identities, immigration/legal review, 14 actual observed inventory days, ads/indexing approval or public readiness. `RELEASE_READY=0` and Himalayas disabled remain unchanged.

No real credentials, paid systems, DNS or production files are touched by the reissue tool. The ZIP is **documentation/contract evidence**, not a deployable application or a release override.
