#!/usr/bin/env python3
"""Prevent outdated Hostinger instructions from re-authorizing unsafe production changes.

Static document assertions only; no runtime hosting evidence or legal approval.
"""
from pathlib import Path

root=Path(__file__).resolve().parents[1]
doc=(root/'deploy/shared_host/DEPLOY_STAGING_README.md').read_text(encoding='utf-8')
tests={
    'same-account testing without paid staging': ('existing production Hostinger account' in doc and 'without a separate paid staging site' in doc),
    'existing static homepage preserved until approved cutover': ('index.html' in doc and 'assets/' in doc and 'images/' in doc),
    'PHP DirectoryIndex homepage-switch risk explicit': ('DirectoryIndex index.php' in doc and 'replace the visible homepage' in doc),
    'no blind ZIP extraction into live root': ('blind extraction' in doc and 'public_html' in doc),
    'private code must stay outside webroot': ('outside every served webroot' in doc),
    'read-only original-site ZIP compare': ('--current-site-zip' in doc and '--candidate-public-zip' in doc),
    'restored original website and DB must be evidenced': ('original live-site and DB recovery evidence' in doc),
    'source live sync globally default OFF': ('SOURCE_LIVE_SYNC_ENABLED=1' in doc and 'defaults are OFF' in doc),
    'provider-specific attestation required': ('PRIVATE owner source-rights attestation' in doc),
    'empty env example does not load CLI runtime': ('.env.example' in doc and 'does NOT automatically configure PHP CLI runtime' in doc),
    'canonical v134 offset pagination not historical cursor': ('offset+limit+totalCount' in doc and 'nextCursor note is historical' in doc),
    'no host preview falsely verified': ('There is no confirmed preview URL' in doc),
    'no paid staging purchase implicitly approved': ('not approved a separate paid staging purchase' in doc),
    'rollback and original-site SEO impact mandatory': ('SEO' in doc and 'tested rollback plan' in doc),
}
for name,ok in tests.items():
    if not ok:raise AssertionError('FAIL Hostinger handoff: '+name)
    print('PASS Hostinger handoff: '+name)
print(f'{len(tests)} safe Hostinger instruction checks passed.')
