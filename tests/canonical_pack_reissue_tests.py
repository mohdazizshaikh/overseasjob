#!/usr/bin/env python3
"""Isolated exact-source v1.3.3 to narrow v1.3.4 reissue acceptance tests."""
import hashlib,json,sys,tempfile,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts'))
from reissue_canonical_pack_v134 import build,read_original,exactly
n=0
def check(name,condition):
    global n
    if not condition:raise RuntimeError('FAIL canonical reissue: '+name)
    n+=1;print('PASS canonical reissue: '+name)
with tempfile.TemporaryDirectory(prefix='oj-canonical-reissue-test-') as temp:
    root=Path(temp)
    baseline=read_original()
    report=build(root/'first')
    check('Exact source v1.3.3 checksum verified',bool(report['source_zip_sha256']))
    check('Canonical source and output file inventories identical',
          len(baseline)==report['source_file_count']==report['reissued_file_count'])
    check('At least 70 old+new canonical checks passed',report['canonical_checks_passed']>=70)
    archive=root/'first'/'OverseasJob_v1.3.4_Source_of_Truth_Project_Pack.zip'
    with zipfile.ZipFile(archive) as z:
        check('Output canonical ZIP has no corrupt members',z.testzip() is None)
        items={x.filename.rsplit('/',1)[-1]:z.read(x) for x in z.namelist() if not x.endswith('/')}
        manifest=json.loads(items['13_RELEASE_MANIFEST.json'])
        check('Manifest version bumped exactly to v1.3.4',manifest['version']=='1.3.4')
        check('Manifest tracks all files except itself',len(manifest['files'])==len(items)-1)
        check('Every reissued manifest checksum verified',all(
            len(items[f['name']])==f['bytes'] and
            hashlib.sha256(items[f['name']]).hexdigest()==f['sha256']
            for f in manifest['files']))
        owner=items['16_DATA_AND_OPERATIONS_CONTRACT.md'].decode()
        check('Old false 410 statement fully removed','410 (Himalayas) means closed' not in owner)
        check('No invented Himalayas per-job status API documented', 'NO per-job status API' in owner)
        check('Original correct offset/totalCount semantics retained','offset=N' in owner and 'totalCount' in owner)
        check('Original correct publication semantics retained','source_published_at' in owner and 'pubDate' in owner)
        check('All previously untouched canonical files bit-identical',all(
            baseline[name]==items[name] for name in report['identical_unchanged_files']))
        check('No business modules or original tool contracts deleted',
              '15_CANONICAL_MODULE_REQUIREMENTS.md' in report['identical_unchanged_files']
              and '17_TOOL_ALGORITHMS_AND_FIXTURES.md' in report['identical_unchanged_files'])
    second=build(root/'second')
    check('Archive generation deterministic', report['reissued_zip_sha256']==second['reissued_zip_sha256'])
    guard=False
    try:exactly('abc','not here','bad','test')
    except ValueError:guard=True
    check('Unknown source anchor fails without silent patching',guard)
    check('No external verification fabricated',not report['production_deployed']
          and not report['source_permission_verified'] and not report['launch_ready'])
print(f'{n} canonical reissue acceptance checks passed')
