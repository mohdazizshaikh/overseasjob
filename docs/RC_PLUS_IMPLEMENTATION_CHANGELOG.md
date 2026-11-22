# Gate 1 RC+ v0.4.0 implementation changelog — 23 November 2026

Canonical Source of Truth remains v1.3.3. No product scope expansion outside already planned T03/T04 and operations. Historical Alpha/Beta/RC test transcripts are kept as non-authoritative archives.

## Delivered

- T03 Resume Kit — three original layouts, safe HTML/text output, browser print, user-authored cover letter, no server CV storage, no paid AI, no invented ATS scores.
- T04 Relocation Planner — exact one-time+reserve+contingency maths with user-supplied assumptions and JSON/print export.
- Fixed static `.htaccess` exposure in local PHP router; static assets now restricted by path/extension. Added read-only 23-case preview HTTP staging probe covering hidden files, source/config paths, anti-framing, CSP, noindex and disabled sitemap.
- Backup creation has unique immutable snapshots, SHA-256 metadata, two retained restore points and isolated restoration/tamper detection. **This is not host-encrypted backup or real production database disaster recovery.**
- Conditional shared-host wrapper for private sibling deployment, plus simulation test, without assuming Hostinger supports that layout.
- Himalayas ingestion now consistently uses documented offset+limit browse API; repeated page signatures block false-success loops while preserving cached jobs. No live API response was obtained.
- Source of Truth no change; updated module_status, documentation and reproducible tests.

## Tests and limitations

56 PHP provider + 21 guidance/moderation + 6 backup + 3 layout + 16 general JS + 24 tracker JS + 22 new-tool JS + 27 HTTP + 23 staging probe + 2 operational = **200/200 passed**.
71/71 existing v1.3.3 specification checks were re-run and passed.
Chrome localhost navigation blocked by administrator; real browser E2E unavailable. Provider domain DNS unavailable. No GitHub or production website modifications.

## Deployment safety

`config/release_evidence.json` remains all-false; public pages noindex. Never mark this build ready only by editing JSON flags. Source rights, Hostinger settings, privacy/legal/editorial approval, full backup recovery and staging browser checks need actual evidence.
