# v0.9.2 — Actual browser verification and form reset correction (23 Nov 2026)

**Evidence type: isolated GitHub Actions browser tests against ephemeral local PHP preview; NOT Hostinger staging, public deployment, production security certification or current static-theme parity.**

## Test evidence

[GitHub Actions run 45075331503](https://github.com/mohdazizshaikh/overseasjob/actions/runs/45075331503) — `real-browser-e2e` job passed **38/38 actual Chromium tests**. This run's deterministic job was expected to fail because the source SHA-256 manifest had not yet been refreshed after adding browser testing; do not describe this whole run as green.

- Desktop actual-browser journeys: home/noindex, honest empty inventory, salary calculation, local Application Tracker, persistence and JSON/ICS downloads, draft guide noindex, interview question/checklist/portfolio/roadmap and downloads, Daily Mission, consent and browser-local deletion.
- Mobile smoke testing in Chromium at 390px: no horizontal overflow on `/`, `/jobs`, `/tools`, `/tools/application-tracker`, `/tools/interview-practice`, `/daily`, `/privacy`. No uncaught JavaScript page errors in tested journeys.
- Real bug found through browser execution: the interview form's `change` event handler reset the selected rubric when its checkbox was clicked, causing a Playwright timeout; corrected by rerunning question selection only for role selector changes. Country checklist handler similarly restricted to country input changes.
- Runner is pinned to `ubuntu-24.04` because GitHub's current `ubuntu-latest` selected Ubuntu 26.04, not supported by the pinned Playwright Chromium distribution.
- Test runner uses `playwright==1.56.0` and Playwright-managed Chromium on a disposable GitHub runner; no paid AI/API or ongoing subscription.

## Release boundaries still open

Actual desktop and mobile browser behavior on the **local preview** is now verified by automated tests, but this does not prove accessibility with screen readers, WCAG contrast, animation, screenshots on real phones, PDF/print layout, actual Hostinger PHP/SSL, private mail delivery, MySQL migration/rollback on the real host, provider API rights, 14 genuine observation days, original live-theme preservation, original guide editorial/legal approvals, advertising approval, employer identity or source job availability.

The live production website, its DNS, existing theme, and external employer/partner flags were not touched. `RELEASE_READY=0`, `ADS_ENABLED=0`, `OWNER_PANEL_ENABLED=0`, `RECRUITER_LITE_ENABLED=0`, and `PARTNER_POSTING_API_ENABLED=0` remain the safe defaults.

## Reproduce in supported development environment

```sh
python3 -m pip install playwright==1.56.0
python3 -m playwright install --with-deps chromium
php -S 127.0.0.1:8809 -t public public/router.php
# in a second terminal:
OJ_E2E_BASE=http://127.0.0.1:8809 python3 tests/browser_e2e.py
```

GitHub CI performs these steps automatically on the `real-browser-e2e` job and isolates the localhost preview. This record is a verification ledger appendix, not a replacement for the canonical source pack or the live release gate.
