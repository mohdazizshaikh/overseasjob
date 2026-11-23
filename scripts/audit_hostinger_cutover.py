#!/usr/bin/env python3
"""Read-only cutover audit against a LOCAL copy of the current Hostinger public_html.

Checks likely effects of a verified candidate ZIP vs a local folder or downloaded original ZIP. Does not deploy,
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
MAX_BUNDLE_FILES = 300
MAX_HTACCESS_BYTES = 65_536


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


def backup_zip_fingerprints(source_zip: Path) -> dict[str, str]:
    """Audit a downloaded public_html ZIP without extracting or writing files.

    Accept either files rooted directly in the ZIP or one literal public_html/
    wrapper. Refuse additional/nested roots rather than guessing Hostinger paths.
    CRC is verified while streaming every member; never trust ZIP filenames.
    """
    if source_zip.is_symlink() or not source_zip.is_file():
        raise ValueError('Original-site ZIP must be a regular local file, not a symlink')
    if source_zip.stat().st_size > MAX_SITE_BYTES:
        raise ValueError('Original-site compressed ZIP exceeds 200 MB safety bound')
    files: dict[str, str] = {}
    seen: set[str] = set()
    byte_total = 0
    with zipfile.ZipFile(source_zip) as z:
        items = z.infolist()
        if len(items) > MAX_SITE_FILES + 400:
            raise ValueError('Original-site ZIP has too many entries')
        # A single optional public_html/ folder wrapper is recognised.
        wrapped = any(x.filename == 'public_html/' for x in items) or (
            not any(x.filename in ('index.html', 'index.php') for x in items)
            and any(x.filename in ('public_html/index.html', 'public_html/index.php') for x in items)
        )
        prefix = 'public_html/' if wrapped else ''
        for entry in items:
            original = entry.filename
            # ZIP dir names end in /; validate underlying path without the slash.
            rawname = original[:-1] if entry.is_dir() else original
            if not safe_entry(rawname) or (prefix and not original.startswith(prefix)):
                raise ValueError('Unsafe, unexpected-root or mixed-root original ZIP path')
            if entry.flag_bits & 0x1:
                raise ValueError('Encrypted original-site archive cannot be audited')
            mode = (entry.external_attr >> 16) & 0o170000
            if entry.is_dir():
                if mode not in (0, 0o040000):
                    raise ValueError('Non-directory filesystem object in original-site ZIP')
                continue
            if mode not in (0, 0o100000):
                raise ValueError('Nonregular file/symlink in original-site ZIP')
            if entry.file_size < 0:
                raise ValueError('Invalid ZIP entry size')
            name = original[len(prefix):] if prefix else original
            if not safe_entry(name) or name.casefold() in seen:
                raise ValueError('Duplicate/unsafe original-site ZIP file')
            seen.add(name.casefold())
            byte_total += entry.file_size
            if byte_total > MAX_SITE_BYTES or len(files) >= MAX_SITE_FILES:
                raise ValueError('Original-site ZIP exceeds 2500 files/200 MB bounds')
            if entry.file_size > 1024*1024 and (
                    entry.compress_size == 0 or entry.file_size > 400*entry.compress_size):
                raise ValueError('Original ZIP compression ratio unsafe')
            hashvalue = hashlib.sha256()
            actual = 0
            with z.open(entry, 'r') as stream:
                while chunk := stream.read(1024*1024):
                    actual += len(chunk)
                    if actual > entry.file_size or actual > MAX_SITE_BYTES:
                        raise ValueError('Original ZIP expands beyond declared safety bounds')
                    hashvalue.update(chunk)
            if actual != entry.file_size:
                raise ValueError('Original ZIP size mismatch')
            files[name] = hashvalue.hexdigest()
    if not ('index.html' in files or 'index.php' in files):
        raise ValueError('ZIP is not a public_html backup: missing index.html/index.php at root')
    return dict(sorted(files.items()))


def candidate_fingerprints(public_zip: Path) -> tuple[dict[str, str], bytes]:
    """Bounded, read-only validation of a public preview ZIP before hashing it.

    Inspect ALL central-directory entries and declared sizes before decompressing.
    ZIP CRC validation happens as each bounded stream reaches EOF; never call
    ZipFile.testzip() or ZipFile.read() on unbounded untrusted input.
    """
    if public_zip.is_symlink() or not public_zip.is_file():
        raise ValueError('Candidate public ZIP must be a regular local file')
    if public_zip.stat().st_size > MAX_BUNDLE_BYTES:
        raise ValueError('Candidate public ZIP compressed archive exceeds 25 MB')
    proposed: dict[str, str] = {}
    htaccess = b''
    with zipfile.ZipFile(public_zip) as z:
        entries = z.infolist()
        if not entries or len(entries) > MAX_BUNDLE_FILES:
            raise ValueError('Candidate public ZIP exceeds file count safety bound')
        checked: list[tuple[str, zipfile.ZipInfo]] = []
        seen: set[str] = set()
        total = 0
        for item in entries:
            if item.is_dir():
                raise ValueError('Candidate public ZIP must not include directory entries')
            if not safe_entry(item.filename) or not item.filename.startswith('public_html/'):
                raise ValueError('Candidate public ZIP path is unsafe or outside webroot')
            name = item.filename[len('public_html/'):]
            if not safe_entry(name) or name.casefold() in seen:
                raise ValueError('Candidate public ZIP has unsafe/duplicate path')
            seen.add(name.casefold())
            if not (name in ('index.php', '.htaccess') or name.startswith('assets/')):
                raise ValueError('Unexpected file in public preview bundle')
            if item.flag_bits & 0x1:
                raise ValueError('Encrypted candidate public archive cannot be audited')
            mode = (item.external_attr >> 16) & 0o170000
            if mode not in (0, 0o100000):
                raise ValueError('Candidate ZIP contains a nonregular file')
            if item.file_size < 0 or item.compress_size < 0:
                raise ValueError('Candidate ZIP has invalid member size')
            total += item.file_size
            if total > MAX_BUNDLE_BYTES:
                raise ValueError('Candidate public bundle exceeds expanded 25 MB bound')
            if item.file_size > 1024 * 1024 and (
                    item.compress_size == 0 or item.file_size > 400 * item.compress_size):
                raise ValueError('Candidate ZIP compression ratio unsafe')
            if name == '.htaccess' and item.file_size > MAX_HTACCESS_BYTES:
                raise ValueError('Candidate Apache configuration exceeds audit bound')
            checked.append((name, item))
        if not {'index.php', '.htaccess'}.issubset(seen):
            raise ValueError('Candidate entrypoint or Apache config missing')
        # No decompression occurs until every entry passes the structural limits.
        for name, item in checked:
            digest = hashlib.sha256()
            consumed = 0
            apache = bytearray() if name == '.htaccess' else None
            with z.open(item, 'r') as stream:
                while chunk := stream.read(64 * 1024):
                    consumed += len(chunk)
                    if consumed > item.file_size or consumed > MAX_BUNDLE_BYTES:
                        raise ValueError('Candidate ZIP member expanded beyond bounds')
                    digest.update(chunk)
                    if apache is not None:
                        apache.extend(chunk)
            if consumed != item.file_size:
                raise ValueError('Candidate ZIP declared and actual size mismatch')
            proposed[name] = digest.hexdigest()
            if apache is not None:
                htaccess = bytes(apache)
    if b'DirectoryIndex index.php' not in htaccess or b'Require all denied' not in htaccess:
        raise ValueError('Candidate Apache configuration not expected secure bootstrap')
    return dict(sorted(proposed.items())), htaccess

def compare_fingerprints(current: dict[str, str], candidate: Path, mode: str) -> dict:
    new, _ = candidate_fingerprints(candidate)
    same = sorted(p for p in new if p in current and new[p] == current[p])
    changes = sorted(p for p in new if p in current and new[p] != current[p])
    additions = sorted(p for p in new if p not in current)
    html_stays = 'index.html' in current and 'index.html' not in new
    homepage_switch = html_stays and 'index.php' in new
    style_collisions = [p for p in changes if p.startswith('assets/')]
    has_original_assets = any(p.startswith('assets/') for p in current)
    has_original_images = any(p.startswith('images/') for p in current)
    risks = [
        'NO_HOSTINGER_PATH_OR_PHP_FPM_PERMISSION_EVIDENCE',
        'NO_VERIFIED_FULL_HOST_BACKUP_AND_RESTORE',
        'NO_ORIGINAL_THEME_PARITY_OR_OWNER_CUTOVER_APPROVAL',
        'PREVIEW_IS_NOINDEX_AND_MAY_AFFECT_EXISTING_SEO'
    ]
    if not has_original_assets:
        risks.insert(0, 'ORIGINAL_ASSETS_FROM_SCREENSHOT_NOT_PRESENT_IN_SUPPLIED_COPY')
    if not has_original_images:
        risks.insert(0, 'ORIGINAL_IMAGES_FROM_SCREENSHOT_NOT_PRESENT_IN_SUPPLIED_COPY')
    if homepage_switch:
        risks.insert(0, 'ROOT_HOME_PAGE_WILL_SWITCH_TO_PHP_DESPITE_INDEX_HTML_BEING_PRESERVED')
    if 'index.php' in changes:
        risks.insert(0, 'EXISTING_INDEX_PHP_WOULD_BE_OVERWRITTEN')
    if '.htaccess' in changes:
        risks.insert(0, 'EXISTING_APACHE_HTACCESS_WOULD_BE_OVERWRITTEN')
    if style_collisions:
        risks.insert(0, 'EXISTING_THEME_ASSETS_WOULD_BE_REPLACED')
    return {
        'mode': mode,
        'existing_files_scanned': len(current),
        'original_screenshot_assets_present': has_original_assets,
        'original_screenshot_images_present': has_original_images,
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


def audit(site: Path, candidate: Path) -> dict:
    return compare_fingerprints(local_fingerprints(site), candidate,
                                'READ_ONLY_LOCAL_COPY_AUDIT')


def audit_zip(source_zip: Path, candidate: Path) -> dict:
    report = compare_fingerprints(backup_zip_fingerprints(source_zip), candidate,
                                  'READ_ONLY_LOCAL_BACKUP_ZIP_AUDIT')
    # Cryptographic identifier is useful to match the exact snapshot later;
    # it is NOT proof a Hostinger backup is complete or restorable.
    digest = hashlib.sha256()
    with source_zip.open('rb') as stream:
        while chunk := stream.read(1024*1024):
            digest.update(chunk)
    report['original_zip_sha256'] = digest.hexdigest()
    report['source_archive_does_not_prove_restore'] = True
    return report


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    source = parser.add_mutually_exclusive_group(required=True)
    source.add_argument('--current-site-copy', type=Path,
                        help='already extracted local public_html directory')
    source.add_argument('--current-site-zip', type=Path,
                        help='downloaded, original public_html ZIP; never extracted by tool')
    parser.add_argument('--candidate-public-zip', required=True, type=Path)
    args = parser.parse_args()
    try:
        result = (audit_zip(args.current_site_zip, args.candidate_public_zip)
                  if args.current_site_zip is not None
                  else audit(args.current_site_copy, args.candidate_public_zip))
        print(json.dumps(result, indent=2, sort_keys=True))
        return 2  # Comparing two directories is NEVER authorisation for a live cutover.
    except (OSError, ValueError, KeyError, zipfile.BadZipFile, RuntimeError) as exc:
        print('READ-ONLY CUTOVER AUDIT FAILED SAFELY: ' + str(exc), file=sys.stderr)
        return 2


if __name__ == '__main__':
    raise SystemExit(main())
