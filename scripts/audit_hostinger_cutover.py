#!/usr/bin/env python3
"""Read-only cutover audit against a LOCAL copy of the current Hostinger public_html.

Checks likely effects of the SHA-256-verified candidate public ZIP. Does not deploy,
change hosting, back up the actual host, or approve production cutover.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import sys
import zipfile

MAX_SITE_FILES = 2500
MAX_SITE_BYTES = 200_000_000
MAX_BUNDLE_BYTES = 25_000_000


def safe_entry(name: str) -> bool:
    if not isinstance(name, str) or not name or '\\' in name or '\0' in name:
        return False
    if name.startswith('/') or len(name) > 600:
        return False
    return all(p not in ('', '.', '..') for p in name.split('/'))


def local_fingerprints(site: Path) -> dict[str, str]:
    if site.is_symlink() or not site.is_dir():
        raise ValueError('Existing-site backup must be a regular local directory')
    base = site.resolve(strict=True)
    files: dict[str, str] = {}
    count_bytes = 0
    for parent, folders, names in os.walk(site, followlinks=False):
        for name in folders:
            if (Path(parent) / name).is_symlink():
                raise ValueError('Symlinked directory in existing-site backup')
        for name in names:
            path = Path(parent) / name
            if path.is_symlink() or not path.is_file():
                raise ValueError('Existing-site backup contains a link or nonregular file')
            relative = path.relative_to(site).as_posix()
            if not safe_entry(relative) or relative in files:
                raise ValueError('Unsafe or duplicate existing-site path')
            count_bytes += path.stat().st_size
            if count_bytes > MAX_SITE_BYTES or len(files) >= MAX_SITE_FILES:
                raise ValueError('Existing-site backup exceeds scan safety bounds')
            h = hashlib.sha256()
            with path.open('rb') as stream:
                while block := stream.read(1024 * 1024):
                    h.update(block)
            files[relative] = h.hexdigest()
    if not ('index.html' in files or 'index.php' in files):
        raise ValueError('Existing-site copy lacks recognised homepage')
    return dict(sorted(files.items()))


def candidate_fingerprints(public_zip: Path) -> tuple[dict[str, str], bytes]:
    if public_zip.is_symlink() or not public_zip.is_file():
        raise ValueError('Candidate public ZIP must be a regular local file')
    proposed: dict[str, str] = {}
    htaccess = b''
    total = 0
    with zipfile.ZipFile(public_zip) as z:
        if z.testzip() is not None:
            raise ValueError('Candidate public ZIP fails CRC')
        for item in z.infolist():
            if item.is_dir():
                raise ValueError('Candidate public ZIP must not include directory entries')
            if not safe_entry(item.filename) or not item.filename.startswith('public_html/'):
                raise ValueError('Candidate public ZIP path is unsafe or outside webroot')
            name = item.filename[len('public_html/'):]
            if not safe_entry(name) or name in proposed:
                raise ValueError('Candidate public ZIP has unsafe/duplicate path')
            if not (name in ('index.php', '.htaccess') or name.startswith('assets/')):
                raise ValueError('Unexpected file in public preview bundle')
            mode = (item.external_attr >> 16) & 0o170000
            if mode not in (0, 0o100000):
                raise ValueError('Candidate ZIP contains a nonregular file')
            total += item.file_size
            if total > MAX_BUNDLE_BYTES or len(proposed) >= 300:
                raise ValueError('Candidate public bundle exceeds size/file bounds')
            raw = z.read(item)
            proposed[name] = hashlib.sha256(raw).hexdigest()
            if name == '.htaccess':
                htaccess = raw
    if 'index.php' not in proposed or '.htaccess' not in proposed:
        raise ValueError('Candidate entrypoint or Apache config missing')
    if b'DirectoryIndex index.php' not in htaccess or b'Require all denied' not in htaccess:
        raise ValueError('Candidate Apache configuration not expected secure bootstrap')
    return dict(sorted(proposed.items())), htaccess


def audit(site: Path, candidate: Path) -> dict:
    current = local_fingerprints(site)
    new, _ = candidate_fingerprints(candidate)
    same = sorted(p for p in new if p in current and new[p] == current[p])
    changes = sorted(p for p in new if p in current and new[p] != current[p])
    additions = sorted(p for p in new if p not in current)
    html_stays = 'index.html' in current and 'index.html' not in new
    homepage_switch = html_stays and 'index.php' in new
    style_collisions = [p for p in changes if p.startswith('assets/')]
    risks = [
        'NO_HOSTINGER_PATH_OR_PHP_FPM_PERMISSION_EVIDENCE',
        'NO_VERIFIED_FULL_HOST_BACKUP_AND_RESTORE',
        'NO_ORIGINAL_THEME_PARITY_OR_OWNER_CUTOVER_APPROVAL',
        'PREVIEW_IS_NOINDEX_AND_MAY_AFFECT_EXISTING_SEO'
    ]
    if homepage_switch:
        risks.insert(0, 'ROOT_HOME_PAGE_WILL_SWITCH_TO_PHP_DESPITE_INDEX_HTML_BEING_PRESERVED')
    if 'index.php' in changes:
        risks.insert(0, 'EXISTING_INDEX_PHP_WOULD_BE_OVERWRITTEN')
    if '.htaccess' in changes:
        risks.insert(0, 'EXISTING_APACHE_HTACCESS_WOULD_BE_OVERWRITTEN')
    if style_collisions:
        risks.insert(0, 'EXISTING_THEME_ASSETS_WOULD_BE_REPLACED')
    return {
        'mode': 'READ_ONLY_LOCAL_COPY_AUDIT',
        'existing_files_scanned': len(current),
        'candidate_public_files': len(new),
        'existing_html_homepage_preserved_as_file': html_stays,
        'apache_directory_index_would_change_homepage': homepage_switch,
        'same_path_identical': len(same),
        'same_path_different': len(changes),
        'different_paths_first_40': changes[:40],
        'existing_assets_with_different_candidate_content_first_40': style_collisions[:40],
        'new_files': len(additions),
        'risk_codes': risks,
        'hostinger_live_backup_verified': False,
        'real_host_php_and_open_basedir_verified': False,
        'real_site_visual_parity_verified': False,
        'noindex_seo_impact_approved': False,
        'safe_to_deploy_over_current_public_html': False,
        'cutover_status': 'BLOCKED_UNTIL_REAL_HOST_BACKUP_THEME_AND_PERMISSION_EVIDENCE',
        'note': 'Checks a supplied local copy, not the actual live hosting or reversible restoration.'
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--current-site-copy', required=True, type=Path)
    parser.add_argument('--candidate-public-zip', required=True, type=Path)
    args = parser.parse_args()
    try:
        result = audit(args.current_site_copy, args.candidate_public_zip)
        print(json.dumps(result, indent=2, sort_keys=True))
        return 2  # Comparing two directories is NEVER authorisation for a live cutover.
    except (OSError, ValueError, KeyError, zipfile.BadZipFile, RuntimeError) as exc:
        print('READ-ONLY CUTOVER AUDIT FAILED SAFELY: ' + str(exc), file=sys.stderr)
        return 2


if __name__ == '__main__':
    raise SystemExit(main())
