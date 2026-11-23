#!/usr/bin/env python3
"""Verify the exact founder v1.4.1 canonical ZIP (offline; no host/deploy calls)."""
from __future__ import annotations
import hashlib
from pathlib import Path, PurePosixPath
import stat
import subprocess
import sys
import tempfile
import zipfile

ROOT=Path(__file__).resolve().parents[1]
ARCHIVE=ROOT/'SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.4.1_Corrected_Canonical_Pack.zip'
SHA256='3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9'
OWNER_SHA='4026cf501828b740fd77c617c9634c963ab3b0c88c395e0989916b7666177ddb'
FOLDER='OverseasJob_v1.4.1_Canonical_Specification'
MAX_ZIP_BYTES=2_000_000
MAX_EXPANDED_BYTES=8_000_000
MAX_ENTRIES=70

def verify_archive(file:Path)->str:
    if not file.is_file() or file.is_symlink():
        raise ValueError('Canonical v1.4.1 ZIP missing or symlinked')
    raw=file.read_bytes()
    if len(raw)>MAX_ZIP_BYTES or hashlib.sha256(raw).hexdigest()!=SHA256:
        raise ValueError('Canonical ZIP bytes or SHA-256 mismatch; refuse historical/altered archives')
    total=0
    with zipfile.ZipFile(file) as z:
        items=z.infolist()
        if len(items)!=34 or len(items)>MAX_ENTRIES:
            raise ValueError('Canonical ZIP file inventory unexpected')
        names=set()
        for ent in items:
            name=ent.filename
            pp=PurePosixPath(name)
            if (not name or '\\' in name or name.startswith('/') or pp.is_absolute()
                or any(p in ('','.','..') for p in name.split('/'))
                or name in names or ent.is_dir()):
                raise ValueError('Unsafe/duplicate canonical ZIP entry')
            names.add(name)
            mode=(ent.external_attr >> 16)&0o170000
            if mode not in (0,stat.S_IFREG):
                raise ValueError('Canonical ZIP contains symlink/nonregular entry')
            total+=ent.file_size
            if total>MAX_EXPANDED_BYTES:
                raise ValueError('Canonical ZIP exceeds expanded size bound')
        owner=f'{FOLDER}/00_SOURCE_OF_TRUTH.md'
        script=f'{FOLDER}/23_VALIDATE_SPEC.py'
        if owner not in names or script not in names:
            raise ValueError('Canonical authority or validator missing')
        if z.testzip() is not None:
            raise ValueError('Canonical ZIP CRC mismatch')
        if hashlib.sha256(z.read(owner)).hexdigest()!=OWNER_SHA:
            raise ValueError('Canonical owner file SHA-256 mismatch')
        with tempfile.TemporaryDirectory(prefix='oj-canonical-v141-') as t:
            # The exact hashed archive is already validated above. Extract to a disposable folder.
            z.extractall(t)
            folder=Path(t)/FOLDER
            completed=subprocess.run([sys.executable,'23_VALIDATE_SPEC.py'],cwd=folder,
                capture_output=True,text=True,timeout=60,check=False)
            if completed.returncode!=0 or '149/149 checks passed; 0 failures.' not in completed.stdout:
                raise ValueError('Canonical document validator did not pass 149/149: '+
                                 (completed.stdout+completed.stderr)[-1000:])
    return 'PASS exact v1.4.1 canonical ZIP SHA-256, safe inventory, CRC, authority hash and 149/149 document checks'

def main()->int:
    try:
        print(verify_archive(ARCHIVE))
        return 0
    except (OSError,ValueError,zipfile.BadZipFile,subprocess.TimeoutExpired) as exc:
        print('BLOCKED canonical package verification: '+str(exc),file=sys.stderr)
        return 2

if __name__=='__main__':
    raise SystemExit(main())
