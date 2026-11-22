# OverseasJob.in — Local Gate 1 + Gate 2 Tool Preview v0.5.0

**Status: not deployed, not production-ready. Date: 2026-11-23.**  
**Canonical product specification:** `SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.3.3_Source_of_Truth_Project_Pack.zip` — no normative product/API changes in this code release.

## What actually works locally

- Anonymous homepage, job search, evidence-labelled Job Decision Cards, source-correct URLs, safe-empty state and source ingestion adapters using a deliberately empty published cache (no invented jobs).
- **All 10 career tool interfaces are implemented and have local tests:** T01 salary/savings, T02 offer comparison, T03 résumé/cover letter, T04 relocation budget, T05 time zone overlap, T06 application tracker, T07 text-based interview practice, T08 UAE/Germany readiness checklist, T09 self-reported WorkProof portfolio, T10 career roadmap.
- Browser-local profile, manually saved jobs, job application status and calendar `.ics` follow-ups; no server-side candidate identity or automatic application claims.
- Five original **draft** guides, basic report/quarantine moderation workflow, local health/sync scripts, private backup/isolated restore test, simulated shared-host public/private layout, preview noindex safeguards.
- No paid AI, premium subscription services, login requirement for candidates or candidate CV/passport uploads.

**Run locally** (PHP 8.2+, Node.js 18+, curl, POSIX sh):

```bash
sh tests/run.sh
php scripts/launch_preflight.php    # exit 2 / ready:false is EXPECTED
php -S 127.0.0.1:8000 -t public public/router.php
# Open http://127.0.0.1:8000/tools
```

`sh tests/run.sh` reports **267 passed local checks**: PHP 56 + operations 21 + backup 6 + layout 3 + JavaScript 16+24+22+55 + HTTP 39 + preview security probe 23 + Gate0/coverage metadata 2. A successful staging E2E has **not** been demonstrated. The Browser script `tests/browser_e2e.py` is available but Chrome localhost navigation is blocked in the test sandbox.

**Deployment**: Do NOT upload project root into public_html. `deploy/shared_host/DEPLOY_STAGING_README.md` describes a conditional private-file layout. Hostinger PHP/open_basedir/cron/SSL and real backups have NOT been confirmed. Old `rehman2671/overseas-platform` GitHub repository is a different paid-SaaS architecture and remains unchanged.

**Live sources**: The provider cache is empty. Jobicy/Himalayas live network verification and 14-day supply evidence are absent; Himalayas is off by default. Public indexing remains deliberately blocked, and development preview may not be published as production.

**Editorial**: The new exercises, generic checklists and 3 roadmaps are original draft materials, and official/resource links were reviewed online on 2026-11-23. They are not legal, immigration or employer-verified materials. Human editorial review and real accessibility/print checks are still required.

Read `docs/GATE2_TOOLS_V050_CHANGELOG.md` and `START_HERE_FOUNDER.md` for precise validation and next steps. `module_status.json` distinguishes what is implemented from what is deployable.