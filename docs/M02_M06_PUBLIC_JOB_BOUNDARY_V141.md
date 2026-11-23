# M02/M06 — Public imported-job URL and timestamp fail-closed contract
**Date:** 2026-11-24 | **Scope:** local/CI code; not live provider authorisation.

## Owner-contract discrepancy
Founder's exact `16_DATA_AND_OPERATIONS_CONTRACT.md` defines UTC ISO 8601 timestamps, permits an unknown expiry as `null`, requires source verification within 72 hours and says invalid/unknown source state must not masquerade as confirmed active publication. Existing `Jobs::visible` ignored malformed or relative `valid_through` after an unsuccessful `strtotime`, potentially leaving a corrupted imported record publicly visible. It also parsed an unchecked relative `last_verified_at`. Existing `Jobs::safeUrl` refused literal dotted IP addresses but could accept browser numeric IP aliases, or subdomains of private `.internal` and `.localhost`, as an outbound job Apply link.

## Code changes and regressions
- Require strict zero-offset `YYYY-MM-DDTHH:MM:SSZ` verified timestamp that round-trips exactly, retains 72-hour freshness and future tolerance rules.
- If `valid_through` is `null`, published job may be considered subject to all other checks; if it is **supplied**, require exact UTC round-trip and a future expiry. Malformed/relative/empty supplied values hide the job, not grant unlimited lifetime.
- Keep documented HTTPS source and employer Apply URLs while rejecting raw IP hosts, browser numeric/hex/octal aliases, dotless names, reserved local/internal hostnames and unsupported authority forms.
- PHP negative tests include malformed leap calendar dates, relative verification, supplied blank expiry, numeric localhost variants, reserved names, valid ordinary HTTPS domains and future expiry.

## Caveats, release gates
This is **syntactic browser-link hygiene only**. A DNS name may resolve to a private IP later or lead to fraud, and source/ATS authority still requires separate review. Changes do not verify employer legitimacy, the current provider rights, data authenticity, 14 actual UTC sample days, Hostinger deployment, backup restore or legal review. All runtime source, partner, recruiter, ads and public-release flags remain OFF by default. Do not infer production readiness from green CI.
