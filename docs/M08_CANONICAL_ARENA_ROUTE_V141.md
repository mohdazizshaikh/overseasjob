# Canonical M08 route correction — founder v1.4.1

**Date:** 2026-11-24

## Confirmed discrepancy
The owner contract `15_CANONICAL_MODULE_REQUIREMENTS.md` explicitly specifies `/arena` as the single Daily Career Mission destination; older `/daily` must permanently redirect, never render a divergent experience. The PHP controller previously rendered `/daily` directly, linked `/daily` in navigation/footer, and had no `/arena` page.

## Change
- `public/index.php` now renders the existing authored Daily Mission only at `/arena` with its local-only progress and existing noindex state.
- `/daily` returns HTTP 301 `Location: /arena` before producing HTML; primary navigation and footer link directly to `/arena`.
- `tests/http_v060_smoke.sh` requires `/arena` HTTP 200, old route HTTP 301 with precise destination, and unchanged noindex/local-only markup.
- `tests/browser_e2e.py` checks that a historical `/daily` visit lands at `/arena` and still completes the local-only practice interaction.
- M08 module ledger and README updated. No real Hostinger website was touched.

**Remaining:** editorial/question-bank human signoff, actual accessibility assessment, and all production Hostinger/rights/restore gates remain blocked. Do not infer production approval from green CI.
