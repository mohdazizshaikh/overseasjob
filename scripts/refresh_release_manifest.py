#!/usr/bin/env python3
"""Complete release manifest inventory from local Git's tracked files, with SHA-256.

Safe offline maintenance for coding agents; only --write changes RELEASE_MANIFEST.json.
--check also catches newly tracked files omitted from the JSON manifest.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import subprocess
import sys
import tempfile

MANIFEST='RELEASE_MANIFEST.json'

def tracked(root:Path)->list[str]:
    r=subprocess.run(['git','rev-parse','--show-toplevel'],cwd=root,capture_output=True,
                     text=True,timeout=15,check=True)
    if Path(r.stdout.strip()).resolve()!=root.resolve():
        raise ValueError('Git root does not match the project root')
    run=subprocess.run(['git','ls-files','--cached','-z','--full-name'],cwd=root,
                       capture_output=True,timeout=30,check=True)
    names=[x.decode('utf-8') for x in run.stdout.split(b'\x00') if x]
    if names.count(MANIFEST)!=1:
        raise ValueError('RELEASE_MANIFEST.json must be tracked exactly once')
    if len(names)!=len(set(names)):
        raise ValueError('Git source contains duplicate tracked paths')
    output=[]
    for name in names:
        parts=PurePosixPath(name).parts
        if ('\\' in name or '\0' in name or not parts or '..' in parts
            or PurePosixPath(name).is_absolute() or name.startswith('../')):
            raise ValueError('Unsafe tracked path')
        if name in ('.env','.env.local') or name.endswith(('.pem','.key','.p12','.pfx')):
            raise ValueError('Refuse secret-like file in release: '+name)
        path=root.joinpath(*parts)
        if path.is_symlink() or not path.is_file():
            raise ValueError('Missing or symlinked tracked file: '+name)
        parent=path.parent
        while parent!=root:
            if parent.is_symlink():
                raise ValueError('Symlinked directory in release: '+name)
            parent=parent.parent
        if name!=MANIFEST:output.append(name)
    return sorted(output)

def entry(path:Path,name:str)->dict:
    digest=hashlib.sha256()
    length=0
    with path.open('rb') as f:
        for chunk in iter(lambda:f.read(1024*1024),b''):
            digest.update(chunk);length+=len(chunk)
    return {'path':name,'bytes':length,'sha256':digest.hexdigest()}

def generate(root:Path)->list[dict]:
    return [entry(root/name,name) for name in tracked(root)]

def perform(root:Path,write:bool=False,release:str|None=None)->tuple[bool,str]:
    root=root.resolve()
    path=root/MANIFEST
    if path.is_symlink() or not path.is_file():
        raise ValueError('Release manifest absent or symlinked')
    data=json.loads(path.read_text(encoding='utf-8'))
    if not isinstance(data,dict) or not isinstance(data.get('files'),list):
        raise ValueError('Release manifest malformed')
    actual=generate(root)
    old=data['files']
    oldpaths=[x.get('path') for x in old if isinstance(x,dict)]
    if len(oldpaths)!=len(old) or len(oldpaths)!=len(set(oldpaths)):
        raise ValueError('Duplicate or invalid existing manifest paths')
    if not write:
        prior={x['path']:{'bytes':x.get('bytes'),'sha256':x.get('sha256')} for x in old}
        new={x['path']:{'bytes':x['bytes'],'sha256':x['sha256']} for x in actual}
        missing=sorted(set(new)-set(prior))
        extra=sorted(set(prior)-set(new))
        different=sorted(name for name in set(new)&set(prior) if new[name]!=prior[name])
        correct=(not missing and not extra and not different
                 and data.get('file_count_excluding_manifest')==len(actual))
        if not correct:
            return False,('Release manifest is INCOMPLETE or stale; missing='+repr(missing[:12])
                          +' extra='+repr(extra[:12])+' mismatched='+repr(different[:12]))
        return True,f'PASS complete tracked Git inventory and SHA-256: {len(actual)} files'
    if release is not None:
        if not release or len(release)>120 or any(ord(x)<32 for x in release):
            raise ValueError('Invalid release label')
        data['release']=release
    data['files']=actual
    data['file_count_excluding_manifest']=len(actual)
    fd,temp=tempfile.mkstemp(prefix='releasemanifest-',dir=root)
    try:
        with os.fdopen(fd,'w',encoding='utf-8') as out:
            json.dump(data,out,ensure_ascii=False,indent=2)
            out.write('\n')
            out.flush();os.fsync(out.fileno())
        os.chmod(temp,0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
    return True,f'UPDATED complete Git source manifest: {len(actual)} tracked files'

def main()->int:
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--write',action='store_true',help='refresh manifest atomically')
    parser.add_argument('--release',help='new release label (requires --write)')
    parser.add_argument('--root',type=Path,default=Path(__file__).resolve().parents[1],
                        help='local Git repo root, for isolated tests or agent maintenance')
    args=parser.parse_args()
    if args.release is not None and not args.write:
        parser.error('--release requires --write')
    try:
        success,message=perform(args.root,args.write,args.release)
        print(message,file=sys.stdout if success else sys.stderr)
        return 0 if success else 2
    except (OSError,ValueError,KeyError,subprocess.SubprocessError,UnicodeError) as e:
        print('Refused unsafe/inconsistent release inventory: '+str(e),file=sys.stderr)
        return 2

if __name__=='__main__':
    raise SystemExit(main())
