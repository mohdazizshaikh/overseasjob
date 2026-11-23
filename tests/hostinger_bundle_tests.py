#!/usr/bin/env python3
"""Verify production-style split packaging on disposable local disk. Not real hosting."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import zipfile

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from build_hostinger_bundle import build,safe_path

count=0
def check(title,ok):
    global count
    if not ok:raise RuntimeError('FAIL: '+title)
    count+=1;print('PASS '+title)

with tempfile.TemporaryDirectory(prefix='oj-hostinger-preflight-') as td:
    root=Path(td)
    report=build(root/'bundle')
    check('Release SHA-256 manifest verified before packaging',report['manifest_files_verified']>=133)
    check('Bundle never claims approved cutover',not report['production_cutover_approved'] and not report['live_host_modified'])
    check('Bundle explicitly blocks extraction over existing public_html',
          report['safe_to_extract_over_current_public_html'] is False)
    check('ZIP warning explains preserved index.html can still lose effective homepage',
          'DirectoryIndex index.php' in (root/'bundle'/'STOP_READ_FIRST_NO_LIVE_CUTOVER.txt').read_text()
          and 'SWITCH THE LIVE HOMEPAGE' in (root/'bundle'/'STOP_READ_FIRST_NO_LIVE_CUTOVER.txt').read_text())
    for kind in ('private','public'):
        key=kind+'_archive'
        file=root/'bundle'/report[key]
        check(kind+' SHA-256 matches report',hashlib.sha256(file.read_bytes()).hexdigest()==report[kind+'_sha256'])
        with zipfile.ZipFile(file) as z:
            members=z.infolist()
            check(kind+' archive paths contain no traversal',all(safe_path(x.filename) for x in members))
            check(kind+' archive has regular files only',all((x.external_attr>>16 & 0o170000)==0o100000 for x in members))
            if kind=='public':
                check('Webroot has only entrypoint, htaccess and safe assets',
                      all(x.filename in ('public_html/index.php','public_html/.htaccess')
                          or x.filename.startswith('public_html/assets/') for x in members))
                check('Current index.html cannot be overwritten',
                      'public_html/index.html' not in z.namelist())
            else:
                check('Private archive includes full release manifest and server code',
                      'overseasjob_private/RELEASE_MANIFEST.json' in z.namelist()
                      and 'overseasjob_private/src/Jobs.php' in z.namelist())
            z.extractall(root/'simulated_parent')
    base=root/'simulated_parent'
    check('Private application stays outside public_html',
          (base/'overseasjob_private/src/Jobs.php').is_file()
          and not (base/'public_html/src').exists())
    code="$_SERVER['REQUEST_URI']='/tools/relocation-budget';require "+json.dumps(str(base/'public_html/index.php'))+";"
    p=subprocess.run(['php','-r',code],capture_output=True,text=True,timeout=20)
    check('Exact extracted shared-host bootstrap renders tool page',
          p.returncode==0 and 'id="relocation-form"' in p.stdout)
    check('Unapproved release remains noindex',
          'noindex,nofollow' in p.stdout)
    again=build(root/'second')
    check('ZIP artifacts are reproducible',report['private_sha256']==again['private_sha256']
          and report['public_sha256']==again['public_sha256'])
print(f'{count} isolated Hostinger archive/layout checks passed')
