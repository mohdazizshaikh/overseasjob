# v1.4.1 Provider HTTP contract — documented endpoints only

**Owner reference:** founder ZIP `16_DATA_AND_OPERATIONS_CONTRACT.md`, SHA-256 `3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9`. This is a **local/CI policy**, not evidence of provider licensing or live data.

## Confirmed gap
`ProviderHttp::allowed` formerly checked HTTPS and the approved hostname but allowed arbitrary API paths. That permitted a hypothetical `himalayas.app/jobs/api/companies/.../jobs/...` request, and the historical unit test labelled simulated HTTP **410 as authoritative closure**, in direct conflict with v1.4.1: Himalayas has no documented per-job 410/404 status API and no public single-job lifecycle endpoint.

## Fail-closed fix
- Current **Jobicy** adapter: only `https://jobicy.com/api/v2/remote-jobs?count=100`.
- Current **Himalayas browse** adapter: only `https://himalayas.app/jobs/api` with no query or the exact `limit=20&offset=N` contract, bounded N. `/jobs/api/search` is separately documented but not implemented by this adapter; do not enable it without its own terms, route and query validator.
- Reject undocumented per-job endpoints, unrecognised query parameters, and unrelated paths at approved hosts **before network transport**.
- `Jobs::himalayasStatusUrl` now always refuses to fabricate an unsupported URL. `Jobs::himalayasStatusDecision` treats all synthetic 200/410 values as **unknown**. Provider active/closed state must come from documented source evidence and expiry, not a hypothetical response.
- Preserve existing exact-host HTTPS/TLS/no-redirect controls, bounded bytes, retries, cache-on-error, source rights OFF and human source review.

## External evidence still required
This change makes no live provider call and cannot establish source licences (including Himalayas API-vs-terms ambiguity), fourteen genuine role-day observations, Hostinger backup/restore, original site preservation or production authority. **Provider activation and public launch remain NO-GO.**

## Historical fixture command safety
The former `scripts/check_himalayas_status.php --fixture=...` also edited local job-cache lifecycle state from hypothetical 200/404/410 fixtures. It now exits with code 3 for **both** live and fixture modes, never reads, changes or falsely "verifies" any stored job. This protects against old scripts/AI agents treating fabricated 410 responses as authentic expiry evidence. HTTP tests confirm the fictional per-job route is denied before contacting a transport; PHP integration tests confirm no fixture/public cache mutation.
