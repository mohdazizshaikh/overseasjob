# v0.9.11 — Complete Git-tracked release manifest and safe regeneration

**Scope:** source-code build quality and release integrity only. Does not deploy or certify Hostinger, live job supply, privacy law or third-party permissions.

## Reproduced loophole

The earlier `scripts/verify_release_manifest.php` checked the file count and SHA-256 **only among entries already listed** in `RELEASE_MANIFEST.json`. A new file committed to Git but omitted from that JSON could go entirely unchecked, yet a CI log would still report that every *listed* file passed. This is a correctness/omission gap for a codebase maintained by autonomous coding agents.

## Repair

New zero-dependency Python `scripts/refresh_release_manifest.py` derives its inventory from `git ls-files --cached` on the local checkout:

- `python3 scripts/refresh_release_manifest.py --check` (read-only) compares **all** Git-tracked source files except `RELEASE_MANIFEST.json` against the JSON records. Unknown/omitted Git-tracked files, extra stale entries, altered bytes or SHA, wrong count, symlinks, missing paths and known secret-like tracked filenames all fail closed.
- `python3 scripts/refresh_release_manifest.py --write --release release_name` regenerates the complete SHA-256 + byte-count array from actual Git-tracked files and atomically replaces **only** `RELEASE_MANIFEST.json`. It preserves other JSON metadata. Safe for coding agents; this command does not deploy, upload, email, fetch provider feeds or alter website data.
- It refuses tracking `.env`, `.env.*` (except nonsecret `.env.example`) and tracked key/certificate private material (`.pem`, `.key`, `.p12`, `.pfx`) as release content. This is a partial denylist, NOT a secret-scanning certification.
- `tests/manifest_inventory_tests.py` creates a disposable Git repository to show omission detection, hash tampering detection, correct atomic refresh, tracked secret rejection and symlink refusal. No real user files or account credentials are involved.
- CI now runs **both** the legacy PHP file-hash validator and the new Git-inventory exhaustive checker. The validated Hostinger preview builder still consumes the same release manifest.

## Truth boundary and remaining gates

This closes an implementable **CI inventory** gap; passing source manifest checks still does not mean the application has enough licensed real jobs, an approved employer programme, complete original theme, a verified Hostinger backup/restore, actual uptime or WCAG certification. `module_status.json` never marks public launch ready because the source tree passes checksum checks.

The canonical v1.3.4 pack remains a separate versioned **documentation artifact**; old v1.3.3 ZIP historical source remains immutable.
