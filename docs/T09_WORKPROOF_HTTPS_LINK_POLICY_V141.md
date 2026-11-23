# T09 — WorkProof public HTTPS evidence-link policy (v1.4.1)

**Date:** 2026-11-24. **Scope:** original local-only portfolio generator, no network requests.

## Source-to-runtime discrepancy
The founder owner tool contract `17_TOOL_ALGORITHMS_AND_FIXTURES.md` requires safe HTTPS external links and no verification claim. `public/assets/advanced_tools.mjs::safeHttps` rejected selected literal private IP ranges and localhost, but did not consistently reject reserved `.local` / `.internal` / `.localhost` subdomains or custom ports, making its treatment of untrusted user-submitted WorkProof links inconsistent with T03's stricter resume evidence policy.

## Fix and tests
- Use browser-native `new URL()` plus conventional HTTPS DNS hostname syntax, deny private/numeric IP aliases (including hex/decimal/octal host forms), IPv6 literal hosts, local/private reserved suffixes, userinfo credentials and custom ports.
- Keep legitimate DNS-form HTTPS evidence URLs, existing escapeHTML logic, self-reported notice, no scripts/no images/noindex, original 1–10 project bounds and local export unchanged.
- Four new test assertions cover private/numeric endpoints, reserved names, IPv6/custom ports/userinfo and valid public-looking domains.

## Honesty and operating boundaries
This only applies a **syntactic hostname** policy: a plausible public hostname may still resolve to a private IP, be malicious or falsely claim employer authority. This does not independently verify DNS, employer ownership, URLs, actual portfolio evidence, job-provider rights or Hostinger. No live fetch or deployment, no paid service, and source/recruiter/ads/partner/release flags remain OFF.
