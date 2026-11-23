#!/usr/bin/env python3
"""Reissue exact tracked, checksum-verified v1.3.3 canonical ZIP as narrow v1.3.4."""
import argparse, hashlib, json, re, stat, subprocess, sys, tempfile, zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
SRC=ROOT/'SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.3.3_Source_of_Truth_Project_Pack.zip'
OLD_ROOT='OverseasJob_v1.3.3_Canonical_Specification/'
NEW_ROOT='OverseasJob_v1.3.4_Canonical_Specification/'
OUT='OverseasJob_v1.3.4_Source_of_Truth_Project_Pack.zip'

def exactly(text,old,new,owner):
    n=text.count(old)
    if n!=1:raise ValueError(f'{owner}: expected exactly one known source phrase, found {n}')
    return text.replace(old,new,1)

def read_original():
    if not SRC.is_file() or SRC.is_symlink():raise ValueError('Missing/symlinked original v1.3.3 ZIP')
    with zipfile.ZipFile(SRC) as z:
        if z.testzip():raise ValueError('Corrupt source archive')
        docs={}
        for item in z.infolist():
            if item.is_dir():continue
            if not item.filename.startswith(OLD_ROOT):raise ValueError('Unexpected pack root')
            name=item.filename[len(OLD_ROOT):]
            if not name or '/' in name or '..' in name or name in docs:
                raise ValueError('Unsafe or duplicate original member')
            docs[name]=z.read(item)
    manifest=json.loads(docs['13_RELEASE_MANIFEST.json'])
    if manifest.get('version')!='1.3.3':raise ValueError('Unexpected source version')
    tracked={}
    for item in manifest['files']:
        name=item['name']
        if name in tracked:raise ValueError('Duplicate manifest entry')
        tracked[name]=item
        blob=docs.get(name)
        if blob is None or len(blob)!=item['bytes'] or hashlib.sha256(blob).hexdigest()!=item['sha256']:
            raise ValueError('Original source checksum mismatch: '+name)
    if set(docs)-{'13_RELEASE_MANIFEST.json'}!=set(tracked):
        raise ValueError('Original source has untracked members')
    return docs

