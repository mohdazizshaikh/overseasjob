#!/usr/bin/env python3
"""Owner v1.4.1-to-code inventory sanity checker, never an external readiness attestation."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path

CANONICAL_SHA = '3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9'
ZIP_PREFIX = 'OverseasJob_v1.4.1_Canonical_Specification/'
TRACEABILITY = 'docs/CANONICAL_V141_IMPLEMENTATION_TRACEABILITY.json'


def demand(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def repo_regular_file(root: Path, name: str) -> Path:
    demand(isinstance(name, str) and bool(re.fullmatch(r'[A-Za-z0-9_.\-/]+', name)),
           f'Invalid path syntax: {name!r}')
    demand('..' not in Path(name).parts and not name.startswith('/'),
           f'Unsafe evidence path: {name}')
    file = root / name
    demand(file.is_file() and not file.is_symlink() and file.resolve().is_relative_to(root.resolve()),
           f'Missing, linked, or out-of-repo file: {name}')
    return file


def owner_inventory(z: zipfile.ZipFile, owner: str, row_pattern: str) -> dict[str, str]:
    text = z.read(ZIP_PREFIX + owner).decode('utf-8')
    out: dict[str, str] = {}
    for match in re.finditer(row_pattern, text, flags=re.MULTILINE):
        name, title = match.group(1), match.group(2).strip()
        demand(name not in out, f'Duplicate canonical row {name}')
        out[name] = title
    return out


def validate(root: Path) -> int:
    manifest = json.loads(repo_regular_file(root, TRACEABILITY).read_text('utf-8'))
    ledger = json.loads(repo_regular_file(root, 'module_status.json').read_text('utf-8'))
    runner = repo_regular_file(root, 'tests/run.sh').read_text('utf-8')
    workflow = repo_regular_file(root, '.github/workflows/ci.yml').read_text('utf-8')
    canonical_bridge = repo_regular_file(root, 'tests/canonical_runtime_contract.py').read_text('utf-8')
    zip_file = repo_regular_file(root, manifest['canonical_zip'])
    demand(zip_file.stat().st_size < 2_000_000, 'Canonical ZIP exceeds permitted audit size')
    demand(hashlib.sha256(zip_file.read_bytes()).hexdigest() == CANONICAL_SHA,
           'Exact founder ZIP digest differs from authoritative v1.4.1')
    demand(manifest['canonical_version'] == 'v1.4.1' and
           manifest['canonical_zip_sha256'] == CANONICAL_SHA and
           ledger['spec_version'] == 'v1.4.1', 'Version target is stale')
    demand(ledger.get('hostinger_verified') is False and ledger.get('public_launch_ready') is False,
           'Local traceability cannot self-authorise real Hostinger or public launch')
    demand(isinstance(manifest['global_external_blockers'], list) and len(manifest['global_external_blockers']) >= 3,
           'Missing honest external evidence exclusions')

    with zipfile.ZipFile(zip_file) as z:
        demand(z.testzip() is None, 'Canonical ZIP CRC error')
        module_owner = owner_inventory(
            z, manifest['canonical_module_owner'], r'^\|\s*(M\d\d)\s*\|\s*([^|]+)\|')
        tool_owner = owner_inventory(
            z, manifest['canonical_tool_owner'], r'^##\s+(T\d\d)\s+([^\n]+)')
    expected_modules = {f'M{i:02d}' for i in range(18)}
    expected_tools = {f'T{i:02d}' for i in range(1, 11)}
    demand(set(module_owner) == expected_modules, 'Source of Truth missing/extra M00-M17 modules')
    demand(set(tool_owner) == expected_tools, 'Source of Truth missing/extra T01-T10 headings')

    ledger_modules = {row['module_id']: row for row in ledger['modules']}
    demand(len(ledger['modules']) == 18 and set(ledger_modules) == expected_modules,
           'GitHub module_status lacks exact 18 modules')
    module_ids, tool_ids = [], []
    code_paths_verified = 0
    test_paths_verified = 0
    for index, rows, ids, owner, expected in (
        ('module', manifest['modules'], module_ids, module_owner, expected_modules),
        ('tool', manifest['tools'], tool_ids, tool_owner, expected_tools),
    ):
        demand(isinstance(rows, list) and len(rows) == len(expected),
               f'Expected exactly {len(expected)} {index} traceability entries')
        for row in rows:
            key = row['id']
            ids.append(key)
            demand(key in owner and row['canonical_title'] == owner[key],
                   f'{key}: title differs from authoritative owner requirement')
            sources = row.get('implementation_paths', [])
            tests = row.get('test_paths', [])
            demand(isinstance(sources, list) and len(sources) >= 1 and len(set(sources)) == len(sources),
                   f'{key}: missing or duplicate code associations')
            demand(isinstance(tests, list) and len(tests) >= 1 and len(set(tests)) == len(tests),
                   f'{key}: missing or duplicate test associations')
            for source in sources:
                repo_regular_file(root, source)
                code_paths_verified += 1
            for test in tests:
                demand(test.startswith('tests/'), f'{key}: non-test file claimed as test')
                repo_regular_file(root, test)
                demand(test.rsplit('/', 1)[-1] in runner or test.rsplit('/', 1)[-1] in workflow
                       or (test.rsplit('/', 1)[-1] in canonical_bridge
                           and 'canonical_runtime_contract.py' in runner),
                       f'{key}: referenced test not dispatched by GitHub CI: {test}')
                test_paths_verified += 1
            if index == 'module':
                reported = row['status_recorded_in_module_ledger']
                demand(reported in ('implemented', 'blocked_with_reason'),
                       f'{key}: only truthful current code statuses allowed')
                demand(ledger_modules[key]['implementation_status'] == reported,
                       f'{key}: live module ledger status drift, review before promotion')
                demand(isinstance(row['external_evidence_missing'], list) and row['external_evidence_missing'] and
                       isinstance(row['code_reconciliation_residuals'], list) and row['code_reconciliation_residuals'],
                       f'{key}: missing residual evidence statement')
            else:
                demand(row['test_evidence_grade'] in ('direct_canonical_expected_outputs',
                                                     'independent_local_tests_only'),
                       f'{key}: ungrounded tool test grade')
                demand(isinstance(row['residuals'], list) and row['residuals'],
                       f'{key}: missing limits')
                if row['test_evidence_grade'] == 'direct_canonical_expected_outputs':
                    demand(key in {'T01', 'T02', 'T04', 'T05'},
                           f'{key}: no direct golden expectation in verified ZIP')
                    demand('tests/canonical_tool_fixtures.mjs' in tests,
                           f'{key}: direct fixture test association absent')
    demand(len(set(module_ids)) == 18 and set(module_ids) == expected_modules,
           'Traceability missing/duplicate/extra module')
    demand(len(set(tool_ids)) == 10 and set(tool_ids) == expected_tools,
           'Traceability missing/duplicate/extra tool')
    # Targeted, executable M08 owner-route alignment; prevents silently regressing to /daily.
    controller = repo_regular_file(root, 'public/index.php').read_text('utf-8')
    m08 = ledger_modules['M08']
    demand('/arena' in m08['routes'] and
           any(route.startswith('/daily (301') for route in m08['routes']),
           'M08 module ledger must name canonical /arena and old-route redirect')
    demand("if($path==='/arena')" in controller and
           "header('Location: /arena',true,301);" in controller and
           'href="/daily"' not in controller,
           'M08 controller must serve only /arena with 301 from /daily')

    # This crosswalk is an inventory test, NOT a full conformance or external evidence certificate.
    print(f'PASS v1.4.1 owner traceability: {len(module_ids)}/18 modules, '
          f'{len(tool_ids)}/10 tools, {code_paths_verified} code references, '
          f'{test_paths_verified} dispatched test references')
    print('NOTE code/test associations are not proof of full behaviour; release remains NO-GO')
    return 0


if __name__ == '__main__':
    try:
        parser = argparse.ArgumentParser()
        parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
        args = parser.parse_args()
        sys.exit(validate(args.root))
    except (AssertionError, KeyError, ValueError, OSError, zipfile.BadZipFile, json.JSONDecodeError) as e:
        print(f'FAIL owner traceability contract: {e}', file=sys.stderr)
        sys.exit(1)
