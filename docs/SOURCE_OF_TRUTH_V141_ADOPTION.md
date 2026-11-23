# Canonical v1.4.1 adoption — truth and deployment boundary

The authoritative founder artifact is **`SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.4.1_Corrected_Canonical_Pack.zip`**, exact SHA-256:

`3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9`

Validate using `python3 scripts/validate_founder_canonical_v141.py`: it requires 149/149 **documentation** checks. The historical v1.3.3 ZIP and v1.3.4 narrow reissue remain for evidence and comparison only. When documentation conflicts, follow the owner map in v1.4.1 `00_SOURCE_OF_TRUTH.md`. Current official provider terms and actual verified host facts override incorrect source summaries, preserving safe default-OFF behaviour.

## Never infer these results from CI

- The local PHP application, browser routes and tools can be tested, but the canonical documentation ZIP is not a deployed app.
- No actual Hostinger private directory, PHP-FPM permissions, existing-site/DB restored backup, original theme parity, or public cutover is verified by this ZIP.
- No real licensed live job feeds, 14-days-of-actual-coverage, verified recruiter or identity, AdSense approval, legal editorial signoff, and no revenue are established by the ZIP.
- The GitHub historical v1.3.4 artifact workflow can remain as a reproducibility check. It is **not** current authority.
- Recruiter/API, source provider HTTP, ads and public launch flags remain disabled until their separate evidence gates are legitimately cleared.

## Handoff for a coding agent

On a fresh branch, import the exact ZIP with the paired `stage_v141_into_repo.py` in the one-command kit. This operation stages only the canonical ZIP, one validator, one adoption note, README authority banner, CI workflow step and manifest. Do not overwrite code, deployment files, real site assets or database. Then run full GitHub PR CI; merge only if all required checks are green. 

No live Hostinger credentials, backup files, employment data, personal information or private provider rights attestations should be committed to GitHub.