def correct(original):
    docs=original.copy()
    def change(name,func):
        before=original[name].decode('utf-8')
        after=func(before)
        if before==after:raise ValueError('No correction made to '+name)
        docs[name]=after.encode('utf-8')
    def change_owner(s):
        stale='For provider, 410 (Himalayas) means closed; 404 means unknown/pending investigation (not automatically closed); 429/5xx retain cache while respecting freshness.'
        correct=('For provider, HTTP 410 proves closure only when received from an independently documented and authorised per-job status endpoint. '
                 'As of 2026-11-23, Himalayas public OpenAPI documents ONLY browse and search and NO per-job status API; '
                 'do not call invented job-status URLs; a 404, a vanished browse entry or an unreachable job link means the job is not automatically closed. '
                 'Use expiry and source freshness, an honest unknown/hidden state and retain the healthy snapshot on 429/5xx.')
        s=exactly(s,stale,correct,'16 status owner')
        heading='### Himalayas JSON API\n'
        detail=('### Himalayas JSON API\n\n'
                '**Public API status boundary (2026-11-23):** Current official OpenAPI at '
                'https://himalayas.app/docs/openapi.json documents GET /jobs/api (offset/limit/totalCount) '
                'and GET /jobs/api/search (page-based filtering) only. It DOES NOT document an individual-job '
                '200/404/410 API. Do not request guessed /jobs/api/companies/... status routes. '
                'Retain opaque original guid values, preserve validated original HTTPS applicationLink even for '
                'employer/ATS destinations, and never fabricate a job-detail source URL. '
                'pubDate is the source-reported job publication timestamp; updatedAt is a feed refresh timestamp '
                'and must not be misrepresented as an individual job update.\n')
        return exactly(s,heading,detail,'16 Himalayas section')
    change('16_DATA_AND_OPERATIONS_CONTRACT.md',change_owner)
    change('05_SOURCE_POLICY_MATRIX.csv',lambda s:exactly(
        s,'within_API_limits_and_remove_closed',
        'within_API_limits_expiry_freshness_no_undocumented_status','05 source policy'))
    def change_ledger(s):
        key='| Himalayas JSON API publication timestamp |'
        if s.count(key)!=1:raise ValueError('20 ledger anchor missing')
        i=s.index('\n',s.index(key))+1
        row=('| Himalayas per-job HTTP status | Checked 2026-11-23: official public OpenAPI lists '
             'browse and search only; no documented per-job 410/404 endpoint. Previous general closure '
             'assumption superseded by canonical owner 16. | Live status calls blocked; '
             'no real provider, closing signal or host evidence asserted |\n')
        return s[:i]+row+s[i:]
    change('20_VERIFICATION_LEDGER.md',change_ledger)
    def change_readme(s):
        s=exactly(s,'Canonical AI-agent Project Pack v1.3.3',
                  'Canonical AI-agent Project Pack v1.3.4','00 version')
        key='# OverseasJob.in — Canonical AI-agent Project Pack v1.3.4'
        note=('\n\n**v1.3.4 narrow source-status correction:** no undocumented Himalayas per-job '
              '200/404/410 endpoint may be invoked; refer to source owner 16 and the changelog. '
              'All M00-M17/T01-T10 requirements retained. NOT a live product or deployment.')
        return exactly(s,key,key+note,'00 release note')
    change('00_READ_ME_FIRST.md',change_readme)
    def change_log(s):
        heading='# OverseasJob.in — Blueprint Change Log'
        entry=('\n\n## v1.3.4 — 23 November 2026 — verified public provider-status boundary\n\n'
               'v1.3.3 ALREADY correctly stated Himalayas offset/limit/totalCount pagination and '
               'source-reported pubDate publication; both retained. Corrected the false assumption '
               'that the public API defines per-job HTTP 200/404/410 status. The official API '
               'documents browse and search only. Updated the data/status owner, source-policy '
               'matrix, verification ledger, validator and checksummed manifest. '
               'No business modules were deleted, no actual Hostinger verification, '
               'provider rights approval or working source inventory is implied.\n\n'
               'Sources checked 2026-11-23: https://himalayas.app/docs/openapi.json '
               'and https://himalayas.app/docs/remote-jobs-api.\n')
        return exactly(s,heading,heading+entry,'09 changelog')
    change('09_CHANGELOG.md',change_log)
    def change_validator(s):
        anchor='# module inventory'
        added=(
            "# v1.3.4 checks for the now-documented provider contract boundary.\n"
            "check('Himalayas offset and totalCount remain primary', 'offset=N' in source and 'totalCount' in source)\n"
            "check('Himalayas no invented status calls', 'NO per-job status API' in source and 'do not call invented' in source)\n"
            "check('Old 410 direct-status assumption removed', '410 (Himalayas) means closed' not in source)\n"
            "matrix=(ROOT/'05_SOURCE_POLICY_MATRIX.csv').read_text()\n"
            "check('Source policy no invented status', 'no_undocumented_status' in matrix)\n"
            "check('Pack version v1.3.4', 'Project Pack v1.3.4' in (ROOT/'00_READ_ME_FIRST.md').read_text())\n"
        )
        return exactly(s,anchor,added+anchor,'23 validator')
    change('23_VALIDATE_SPEC.py',change_validator)
    if '18_TEST_FIXTURES.json' in docs:
        f=json.loads(docs['18_TEST_FIXTURES.json'])
        if f.get('version')=='1.3.3':
            f['version']='1.3.4'
            docs['18_TEST_FIXTURES.json']=(json.dumps(f,indent=2,ensure_ascii=False)+'\n').encode()
    return docs

