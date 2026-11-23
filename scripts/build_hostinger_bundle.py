#!/usr/bin/env python3
"""Build two isolated Hostinger preview archives from the verified release manifest.

No hosting/network calls, no production cutover, no paid service.
"""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import stat
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'RELEASE_MANIFEST.json'
BOOTSTRAP = 'deploy/shared_host/index.php.template'
FIXED_DATE = (2026, 11, 23, 0, 0, 0)


def safe_path(name: str) -> bool:
    if not name or '\\' in name or '\x00' in name or '\r' in name or '\n' in name:
        return False
    return (not PurePosixPath(name).is_absolute()
            and all(p not in ('', '.', '..') for p in name.split('/')))


def verified_sources() -> dict[str, bytes]:
    if MANIFEST.is_symlink() or not MANIFEST.is_file():
        raise ValueError('Missing or symlinked release manifest')
    d = json.loads(MANIFEST.read_text())
    records = d['files']
    if not isinstance(records, list) or len(records) != d['file_count_excluding_manifest']:
        raise ValueError('Release manifest count mismatch')
    files = {}
    for row in records:
        name = row['path']
        if not isinstance(name, str) or not safe_path(name) or name in files:
            raise ValueError(f'Unsafe/duplicate entry: {name!r}')
        file = ROOT.joinpath(*name.split('/'))
        if not file.is_file() or file.is_symlink():
            raise ValueError(f'Missing/symlinked source: {name}')
        parent = file.parent
        while parent != ROOT:
            if parent.is_symlink():
                raise ValueError(f'Symlinked parent for {name}')
            parent = parent.parent
        raw = file.read_bytes()
        if len(raw) != row['bytes'] or hashlib.sha256(raw).hexdigest() != row['sha256']:
            raise ValueError(f'Manifest mismatch: {name}')
        files[name] = raw
    if BOOTSTRAP not in files or 'public/.htaccess' not in files:
        raise ValueError('Missing hosting entrypoints')
    for marker in (b'../overseasjob_private', b'OJ_PROJECT_ROOT'):
        if marker not in files[BOOTSTRAP]:
            raise ValueError('Private sibling bootstrap contract changed')
    for marker in (b'DirectoryIndex index.php', b'Require all denied', b'RewriteRule'):
        if marker not in files['public/.htaccess']:
            raise ValueError('Public Apache security contract changed')
    assets = [p for p in files if p.startswith('public/assets/')]
    if not assets:
        raise ValueError('No public assets present')
    for name in assets:
        if (name.rsplit('/', 1)[-1].startswith('.')
                or Path(name).suffix not in {'.mjs', '.js', '.css', '.png', '.jpg', '.jpeg', '.svg', '.webp'}):
            raise ValueError(f'Unexpected public asset: {name}')
    return files


def archive(path: Path, content: dict[str, bytes]) -> str:
    with zipfile.ZipFile(path, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for name, raw in sorted(content.items()):
            if not safe_path(name):
                raise ValueError(f'Unsafe archive entry: {name}')
            info = zipfile.ZipInfo(name, FIXED_DATE)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            z.writestr(info, raw, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    return hashlib.sha256(path.read_bytes()).hexdigest()


def build(output: Path) -> dict:
    source = verified_sources()
    private = {'overseasjob_private/' + name: raw for name, raw in source.items()}
    private['overseasjob_private/RELEASE_MANIFEST.json'] = MANIFEST.read_bytes()
    public = {'public_html/index.php': source[BOOTSTRAP],
              'public_html/.htaccess': source['public/.htaccess']}
    public.update({'public_html/' + name[len('public/'):]: raw
                   for name, raw in source.items() if name.startswith('public/assets/')})
    if any(name.startswith(('public_html/src/', 'public_html/storage/', 'public_html/config/',
                            'public_html/database/', 'public_html/scripts/')) for name in public):
        raise ValueError('Private source would leak to webroot')
    if 'public_html/index.html' in public:
        raise ValueError('Current live homepage must not be overwritten')
    output.mkdir(parents=True, exist_ok=True)
    prv = output / 'overseasjob_private_verified.zip'
    pub = output / 'overseasjob_public_preview_NOT_FOR_BLIND_OVERWRITE.zip'
    report = {
        'mode': 'PACKAGE_ONLY_NEVER_DEPLOYS',
        'source_release': json.loads(MANIFEST.read_text())['release'],
        'manifest_files_verified': len(source),
        'private_archive': prv.name, 'private_entries': len(private),
        'public_archive': pub.name, 'public_entries': len(public),
        'private_sha256': archive(prv, private),
        'public_sha256': archive(pub, public),
        'live_host_modified': False,
        'production_cutover_approved': False,
        'hostinger_path_and_original_theme_backup_required': True,
        'safe_to_extract_over_current_public_html': False,
        'existing_index_html_does_not_prevent_php_homepage_switch': True,
        'original_site_cutover_audit_required': True
    }
    stop_note = """STOP — THIS IS NOT AN APPROVED LIVE-HOST DEPLOYMENT

These two ZIP archives are SHA-256-verified PACKAGE PREVIEWS only.
Your existing Hostinger public_html has index.html, assets/ and images/.

CRITICAL: Even though index.html is NOT deleted or included, the public ZIP
adds index.php plus .htaccess containing 'DirectoryIndex index.php'.
On an Apache-compatible Hostinger site, this can SWITCH THE LIVE HOMEPAGE
to the new PHP app immediately. Existing CSS/assets paths may also be overwritten.

Do not extract EITHER ZIP into the existing public_html by guessing.
Do not upload the private ZIP inside any public webroot.
Do not extract the outer GitHub Actions artifact ZIP as a website release.
Do not assume the DO_NOT_UPLOAD_HERE marker permits a sibling folder.

First obtain a recoverable full original-site AND database backup, verify the
real PHP private-directory/open_basedir location, and compare actual file
collisions with a local copy using:
python3 scripts/audit_hostinger_cutover.py --current-site-copy PATH_TO_SAVED_PUBLIC_HTML --candidate-public-zip PATH_TO_PUBLIC_ZIP

The audit ALWAYS exits 2 and cannot authorise cutover by itself.
Preview pages are deliberately noindex and can affect existing site SEO.
The original theme, on-host browser behavior, legal rights, real jobs,
off-site restore, HTTPS/cron and public release must be verified separately.
"""
    (output / 'STOP_READ_FIRST_NO_LIVE_CUTOVER.txt').write_text(stop_note)
    (output / 'bundle_integrity_report.json').write_text(json.dumps(report, indent=2) + '\n')
    return report


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, default=ROOT / 'artifacts' / 'hostinger-preview')
    args = parser.parse_args()
    try:
        print(json.dumps(build(args.output.resolve()), indent=2))
        return 0
    except (OSError, KeyError, TypeError, ValueError, zipfile.BadZipFile) as e:
        print(f'Refused unsafe preview bundle: {e}', file=sys.stderr)
        return 2


if __name__ == '__main__':
    raise SystemExit(main())
