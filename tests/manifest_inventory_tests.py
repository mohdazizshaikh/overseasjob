#!/usr/bin/env python3
"""Negative tests for Git-tracked inventory omission detection and atomic regeneration."""
from pathlib import Path
import hashlib
import json
import subprocess
import sys
import tempfile
import shutil

ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from refresh_release_manifest import MANIFEST,entry,perform

n=0
def check(label,truth):
    global n
    if not truth:raise AssertionError('FAIL inventory '+label)
    n+=1;print('PASS inventory '+label)

with tempfile.TemporaryDirectory(prefix='oj-manifest-proof-') as temp:
    repo=Path(temp)
    def git(*args):
        subprocess.run(['git',*args],cwd=repo,check=True,
                       stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    git('init')
    (repo/'scripts').mkdir()
    shutil.copyfile(ROOT/'scripts/refresh_release_manifest.py',
                    repo/'scripts/refresh_release_manifest.py')
    (repo/'alpha.txt').write_text('clean source contents')
    initial=[entry(repo/'alpha.txt','alpha.txt'),
             entry(repo/'scripts/refresh_release_manifest.py','scripts/refresh_release_manifest.py')]
    (repo/MANIFEST).write_text(json.dumps({
        'release':'fixture','files':initial,'file_count_excluding_manifest':2
    })+'\n')
    git('add','-A')
    current=(repo/MANIFEST).read_bytes()
    check('Complete Git index initially passes',perform(repo)[0])
    check('Read-only check never alters release manifest',(repo/MANIFEST).read_bytes()==current)
    (repo/'beta.txt').write_text('new source not yet in manifest')
    git('add','beta.txt')
    good,msg=perform(repo)
    check('Newly tracked omitted source triggers failure',not good and 'beta.txt' in msg)
    check('Check failure does not rewrite manifest',(repo/MANIFEST).read_bytes()==current)
    good,_=perform(repo,write=True,release='vX-test')
    check('Atomic --write synchronizes every tracked file',good)
    check('Regenerated manifest includes exactly all three files',
          len(json.loads((repo/MANIFEST).read_text())['files'])==3)
    check('New complete manifest passes',perform(repo)[0])
    (repo/'alpha.txt').write_text('tampered original')
    good,msg=perform(repo)
    check('Modified tracked source digest detected',not good and 'alpha.txt' in msg)
    perform(repo,write=True)
    check('Refresh resolves stale hashes',perform(repo)[0])
    (repo/'secret.key').write_text('not a real secret; negative test')
    git('add','secret.key')
    bad=False
    try:perform(repo)
    except ValueError:bad=True
    check('Tracked secret-like key causes fail closed',bad)
    git('reset','--','secret.key')
    (repo/'secret.key').unlink()
    (repo/'link.txt').symlink_to(repo/'alpha.txt')
    git('add','link.txt')
    bad=False
    try:perform(repo)
    except ValueError:bad=True
    check('Tracked symlink is refused',bad)
    git('reset','--','link.txt')
    (repo/'link.txt').unlink()
    check('Safe inventory works again without prohibited files',perform(repo)[0])

ok,msg=perform(ROOT)
check('Real project tracked manifest is exhaustive and hash-matched',ok)
print(f'\n{n} complete source-inventory safeguards passed.')
