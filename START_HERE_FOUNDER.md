# OverseasJob.in v0.9.0 — Founder status (23 November 2026)

**Current position: code development ongoing; PUBLIC DEPLOYMENT IS NOT APPROVED.** The existing overseasjob.in theme and website are untouched. User prefers that current theme; it will be preserved and separately compared, not overwritten by this project's newer CSS.

## Implemented and tested locally

- Core search/Job Decision Cards, all ten free tools, Career Radar, Daily Mission, privacy controls, moderation and owner panel code from earlier releases are preserved.
- Invite-only employer/partner API, DNS proof, first-party publication approval, recruiter dashboard and MySQL schema from v0.8.0 are preserved behind default-OFF feature gates.
- New **recruiter forgot-password flow**: time-limited one-time mailbox code, bounded attempts, password hashing and forced invalidation of previous browser sessions. Email configuration is required and actual Hostinger inbox delivery is **not** verified.
- New **theme collision audit** is strictly read-only, detects static HTML and PHP/CSS collisions on a **local backup**, and intentionally cannot approve any replacement of live website files. Current static site files/screenshots have not been supplied for visual parity checking.
- New **operational snapshot** gives owner-safe counts and honest missing-cache/backup warnings without leaking report contents.
- Full source ZIP manifest with SHA-256 verification. Unit and HTTP checks run in the local toolchain; the new disposable MySQL tests must be judged by the actual GitHub Actions result.

## Required real-world gates

The current website's original static HTML/CSS and visual screenshots, Hostinger plan and private staging environment, actual emailed confirmation, MySQL migration/rollback on staging, live Jobicy/Himalayas providers, 14 observed days of job inventory, accessibility/mobile browser E2E, editorial/legal approvals and production backup restore are **not independently verified**. The release preflight stays `ready:false`. Real employers and their application data must not be activated until these checks pass.

**Next safe handoff:** obtain a sanitized archive of current `public_html`, deploy this separate new backend to an isolated staging site, compare and port the preferred theme before any public cutover. No need to change current production site now.

GitHub repository: https://github.com/mohdazizshaikh/overseasjob
