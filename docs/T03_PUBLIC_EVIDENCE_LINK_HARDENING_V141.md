# T03 — Browser-only public evidence-link input hardening (v1.4.1)

**Date:** 2026-11-24 | **Scope:** local Resume & Cover Letter generator, not user identification or web browsing.

## Confirmed gap
`safeEvidenceLink` rejected `localhost` and `.local` hostnames but accepted literal IPv4 loopback/private ranges and IPv6 host literals, while labelling all accepted URLs as public HTTPS evidence links. In standalone exported HTML, a user could follow such a private or loopback link. This is inconsistent with the wider platform's guarded external-link handling.

## Change
- Continue to accept public, absolute HTTPS domain URLs without credentials, custom ports or reserved local hostnames.
- Reject numeric IPv4 literals (including decimal/hex URL canonicalisation), IPv6 literals, `localhost` subdomains and reserved local/internal domains, including trailing-dot aliases.
- Add positive and negative regression checks for loopback/private addresses, IPv6 and obfuscated literal addresses without changing the three original templates, HTML escaping, or private local-only workflow.

## Boundary
This is a **syntactic URL-policy check only**. A public-looking domain can later resolve to a private address or host malicious material; an offline browser cannot establish DNS resolution, real project ownership, employer legitimacy or phishing safety. Don't claim accepted links are trustworthy or verified. No outbound fetch, external API, DNS, paid service or actual Hostinger production change is performed.
