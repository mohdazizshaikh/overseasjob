# v0.9.7 — Direct Hostinger cutover guard for existing production webroot

**Status:** local source-code safety implementation with CI acceptance tests; **not** evidence that Hostinger's live site or theme is safe to replace. The founder uses the current `overseasjob.in` production account as a testing environment, not a separate paid staging host. This preference does not waive backup, legal, data or SEO release gates.

## The confirmed risk in the current upload artifact

The founder's file manager screenshots showed `public_html/assets/`, `public_html/images/` and `public_html/index.html` (541 bytes), plus a parent marker `DO_NOT_UPLOAD_HERE`.

The public ZIP preserves `index.html` as a *file*. **However, `public/.htaccess` explicitly sets `DirectoryIndex index.php`**. If the public ZIP's `index.php` and `.htaccess` are uploaded to the real Apache-compatible `public_html`, requesting the root `/` can immediately run the new PHP homepage instead of the old `index.html`. Unchanged file bytes are not unchanged website behavior. Existing same-path `assets/*` files can also be replaced by unpacking the ZIP; this changes live CSS/JS/images. The PHP preview also returns `noindex,nofollow` while release evidence remains missing, causing potential SEO disruption on the current indexed domain.

## v0.9.13 — read downloaded public_html ZIP directly (no extraction)

The founder can now use a saved **Hostinger File Manager ZIP of only the original public_html** as an offline audit input. The new command does NOT extract or upload the ZIP:

`python3 scripts/audit_hostinger_cutover.py --current-site-zip PATH_TO_ORIGINAL_PUBLIC_HTML_BACKUP.zip --candidate-public-zip PATH_TO_GITHUB_PUBLIC_PREVIEW.zip`

Alternatively the existing extracted-folder mode `--current-site-copy PATH_TO_LOCAL_PUBLIC_HTML` still works unchanged. The ZIP reader accepts either a root `index.html` or one literal `public_html/` wrapper. It rejects mixed roots, traversal entries, nonregular files/symlinks, duplicate member paths (case-insensitive), oversized archives, unexpectedly compressed zip bombs, missing homepages, invalid CRC and encrypted archives. It streams members **in memory only**, hashes each file and compares their filenames/content hashes with the candidate public ZIP; it never writes original files to disk. ZIP mode also emits an `original_zip_sha256` identifier without leaking original site content.

**This is only an integrity/collision check for a copy you already downloaded.** A ZIP that passes validation is NOT proof that Hostinger created a complete, up-to-date backup or that a live restore works. Compare the original screenshot's `assets/`, `images/`, and `index.html` against the ZIP report. If the original site changed since the screenshot, do not proceed based on this older visual evidence. Actual Hostinger file permissions, hidden files, DB backup and original theme parity remain unverified.

Do not paste or upload real production backup ZIPs containing secrets or personal data into a public GitHub repository or issue. Keep a private offline copy; share only sanitized audit summary and screenshots. ZIP mode exits **2 even when validation succeeds**, and never gives a cutover authorisation.

## New independent cutover audit

`python3 scripts/audit_hostinger_cutover.py --current-site-copy PATH_TO_LOCAL_COPY_OF_OLD_PUBLIC_HTML --candidate-public-zip PATH_TO_PUBLIC_ZIP`

The script **never copies, deletes, renames, uploads, deploys or changes** the current site. It fingerprints up to 2500 files/200 MB from a local copy and compares every safe public candidate ZIP entry, without emitting original HTML content or private data. It rejects ZIP traversal, duplicate/unexpected files, symlinks, missing entrypoint or insecure changed Apache config.

It reports (a) preserved `index.html` vs effective PHP homepage-switch risk, (b) content-different same-path assets and `.htaccess` overwrite risk, and (c) all unverifiable external gates. It always returns **exit code 2**, even on successful inspection; a comparison is not an approval for cutover. The report is **not** a verified backup/restore or visual regression test.

The outer GitHub Actions artifact now contains `STOP_READ_FIRST_NO_LIVE_CUTOVER.txt`. The safety warning is **not inside the public ZIP** and must not be extracted as a public web file. The artifact report explicitly says `safe_to_extract_over_current_public_html=false`.

## Founder-friendly next steps (do not upload yet)

1. In hPanel, download and independently validate a complete recoverable backup of the current site's `public_html` and any databases; retain the original `index.html`, `assets/`, `images/` and hidden `.htaccess` if one exists. Do not send Hostinger login/OTP/API key/password in screenshots.
2. Confirm the *actual* PHP-FPM version, `open_basedir`, writable private `storage/`, Apache-compatible rewrite rules, and whether a sibling `overseasjob_private/` folder can safely exist despite the visible `DO_NOT_UPLOAD_HERE` marker. Do not assume the screenshot gives absolute filesystem path.
3. Compare a saved original static site copy with the verified public ZIP using the script above (or ask an assistant to inspect a sanitized copy). Review same-path asset collisions and compare mobile/desktop screenshots and navigation before approving any root homepage change.
4. Verify a complete **restore** of original files and database, not merely whether a downloadable ZIP exists. Use a safe maintenance/rollback procedure if an authorised same-host trial is later approved.
5. Keep `RELEASE_READY=0`, provider legal gates, employer/partner, analytics and ads OFF. Changing `DirectoryIndex` and `noindex` on an already public site is an owner/SEO approval decision, not a filename-only deployment trick.

**Completion criteria:** this guard proves the code detects risks in a supplied local backup; actual `overseasjob.in` permissions, current theme contents, indexing, backup restore, real provider licences, live jobs and public launch remain **UNVERIFIED**.

The original static-theme checker `src/ThemeSafety.php` is preserved. This v0.9.7 guard differs by comparing the **exact generated public ZIP** intended for Hostinger and explicitly modeling the root homepage switch.

## 24 November 2026 — bounded candidate-ZIP verification before CRC scan

The candidate public ZIP is now checked for **compressed archive size (25 MB)**, **total expanded size (25 MB)**, **member count (300)**, safe webroot-only paths, case-insensitive duplicate paths, encrypted members, symlink/non-file entries, suspicious compression ratios and bounded `.htaccess` size **before** decompressing any member. Unlike the earlier implementation, validation no longer invokes whole-archive `ZipFile.testzip()` or reads entire members into memory. Each member is SHA-256 hashed with bounded streaming; Python's ZIP reader verifies CRC on stream completion. Added negative regression fixtures for an oversized ZIP container, excessive uncompressed size, compression-ratio bomb and case-insensitive asset collision. The read-only CLI continues to exit code 2, never returns deployment permission and never touches Hostinger.

These safeguards protect the offline inspection tool against malformed candidate archives; they **do not** prove that the original Hostinger backup is complete, that MySQL can be restored, that the host supports sibling private directories, or that existing website SEO/theme can be cut over safely. Keep Gate 0 blocked until the real evidence is inspected.