def build(output):
    original=read_original()
    docs=correct(original)
    with tempfile.TemporaryDirectory(prefix='oj-v134-contract-') as temp:
        p=Path(temp)/NEW_ROOT;p.mkdir()
        for name,data in docs.items():(p/name).write_bytes(data)
        check=subprocess.run([sys.executable,str(p/'23_VALIDATE_SPEC.py')],cwd=p,
                             capture_output=True,text=True,timeout=60)
        if check.returncode:
            raise ValueError('Canonical validator failed:\n'+check.stdout[-1900:]+check.stderr[-800:])
        found=re.findall(r'(\d+)/(\d+) checks passed; 0 failures',check.stdout)
        if len(found)!=1 or found[0][0]!=found[0][1]:
            raise ValueError('No unambiguous 100% passing canonical validator summary')
        passed=int(found[0][0])
    manifest=json.loads(original['13_RELEASE_MANIFEST.json'])
    manifest['version']='1.3.4';manifest['prepared']='2026-11-23'
    manifest['status']='documentation_contract_not_deployed'
    manifest['qa_checks']=f'{passed}/{passed} contract and fixture checks passed in isolated source'
    manifest['files']=[{'name':n,'bytes':len(v),'sha256':hashlib.sha256(v).hexdigest()}
                       for n,v in sorted(docs.items()) if n!='13_RELEASE_MANIFEST.json']
    docs['13_RELEASE_MANIFEST.json']=(json.dumps(manifest,indent=2,ensure_ascii=False)+'\n').encode()
    changed=sorted(n for n in docs if original[n]!=docs[n])
    if not {'16_DATA_AND_OPERATIONS_CONTRACT.md','05_SOURCE_POLICY_MATRIX.csv',
        '20_VERIFICATION_LEDGER.md','00_READ_ME_FIRST.md','09_CHANGELOG.md',
        '23_VALIDATE_SPEC.py','13_RELEASE_MANIFEST.json'}.issubset(changed):
        raise ValueError('Mandatory contract owner/validator update missing')
    output.mkdir(parents=True,exist_ok=True)
    dest=output/'OverseasJob_v1.3.4_Source_of_Truth_Project_Pack.zip'
    with zipfile.ZipFile(dest,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for name,blob in sorted(docs.items()):
            info=zipfile.ZipInfo(NEW_ROOT+name,(2026,11,23,0,0,0))
            info.compress_type=zipfile.ZIP_DEFLATED
            info.external_attr=(stat.S_IFREG|0o644)<<16
            z.writestr(info,blob,compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
    with zipfile.ZipFile(dest) as z:
        if z.testzip():raise ValueError('Output ZIP testzip failure')
    report={'version':'1.3.4','source_version':'1.3.3',
       'source_zip_sha256':hashlib.sha256(SRC.read_bytes()).hexdigest(),
       'reissued_zip_sha256':hashlib.sha256(dest.read_bytes()).hexdigest(),
       'source_file_count':len(original),'reissued_file_count':len(docs),
       'canonical_checks_passed':passed,'changed_files':changed,
       'identical_unchanged_files':sorted(set(docs)-set(changed)),
       'production_deployed':False,'source_permission_verified':False,
       'actual_host_backup_restored':False,'launch_ready':False}
    (output/'v134_integrity_and_scope.json').write_text(json.dumps(report,indent=2)+'\n')
    return report

def main():
    a=argparse.ArgumentParser(description=__doc__)
    a.add_argument('--output',type=Path,default=ROOT/'artifacts/canonical-v134')
    x=a.parse_args()
    try:print(json.dumps(build(x.output.resolve()),indent=2));return 0
    except (OSError,ValueError,KeyError,zipfile.BadZipFile,subprocess.TimeoutExpired) as e:
        print('REFUSED canonical reissue:',e,file=sys.stderr);return 2

if __name__=='__main__':raise SystemExit(main())
