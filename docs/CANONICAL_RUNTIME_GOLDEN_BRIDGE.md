# Founder v1.4.1 — Direct canonical fixture-to-implementation regression

**Purpose:** previously the v1.4.1 ZIP's own `23_VALIDATE_SPEC.py` independently passed 149 document/fixture checks, and PHP/JS code had independently passing test examples. Independent success does not automatically guarantee that the *same exact founder golden outputs* are exercised against working code.

The offline `python3 tests/canonical_runtime_contract.py` verifies the exact founder ZIP SHA-256, parses the **unchanged** `18_TEST_FIXTURES.json` from inside it and runs:

- `tests/canonical_tool_fixtures.mjs`: the real T01 salary/savings, T02 first-year offer comparison, T04 relocation reserve and T05 London/Kolkata daylight-saving functions against ten outputs defined in the canonical ZIP. Expected values are **not copied to code**; only scenario inputs are specified in the test.
- `tests/canonical_provider_fixtures.php`: all eighteen v1.4.1 strict Himalayas ISO Location-object / timezone UTC-string India-eligibility cases against `Jobs::himalayasIndia(..., true)`. Missing, malformed, excluded and unrestricted states are checked without ever calling an external provider.

The test is included in normal local `tests/run.sh` and GitHub deterministic CI. A new canonical ZIP hash/fixture change will fail closed until the code, spec owner and tests are consciously reconciled. This check covers four tool golden-output sets and eighteen provider fixtures **only**; it does **not** establish compliance of all ten tools, all eighteen modules, actual source licensing, 14 real days, data freshness, Hostinger backup restoration or production launch safety. The exact original ZIP and historical packs remain unmodified.
