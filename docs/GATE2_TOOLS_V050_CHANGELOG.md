# Gate 1 + Gate 2 Utilities v0.5.0 — Implementation Changelog

**Date:** 2026-11-23  
**Source of truth:** v1.3.3 canonical pack (unchanged)  
**Deployment:** NONE. All engineering changes are isolated in a new ZIP; GitHub's old paid-SaaS repository and any live domain were not modified.

## New functional work (T07–T10)

- **T07 Original Interview Practice:** 12 original prompts across IT Support, Customer Support and Administration; structured objective/answer guidance, self-assessment rubric, timer, session-only draft answer and downloadable plain-text export. Text-only mode works with no mic; no LLM, ATS score or hiring prediction.
- **T08 Country Readiness Checklist:** UAE and Germany generic preparation tasks (10) and separately linked official portals. Dated 2026-11-23 reference check, `review_by` 2027-02-21, visible stale-source warning, separate browser-local progress for each country, bounded JSON import/export/delete. No visa eligibility determination, no uploads.
- **T09 WorkProof Portfolio:** User-entered 1–10 self-reported projects, escaped standalone printable HTML with CSP, HTTPS-only optional reference links, local preview, append/remove-last/reset. No badges, candidate cloud profile or server upload.
- **T10 Career Roadmap:** Three curated practice tracks (IT Support, Customer Support, Junior Web Developer), prerequisites and verified learning starting points, 30/60/90 day milestones, deterministic four-week stage budgets (3 h/week → 12 h/stage), editable notes, export JSON and print. No paid AI or certification guarantee.
- The `/tools` index now routes to ten local utilities. New content JSON stays outside public webroot and is embedded as escaped non-executable JSON when needed.
- Browser-local tool forms use `method="dialog"` to avoid fallback HTTP submission of user input when JavaScript is unavailable; no-JS notices explain local-only operation. Actual no-JS browser behaviour must still be tested on staging.

## Verification (not production certification)

| Suite | Checks passed |
|---|---:|
| PHP job adapters and source fixtures | 56 |
| Operations, original guide drafts and moderation | 21 |
| Isolated backup/restore | 6 |
| Simulated private hosting layout | 3 |
| T01/T02/T05 JavaScript | 16 |
| T06 application tracker JavaScript | 24 |
| T03/T04 JavaScript | 22 |
| T07–T10 pure-function and content fixture checks | 55 |
| HTTP navigation / development protection | 39 |
| Preview HTTP/security probe | 23 |
| Preflight / factual coverage metadata | 2 |
| **Total local checks** | **267** |

The v1.3.3 Source of Truth package retains its independently validated 71/71 documentation checks. The current release has not changed the product/API specification or source semantics.

**Browser E2E:** actual Chromium test attempted and BLOCKED (`ERR_BLOCKED_BY_ADMINISTRATOR`); new complete T07–T10 click-through steps added to `tests/browser_e2e.py` but not claimed passed. PDF printing, assistive tech behaviour and real user data persistence need staging testing.

**Live data/hosting:** `jobs.json` remains empty by design. Real Jobicy/Himalayas sync, source rights recheck, 14 distinct calendar days of role coverage, Hostinger plan/SSL/cron/private path, SQL migrations, production-safe moderation and live recovery remain *unverified*. Himalayas is disabled by default. No fake job statistics, revenue or AdSense approval.

**Editorial work:** 12 interview questions, country checklists and 3 roadmaps are original authored draft assets; links were checked against official publisher sites on 2026-11-23. They have **not** undergone independent legal/editorial approval. New content routes intentionally retain `noindex,nofollow` even if a future release gate is enabled; flip only after meaningful content review.

## Next agent action

1. Inspect actual Hostinger account and pre-existing repository(s) without replacing any existing sites; verify PHP, cron, storage, SSL and backups.
2. Use staging to run `sh tests/run.sh`, then `python tests/browser_e2e.py` against an actual staging host (update BASE safely); inspect rendering and generated PDFs.
3. Run permitted live provider tests, record real supply evidence over 14 days, validate source licences and statuses. Keep Himalayas off until policy and exact API response are evidenced.
4. Review original content and site policies, demonstrate restore, validate source-correct external Apply, and run an accessibility/security pass.
5. Only then revisit `scripts/launch_preflight.php` and deployment evidence. Production release must stay blocked until its real-world gates pass.
