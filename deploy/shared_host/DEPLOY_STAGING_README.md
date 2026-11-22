# Shared-host / Hostinger staging handoff — NOT DEPLOYED

**Read this before uploading anything:** The hosting account, site type, PHP restrictions, cron availability, TLS, backup/restore access and supported document-root settings have NOT been verified. This is a **conditional, reversible layout**, not a guaranteed Hostinger configuration. No DNS or production domain should be changed without confirming ownership and existing-site contents. Legacy GitHub `overseas-platform` is an unrelated paid-SaaS architecture; DO NOT overwrite it.

## Preferred option: custom document root

If the actual hosting plan permits the domain/subdomain document root to point to `project/public/`, upload the whole application outside all other sites, set the document root to that `public/` directory only, and serve PHP with Apache rewrite. Files under `src/`, `config/`, `storage/`, `scripts/`, `content/`, `tests/`, `deploy/` must NEVER be web-accessible.

## Alternative: standard `public_html` document root

**Only after inspecting the actual hPanel path and Apache/PHP `open_basedir` permissions:**

```
<authorised domain-home>/
├── overseasjob_private/            # outside the public webroot
│   ├── src/ config/ content/ scripts/ storage/
│   ├── public/index.php            # real controller, no assets required here
│   └── ...
└── public_html/                  # the ONLY publicly served directory
    ├── index.php                  # generated from deploy/shared_host/index.php.template
    ├── .htaccess                  # copy from public/.htaccess
    └── assets/                   # copy from public/assets/*
```

Keep `.env`, credentials, backups and country source research OUTSIDE `public_html`. Copy the supplied `index.php.template` to `public_html/index.php` after verifying sibling path; this wrapper locates the private directory and calls the canonical PHP controller. Do NOT upload `src/`, `scripts/`, `storage/`, or other private application files to `public_html`. Shared-host `open_basedir` may prevent a sibling private folder; if so, **stop** and reassess the plan instead of putting private files in public root.

## Controlled staging procedure

1. Inspect domain and current DNS/site first; take a backup BEFORE any uploads. Use a dedicated test subdomain or isolated site, not the active production root.
2. Verify PHP 8.2+, JSON, OpenSSL, file permissions, CLI PHP and a working schedule mechanism. Do not assume Docker, Redis, FastAPI, persistent Node or extra billing.
3. Deploy **only the six planned utility pages + safe source-view layer** to staging with `RELEASE_READY` unset. Confirm `/robots.txt` denies indexing and `/sitemap.xml` returns 404.
4. Run `scripts/staging_probe.sh https://<your-authorised-staging-domain>` from an agent machine. If private paths, `.htaccess`, or `.env` are accessible, do NOT proceed.
5. Test write access of `overseasjob_private/storage`, scheduling with logged successful CLI `php scripts/sync_jobs.php jobicy` after reviewing provider terms, and the correct source attribution and Apply URLs. Himalayas stays disabled until current response and rights are verified.
6. Record 14 distinct **real** calendar days of source coverage before making daily-match marketing claims. Never manufacture sample counts.
7. Backups in `storage/backups` must be protected and encrypted/access-controlled by the verified hosting environment; `php scripts/backup.php` + `php scripts/restore_drill.php` provide **local file-hash rehearsal only**, not proof of host-level encryption or database recovery.
8. Conduct browser E2E and accessibility/print/export tests on staging; verify original guides, privacy/consent/legal, scam review operation, and the release checklist. Do not set release-evidence flags based only on scripts passing.
9. Keep ads, employer posting, partner API and candidate accounts off. Preserve staged rollback to the last known-good version.

## Nondeveloper founder evidence

Agent must give: **staging URL**, hosted plan limits, provider sync logs with redacted secrets, sample jobs and apply targets, tested browser interactions, private-path probe result, actual backup restore proof, cost changes (`₹0 additional recurring` target), launch blocker list, and specific safe rollback method. There is no confirmed staging URL or public deployment in this package.
