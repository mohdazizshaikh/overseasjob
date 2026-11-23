#!/usr/bin/env python3
"""Regression tests for read-only original-site/candidate cutover checks."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import zipfile

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from build_hostinger_bundle import build
from audit_hostinger_cutover import audit,audit_zip,backup_zip_fingerprints,local_fingerprints,candidate_fingerprints

n=0
def check(name,passed):
    global n
    if not passed: raise AssertionError('FAIL: '+name)
    n+=1;print('PASS: '+name)

with tempfile.TemporaryDirectory(prefix='oj-real-site-copy-test-') as tmp:
    base=Path(tmp)
    current=base/'old_public_html'
    current.mkdir()
    original_html=b'<html><body>Original OverseasJob static homepage</body></html>'
    (current/'index.html').write_bytes(original_html)
    (current/'assets').mkdir()
    (current/'images').mkdir()
    (current/'images'/'logo.png').write_bytes(b'current-original-logo')
    (current/'.htaccess').write_text('DirectoryIndex index.html\nOptions -Indexes\n')
    report=build(base/'candidate_bundle')
    archive=base/'candidate_bundle'/report['public_archive']
    with zipfile.ZipFile(archive) as z:
        asset=next(x for x in z.namelist() if x.startswith('public_html/assets/') and not x.endswith('/'))
    asset_path=asset.removeprefix('public_html/')
    dst=current.joinpath(*asset_path.split('/'))
    dst.parent.mkdir(parents=True,exist_ok=True)
    dst.write_bytes(b'original-website-asset-that-should-not-be-overwritten')
    before=local_fingerprints(current)
    result=audit(current,archive)
    after=local_fingerprints(current)
    check('Audit never changes any existing-site file hash',before==after)
    check('Old index.html remains on disk',result['existing_html_homepage_preserved_as_file'])
    check('NEW index.php + DirectoryIndex overrides effective homepage',
          result['apache_directory_index_would_change_homepage'])
    check('Existing .htaccess conflict detected','.htaccess' in result['different_paths_first_40'])
    check('Original conflicting asset detected',asset_path in result['different_paths_first_40'])
    check('Root homepage risk is explicitly blocking',
          'ROOT_HOME_PAGE_WILL_SWITCH_TO_PHP_DESPITE_INDEX_HTML_BEING_PRESERVED' in result['risk_codes'])
    check('Original theme asset overwrite risk explicitly blocking',
          'EXISTING_THEME_ASSETS_WOULD_BE_REPLACED' in result['risk_codes'])
    check('No live hosting or restore falsely verified',
          result['safe_to_deploy_over_current_public_html'] is False
          and result['hostinger_live_backup_verified'] is False
          and result['real_host_php_and_open_basedir_verified'] is False)
    check('Auditor reports only path names/hashes, never original HTML content',
          original_html.decode() not in json.dumps(result))
    cli=subprocess.run([sys.executable,str(ROOT/'scripts/audit_hostinger_cutover.py'),
                        '--current-site-copy',str(current),
                        '--candidate-public-zip',str(archive)],
                       capture_output=True,text=True,timeout=20)
    check('Founder-facing CLI exits 2 on cutover even when audit succeeds',
          cli.returncode==2 and json.loads(cli.stdout)['cutover_status'].startswith('BLOCKED'))
    empty=base/'empty_site_copy'
    empty.mkdir()
    rejected=False
    try:audit(empty,archive)
    except ValueError:rejected=True
    check('Existing-site directory missing entrypoint fails closed',rejected)
    # Inputs with unsupported structures must fail without ever opening/altering the host.
    weird=current/'assets'/'link'
    try:
        weird.symlink_to(base/'candidate_bundle',target_is_directory=True)
        rejected=False
        try: audit(current,archive)
        except ValueError: rejected=True
        check('Current site symlink is rejected',rejected)
    finally:
        if weird.is_symlink():weird.unlink()
    badzip=base/'traversal.zip'
    with zipfile.ZipFile(badzip,'w') as z:
        z.writestr('public_html/index.php',b'<?php')
        z.writestr('public_html/.htaccess',b'DirectoryIndex index.php\nRequire all denied')
        z.writestr('public_html/assets/../../secrets.php',b'bad')
    rejected=False
    try:audit(current,badzip)
    except ValueError:rejected=True
    check('Path traversal in candidate ZIP rejected',rejected)
    check('Original index.html remains byte-for-byte intact',
          (current/'index.html').read_bytes()==original_html)
    check('Report contains mandatory noindex SEO blocker',
          'PREVIEW_IS_NOINDEX_AND_MAY_AFFECT_EXISTING_SEO' in result['risk_codes'])
    # The founder can use the downloadable public_html ZIP as-is; no extraction.
    def write_backup_zip(out: Path, prefix: str):
        with zipfile.ZipFile(out,'w',compression=zipfile.ZIP_DEFLATED) as z:
            if prefix:
                z.writestr(prefix, b'')
            for path in sorted(current.rglob('*')):
                if path.is_file():
                    z.write(path,prefix+path.relative_to(current).as_posix())
    original_zip=base/'original_hostinger_public_html.zip'
    write_backup_zip(original_zip,'public_html/')
    original_archive_hash=__import__('hashlib').sha256(original_zip.read_bytes()).hexdigest()
    zip_report=audit_zip(original_zip,archive)
    check('Downloaded wrapper public_html ZIP matches local original-file hashes',
          backup_zip_fingerprints(original_zip)==before)
    check('ZIP-vs-candidate comparison reports identical effective homepage and asset risks',
          zip_report['risk_codes']==result['risk_codes']
          and zip_report['different_paths_first_40']==result['different_paths_first_40'])
    check('Backup-zip hash recorded without claiming tested recovery',
          zip_report['original_zip_sha256']==original_archive_hash
          and zip_report['source_archive_does_not_prove_restore'] is True
          and zip_report['safe_to_deploy_over_current_public_html'] is False)
    check('ZIP reader never alters original Hostinger backup bytes',
          __import__('hashlib').sha256(original_zip.read_bytes()).hexdigest()==original_archive_hash)
    zip_cli=subprocess.run([sys.executable,str(ROOT/'scripts/audit_hostinger_cutover.py'),
                            '--current-site-zip',str(original_zip),
                            '--candidate-public-zip',str(archive)],
                           capture_output=True,text=True,timeout=20)
    check('CLI downloaded ZIP mode exits 2 and safely shows collision evidence',
          zip_cli.returncode==2
          and json.loads(zip_cli.stdout)['mode']=='READ_ONLY_LOCAL_BACKUP_ZIP_AUDIT')
    flat=base/'flat_public_html.zip'
    write_backup_zip(flat,'')
    check('Unwrapped root ZIP also accepted when index.html is at root',
          backup_zip_fingerprints(flat)==before)
    check('Original ZIP reports no leaked homepage contents in JSON',
          original_html.decode() not in json.dumps(zip_report))

    def rejects(entries,reason):
        path=base/(reason+'.zip')
        with zipfile.ZipFile(path,'w') as z:
            for n,v in entries:z.writestr(n,v)
        refused=False
        try:backup_zip_fingerprints(path)
        except (ValueError,zipfile.BadZipFile,RuntimeError):refused=True
        check(reason,refused)
    rejects([('public_html/index.html','ok'),('public_html/../secrets.env','bad')],
            'Backup ZIP traversal rejected')
    rejects([('index.html','one'),('index.html','two')],
            'Duplicate backup ZIP member rejected')
    rejects([('public_html/index.html','ok'),('unexpected/evil.txt','bad')],
            'Mixed-root original ZIP rejected')
    rejects([('public_html/nested/index.html','nested only')],
            'Wrong nested docroot rejected')
    symlink=zipfile.ZipInfo('public_html/link.php')
    symlink.create_system=3
    symlink.external_attr=(0o120777<<16)
    rejects([('public_html/index.html','safe'),(symlink,'../../secrets')],
            'Symlink inside downloaded backup ZIP rejected')
    folder=zipfile.ZipInfo('public_html/subfolder/')
    folder.create_system=3
    folder.external_attr=(0o040755<<16)
    valid_dir=base/'backup-with-dir-entries.zip'
    with zipfile.ZipFile(valid_dir,'w') as z:
        z.writestr('public_html/',b'')
        z.writestr(folder,b'')
        z.writestr('public_html/index.html',b'OK')
        z.writestr('public_html/subfolder/test.txt',b'hello')
    check('Normal explicit ZIP directories allowed without disk extraction',
          len(backup_zip_fingerprints(valid_dir))==2)

print(f'{n} no-cutover read-only audit checks passed')
