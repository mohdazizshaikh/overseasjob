# v0.9.10 — M16 isolated browser keyboard, form labels, reflow, print and visual-baseline QA

**Release status:** GitHub CI-only candidate. Does not assert the current live Hostinger site was inspected, restored, re-themed or accessibility-certified.

## Verified code coverage added

`tests/browser_a11y_print.py` extends the existing Chromium suite from transactional career-tool functionality to **targeted accessibility and responsive presentation**. In isolated GitHub CI, it visits 16 public routes and asserts:

- Exactly one `main#main` landmark and one visible semantic `h1` for each page.
- Correct document `lang=en`, labels/accessible names on all visible form inputs and controls, nonempty button names, `alt` attributes on images.
- First keyboard Tab reaches the visible skip-to-main link with a clearly sized focus outline.
- **320 CSS px reflow** with no horizontal scroll on the same 16 routes, beyond the earlier 390 px mobile checks.
- Meaningful roadmap **print media** behavior (navigation hidden and unclipped preview).
- Homepage, job search and safety help page still render server content with JavaScript disabled.
- No uncaught desktop or 320 px mobile script errors.

The job produces eight reproducible **candidate-site** screenshots (desktop/mobile home, jobs, tools, guides) as GitHub Actions artifact `overseasjob-isolated-desktop-mobile-visual-baseline` for 14 days. No user accounts or real candidate data are included. This is a new frontend baseline, **not** visual parity proof with the founder's existing `public_html/index.html/assets/images` theme; the real original-site files/screenshots remain unavailable for a byte/visual comparison.

## Scope boundary

Automated targeted assertions are **not** equivalent to WCAG 2.2 AA certification, a screen-reader/manual keyboard audit, a complete color contrast scan, actual device and network speed measurements, print-to-paper human QA, original-theme visual parity, accessibility for real job cards/provider data, or live Hostinger deployment E2E.

M16 remains `blocked_with_reason` until a real authorised hosting environment, original-theme backup, access configuration and independent assistive-technology/visual review are evidenced. New CSS fixes, if CI reveals them, must retain existing static-site production theme unchanged; do not ship UI to the current public root without owner-approved rollback.

No paid third-party dependencies are required; the Playwright Chromium runtime is already installed by existing CI. All employer/partner, ads, analytics and production release gates stay OFF.
