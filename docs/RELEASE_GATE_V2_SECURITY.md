# P0 pre-deployment release-gate hardening (2026-11-24)

STATUS: Local fail-closed code and regression tests only. No Hostinger access, external verification, source activation or production deployment.

## Earlier false-positive risk
RELEASE_READY=1 together with six verified=true entries pointing to 150-byte Markdown files could make the old Launch::status return ready. These fields did not independently establish actual backup restoration, licensed feed rights, fourteen UTC days, or legal/editorial approval.

## Requirements enforced before PUBLIC release
1. Exact founder source-of-truth v1.4.1 ZIP under SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.4.1_Corrected_Canonical_Pack.zip, matching SHA-256 3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9. Both RELEASE_MANIFEST.json source_of_truth and module_status.json spec_version must be v1.4.1. Exact founder v1.4.1 ZIP was imported through PR #27, verified in GitHub main CI and issue #22 closed; historical v1.3.x archives are explicitly nonauthoritative. Presence of the correct ZIP does not certify implementation conformance, licensing or hosting.
2. All six config/release_evidence.json checks need verified=true, reviewed_at as recent UTC date-time (30 days maximum), an immutable exact SHA-256 hash and a safe path to a 150–64000 byte normal Markdown file within docs/evidence. Symlinks and changed files are refused. File hashes show integrity, NOT external truth.
3. Fourteen distinct UTC dates validated from the private strict source coverage log. Fixture-like records may pass format tests, but this is explicitly NOT independent proof of genuine provider responses or two/three role-cohort supply.
4. At least one provider must pass the private SourcePermissionGate readiness with explicit global/provider live flags, current source policy and the real operator approval file. The provider-policy enabled flag alone is insufficient.
5. Five original guides must have recorded editorial review.
6. Separate PRIVATE storage/release_authorization.json must be a 0600 regular file outside public files and match exact manifest and evidence-register SHA-256 values. Require schema_version=1, approved_for_public_launch=true, independent_external_checks_reviewed=true, reviewer_role=owner, reviewed_at within seven UTC days, canonical_sha256 matching the founder ZIP and substantial review_notes. The private file is ignored by Git and NEVER placed in a release archive.

## Honest release signoff
This file and the six reviewed evidence notes may ONLY be recorded after real Hostinger filesystem/database backup+restore, current theme and noindex/SEO safety, actual provider rights and permitted sample history, human editorial/immigration/legal review, security tests and rollback have independently passed. They are not passwords or magic proofs; an operator who fabricates them bypasses the intention of this gate. Never upload original Hostinger ZIP, databases or applicant data into public GitHub.

By default .env.example still keeps RELEASE_READY=0 and every source/recruiter/ads gate OFF. The scripts/launch_preflight.php process must exit 2 until all necessary evidence is real; CI synthetic tests only prove negative cases, not business readiness.

## Module ledger consistency
The launch gate also requires module_status.json public_launch_ready=true AND hostinger_verified=true. Merely updating spec_version from historical v1.3.x to v1.4.1 may never make a site publicly ready while the actual hosting/module ledger still says unverified. Current repository flags remain false; no one should flip them before independent Hostinger proof and owner release approval. A new synthetic negative test verifies this additional veto.

## Current M17 role-cohort proof guard
Release also requires locally valid source coverage across fourteen actual UTC calendar days **and** the independent `RoleCoverage` pilot metadata across at least two of the three defined cohorts. Synthetic fixtures can never generate real role evidence, and 14 valid-shaped rows do not independently prove provider responses, India eligibility or legal terms. The new `scripts/predeployment_report.php` aggregates both read-only shape reports for the founder. Real role-supply yield, errors/terms notes and qualified legal/editorial review must be substantiated externally before enabling anything.

## Canonical artifact source-of-truth order
The GitHub Actions main workflow now publishes the exact `overseasjob-founder-canonical-v141-validated` ZIP (PR #29), not the historical v1.3.4 reissue as the current handoff. The original v1.3.x archives remain for retrospective reproducibility only. A downloadable artifact is **not** a deployment archive.
