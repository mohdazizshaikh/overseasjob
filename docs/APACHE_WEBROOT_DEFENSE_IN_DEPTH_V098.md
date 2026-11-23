# v0.9.8 — Apache/LiteSpeed-compatible webroot defense in depth

**Status:** isolated GitHub Apache test only; the real Hostinger public_html still must NOT receive private project files or ZIP archives.

## Why the change was needed

The previous `public/.htaccess` blocked common `.env`, `.json`, `.md` and `.sql` suffixes, but it did **not** explicitly deny `.zip` / `.tar` / `.gz` backup archives or physically uploaded PHP files under internal folders such as `src/` and `scripts/`. Users could accidentally unpack a full repository or backup into `public_html`; the application packaging already prevents this by default, but the webserver should deny typical exposures too.

## Hardened rules

- `<FilesMatch>` now denies common backups, sensitive data and secret formats: `.zip`, `.tar`, `.tgz`, `.gz`, `.7z`, `.rar`, `.csv`, `.ndjson`, `.sqlite`, `.db`, `.pem`, `.key`, `.p12`, `.pfx`, `.phar`, plus the prior dotfile/config extensions.
- `mod_rewrite` returns HTTP **403** for direct requests into `src/`, `storage/`, `scripts/`, `tests/`, `config/`, `database/`, `content/`, `deploy/`, `docs/`, `vendor/`, `SOURCE_OF_TRUTH_REFERENCE/`, `.git/` and `.github/`, including physically present PHP files. These rules run **before** the usual route fallback.
- Public `index.php`, `robots.txt` and `assets/*.css/js` stay accessible. `DirectoryIndex index.php` continues to change the effective homepage even if the old `index.html` is not deleted. v0.9.7 no-cutover guard remains mandatory.

The security checks use `tests/apache_webroot_security.sh`, which **refuses to run unless `GITHUB_ACTIONS=true`**. It uses a disposable GitHub runner's Apache server, intentionally creates fake private files under `/var/www/html`, and checks actual HTTP 403/200 responses, rewrite fallback, browser security headers and homepage priority. No applicant data or real host files are involved.

**Failure boundaries:** if the real Hostinger plan ignores `.htaccess`, lacks rewrite modules, has different PHP-FPM or document root, or the `.htaccess` is uploaded after other private files, this test does not prevent exposure. `mod_rewrite` being wrapped in `<IfModule>` is compatibility, not an assertion it is available. Hardcoded regexes are an additional safeguard, never a substitute for keeping `overseasjob_private/` outside the actual public webroot. The independent read-only cutover comparison, complete real backup and restore, live theme/SEO review and owner decision remain blocked.

When deployed on **a truly authorised host**, repeat HTTP private-file probes under current provider/server config. The CI job does **not** certify actual Hostinger configuration, real job feed permission, third-party licences, recruiter identity, external backups or public launch readiness.
