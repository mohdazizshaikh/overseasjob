#!/usr/bin/env python3
"""Check actual JS/PHP functions directly against exact founder v1.4.1 golden fixtures.

Offline, immutable source zip, no HTTP/Hostinger calls, no licences inferred.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.4.1_Corrected_Canonical_Pack.zip'
EXPECTED_HASH = '3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9'
FIXTURE = 'OverseasJob_v1.4.1_Canonical_Specification/18_TEST_FIXTURES.json'


def main() -> int:
    if ARCHIVE.is_symlink() or not ARCHIVE.is_file() or ARCHIVE.stat().st_size > 2_000_000:
        raise RuntimeError('Exact founder canonical ZIP is missing or unsafe')
    if hashlib.sha256(ARCHIVE.read_bytes()).hexdigest() != EXPECTED_HASH:
        raise RuntimeError('Founder canonical archive SHA-256 mismatch')
    with zipfile.ZipFile(ARCHIVE) as bundle:
        if bundle.testzip() is not None or bundle.namelist().count(FIXTURE) != 1:
            raise RuntimeError('Canonical fixture missing, duplicated or ZIP integrity broken')
        member = bundle.getinfo(FIXTURE)
        if member.file_size < 100 or member.file_size > 50_000:
            raise RuntimeError('Implausible canonical fixture length')
        fixture = json.loads(bundle.read(FIXTURE))
    if fixture.get('version') != '1.4.1':
        raise RuntimeError('Golden fixture version differs from authoritative ZIP')
    expected = fixture.get('tool_expected')
    cases = fixture.get('provider_eligibility')
    if not isinstance(expected, dict) or set(expected) != {'T01', 'T02', 'T04', 'T05'}:
        raise RuntimeError('Canonical tool fixture contract unexpectedly changed')
    if not isinstance(cases, list) or len(cases) != 18:
        raise RuntimeError('Canonical external provider fixture count unexpectedly changed')
    commands = [
        (['node', str(ROOT / 'tests/canonical_tool_fixtures.mjs')], expected),
        (['php', str(ROOT / 'tests/canonical_provider_fixtures.php')], cases),
    ]
    for command, payload in commands:
        result = subprocess.run(
            command, input=json.dumps(payload, separators=(',', ':')),
            text=True, capture_output=True, cwd=ROOT, timeout=30, check=False,
        )
        if result.returncode != 0:
            # Inputs come from a verified bundled specification; subprocesses only run code.
            raise RuntimeError(f"{Path(command[1]).name} failed: {result.stderr[-1500:]}")
        print(result.stdout.strip())
    print('PASS canonical v1.4.1 golden fixture to actual JS/PHP reconciliation '
          '(4 tools + 18 provider cases; offline, not live API evidence)')
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (ValueError, OSError, RuntimeError, zipfile.BadZipFile, subprocess.TimeoutExpired) as exc:
        print(f'FAIL canonical runtime golden bridge: {exc}', file=sys.stderr)
        sys.exit(1)
