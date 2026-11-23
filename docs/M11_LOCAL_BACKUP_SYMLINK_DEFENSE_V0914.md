# v0.9.14 — M11 local backup/restore path safety

**Status:** isolated-code correction and test candidate. The backup scripts operate on private application storage only and **do not** back up or restore Hostinger's existing live `public_html` site or any MySQL database. They are NOT a disaster-recovery certification.

## Observed risk

Previous `src/Backups.php` allowed `storage/backups` to be a symlink to an arbitrary directory, then called `chmod` and wrote backup copies into the symlink target. It also followed symlinked backup `.json` checksum metadata during isolated restore. A misconfigured shared host or maliciously redirected storage path could cause private file snapshot writes or reads outside the intended private backup area.

## M11 defense-in-depth fix

- Reject symlinked storage root, backup directory, original data source, backup candidate and checksum metadata. Broken source-file symlinks also fail closed rather than being silently skipped.
- Require `storage/backups` to be writable with enforceable **0700** mode; each local snapshot and checksum metadata uses **0600**. If permissions cannot be enforced, fail rather than claiming a verified backup.
- Reject unsafe symlinked retention entries before pruning old backup generations. Continue retaining two recent file snapshots per source under the existing contract.
- Reject missing/oversized or untrusted checksum metadata during the local isolated restore drill. Continue to check SHA-256 on both backup and isolated restored copy.
- Extend `tests/backup_tests.php` with filesystem symlink attacks against the source, storage root, backup directory and metadata, plus positive restoration and permission checks.

This change does **not** create a real off-site backup, encrypt archives, back up all original site/theme files, capture provider rights attestations, migrate or restore database records, or verify Hostinger `open_basedir`/PHP settings. `scripts/backup.php` and `scripts/restore_drill.php` remain private CLI-only rehearsals. The user must obtain independently restorable Hostinger originals before any same-production-host testing. A safe relative source-code audit is not permission to overwrite the site's `index.html`, `assets`, `images` or `.htaccess`.

No subscriptions, DNS, credentials, user data or live hosting files were touched.
