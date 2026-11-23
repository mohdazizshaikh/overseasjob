#!/usr/bin/env python3
"""Read-only audit of the exact tracked canonical v1.3.3 ZIP. Does not mutate source."""
from pathlib import Path
from zipfile import ZipFile
import json,re,sys
ROOT=Path(__file__).resolve().parents[1]
PACK=ROOT/"SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.3.3_Source_of_Truth_Project_Pack.zip"
with ZipFile(PACK) as z:
    assert z.testzip() is None
    names=z.namelist()
    print("Pack entries",len(names))
    owner_names=("16_DATA_AND_OPERATIONS_CONTRACT.md","20_VERIFICATION_LEDGER.md",
                 "09_CHANGELOG.md","00_READ_ME_FIRST.md","13_RELEASE_MANIFEST.json",
                 "23_VALIDATE_SPEC.py","05_SOURCE_POLICY_MATRIX.csv")
    for basename in owner_names:
        matches=[n for n in names if n.endswith("/"+basename) or n==basename]
        print("OWNER",basename,"MATCHES",len(matches))
        if len(matches)!=1:continue
        raw=z.read(matches[0]).decode("utf-8")
        print("SIZE",len(raw))
        if basename.endswith(".json"):
            d=json.loads(raw); print("MANIFEST METADATA",{k:v for k,v in d.items() if k!="files"})
            print("MANIFEST FIRST",d.get("files",[None])[0]);continue
        if basename=="23_VALIDATE_SPEC.py":
            print("VALIDATOR LINES",[x.strip() for x in raw.splitlines() if "readme current version" in x or "source invariant" in x or "readme.startswith" in x])
        elif basename=="16_DATA_AND_OPERATIONS_CONTRACT.md":
            for i,line in enumerate(raw.splitlines(),1):
                if any(k in line.lower() for k in ("himalayas","nextcursor","pubdate","410","404","offset","source_updated_at")):
                    print(f"OWNER_LINE_{i}: {line[:450]}")
        elif basename=="20_VERIFICATION_LEDGER.md":
            for i,line in enumerate(raw.splitlines(),1):
                if "Himalayas" in line or "pubDate" in line or "status" in line.lower():print(f"LEDGER_LINE_{i}: {line[:450]}")
        elif basename=="00_READ_ME_FIRST.md":
            print("README FIRST",raw.splitlines()[:3])
        elif basename=="09_CHANGELOG.md":
            print("CHANGELOG END",raw[-850:])
        else:
            print("MATRIX HIMALAYAS",next((x[:500] for x in raw.splitlines() if x.startswith("Himalayas,")),"MISSING"))
    print("DOCX FILES",[n for n in names if n.endswith(".docx")])
    print("ASSET PACK ROOTS",sorted(set(n.split("/")[0] for n in names)))
