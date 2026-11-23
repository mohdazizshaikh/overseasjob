# v1.4.1 — Module and tool implementation crosswalk (Gate 0 / issue #32)

**Classification: locally observed code/test associations only, NOT a 100% implementation certificate.** The sole normative owners are the exact founder ZIP `SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.4.1_Corrected_Canonical_Pack.zip` (`15_CANONICAL_MODULE_REQUIREMENTS.md` for M00–M17; `17_TOOL_ALGORITHMS_AND_FIXTURES.md` for T01–T10), pinned SHA-256 `3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9`.

## Machine-readable inventory

`docs/CANONICAL_V141_IMPLEMENTATION_TRACEABILITY.json` names all 18 canonical modules and ten tools with exact normative titles, observed application/code references, dispatched test files and remaining external and code-conformance uncertainties. It copies the **reported local implementation state** from `module_status.json` rather than claiming production deployment. The code-to-test mapping is reviewed with the current GitHub tree and is not a functional equivalence proof for every edge case.

`python3 tests/canonical_traceability_tests.py` reads the canonical ZIP itself, validates the normatively declared M00–M17 / T01–T10 IDs and names, checks SHA-256 and CRC, fails for missing/path-traversal/symlinked code or test links, proves every referenced test file is dispatched by `tests/run.sh` or GitHub workflows (or via its canonical bridge), prevents unreviewed module status drift and ensures the canonical M08 `/arena` plus historical `/daily` 301 contract is present. It **never** accesses the network, changes feature flags, logs into Hostinger, records fake 14-day feed samples or approves the release. The default release remains NO-GO.

## Evidence distinction

- **Canonical document/fixture validation:** 149/149 from the verified ZIP. Does not certify live app behaviour.
- **Direct owner-to-runtime golden comparison:** ten tool values in T01/T02/T04/T05 and 18 strict Himalayas eligibility examples from existing GitHub CI. Does not cover every golden/no-JS/UX/DB branch.
- **Other code association and local tests:** tests may prove selected functions, regressions and isolated browser experiences; no blanket “all conform” claim.
- **Unverified real-world gates:** original Hostinger account PHP/cron/SSL/private path and backup/independent DB+homepage restore; exact theme/SEO/cutover parity; current lawful job-provider rights and 14 genuine UTC days across 2–3 cohorts; qualified human editorial/immigration/legal/privacy review; real mobile assistive-tech testing; gated employer identity and partner operations.

## Code defect found during crosswalk

Canonical M08 explicitly declares `/arena` as owner destination with `301 /daily → /arena`. The previous repository exposed `/daily` only, with no `/arena`. See PR #33 (canonical route and HTTP/Chromium regressions). The machine checker adds a permanent guard against an accidentally reverted route or stale module ledger.

## Done/not done

The traceability checker being green means **the crosswalk is complete and correctly grounded in the existing local files**, not that the 18 modules have no other bugs or all 10 tools meet all untested owner behaviours. Further implementation discrepancies must be tracked as narrowly scoped PRs with negative tests and current manifest hashes. External release evidence and authorised production deployment remain in GitHub issue #3; broader full-contract issue #32 is **not closed by an inventory checker alone**.
