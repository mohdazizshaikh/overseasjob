/** Privacy-first application tracker. No HTTP calls, cloud storage or auto-apply. */
export const TRACKER_KEY = 'overseasjob_applications_v1';
export const STATUSES = ['saved','applied','interview','offer','rejected','closed'];
export const MAX_RECORDS = 1000;
export const MAX_IMPORT_BYTES = 1024 * 1024;
export const CSV_FIELDS = ['id','company','role','url','work_location','status','applied_at','followup_date','notes','created_at','updated_at'];
function limited(value,max){ if(value==null)return ''; if(typeof value!=='string')throw Error('Invalid field type');return value.trim().slice(0,max); }
export function safeExternalUrl(value){
 const text=limited(value,2048);if(!text)return '';
 try{const u=new URL(text); if(u.protocol!=='https:' || u.username || u.password || u.port || u.hostname==='localhost' || u.hostname.endsWith('.local') || /^\d+\.\d+\.\d+\.\d+$/.test(u.hostname) || u.hostname.includes(':'))throw Error();return text;}
 catch{throw Error('Only safe HTTPS links are accepted');}
}
function dateOnly(v){
 const text=limited(v,10);if(!text)return '';
 if(!/^\d{4}-\d{2}-\d{2}$/.test(text)||!Number.isFinite(Date.parse(text))||new Date(text+'T00:00:00.000Z').toISOString().slice(0,10)!==text)throw Error('Date must be a valid YYYY-MM-DD');
 return text;
}
function timestamp(value){
 if(typeof value!=='string' || !/^\d{4}-\d{2}-\d{2}T/.test(value) || !Number.isFinite(Date.parse(value)))throw Error('Invalid record timestamp');
 return new Date(value).toISOString();
}
export function normaliseRecord(raw,{now=new Date().toISOString(),id=''}={}){
 if(!raw || typeof raw!=='object' || Array.isArray(raw))throw Error('Invalid record');
 const role=limited(raw.role,140), company=limited(raw.company,120), notes=limited(raw.notes,800), work_location=limited(raw.work_location,120);
 if(!role || !company)throw Error('Company and role are required');
 const status=limited(raw.status??'saved',30);
 if(!STATUSES.includes(status))throw Error('Invalid application status');
 const identifier=limited(raw.id||id,170);
 if(!identifier || !/^[A-Za-z0-9:._-]{1,170}$/.test(identifier))throw Error('Invalid application identifier');
 return {id:identifier,company,role,url:safeExternalUrl(raw.url??''),work_location,status,
   applied_at:dateOnly(raw.applied_at??''),followup_date:dateOnly(raw.followup_date??''),notes,
   created_at:timestamp(raw.created_at||now),updated_at:timestamp(raw.updated_at||now)};
}
export function readTracker(store=globalThis.localStorage){
 try{const data=store.getItem(TRACKER_KEY);if(!data)return [];
  return importTrackerJSON(data,{isStored:true});
 }catch{return [];}
}
export function writeTracker(records,store=globalThis.localStorage){
 if(!Array.isArray(records)||records.length>MAX_RECORDS)throw Error('Tracker limit reached');
 const unique=new Set(); const cleaned=records.map(r=>{
   const norm=normaliseRecord(r);if(unique.has(norm.id))throw Error('Duplicate ID');unique.add(norm.id);return norm;});
 const raw=JSON.stringify({schema_version:1,records:cleaned});
 if(new TextEncoder().encode(raw).length>MAX_IMPORT_BYTES)throw Error('Local tracker exceeds 1 MiB');
 store.setItem(TRACKER_KEY,raw);return cleaned;
}
export function importTrackerJSON(raw,{isStored=false}={}){
 if(typeof raw!=='string'||new TextEncoder().encode(raw).length>MAX_IMPORT_BYTES)throw Error('Import must be under 1 MiB');
 let document;try{document=JSON.parse(raw);}catch{throw Error('Invalid JSON');}
 if(!document||document.schema_version!==1||!Array.isArray(document.records)||document.records.length>MAX_RECORDS)throw Error('Unsupported tracker format');
 const seen=new Set();return document.records.map(r=>{
  const x=normaliseRecord(r);if(seen.has(x.id))throw Error('Duplicate IDs found');seen.add(x.id);return x;
 });
}
export function exportTrackerJSON(records){return JSON.stringify({schema_version:1,records:records.map(r=>normaliseRecord(r))},null,2);}
function csvCell(value){let s=String(value??'');if(/^[\s]*[=+@\-\t\r]/.test(s))s="'"+s;return '"'+s.replaceAll('"','""')+'"';}
export function exportTrackerCSV(records){return '\ufeff'+[CSV_FIELDS.join(','),...records.map(r=>{const clean=normaliseRecord(r);return CSV_FIELDS.map(k=>csvCell(clean[k])).join(',');})].join('\r\n')+'\r\n';}
export function parseTrackerCSV(content){
 if(typeof content!=='string'||new TextEncoder().encode(content).length>MAX_IMPORT_BYTES)throw Error('CSV too large');
 const text=content.replace(/^\ufeff/,'');let fields=[],rows=[],field='',quoted=false;
 for(let i=0;i<text.length;i++){
  const c=text[i]; if(quoted){if(c==='"'){if(text[i+1]==='"'){field+='"';i++;}else quoted=false;}else field+=c;}
  else if(c==='"'){if(field!=='')throw Error('Malformed CSV quotes');quoted=true;}
  else if(c===','){fields.push(field);field='';}
  else if(c==='\n'||c==='\r'){if(c==='\r'&&text[i+1]==='\n')i++;fields.push(field);field='';if(fields.some(x=>x!==''))rows.push(fields);fields=[];}
  else field+=c;
  if(rows.length>MAX_RECORDS+1)throw Error('Too many CSV rows');
 }
 if(quoted)throw Error('Unterminated CSV quoted field');
 if(fields.length||field){fields.push(field);if(fields.some(x=>x!==''))rows.push(fields);}
 if(!rows.length||JSON.stringify(rows[0])!==JSON.stringify(CSV_FIELDS))throw Error('Unrecognised CSV columns');
 const seen=new Set();return rows.slice(1).map(row=>{
  if(row.length!==CSV_FIELDS.length)throw Error('CSV row has wrong field count');
  const obj=Object.fromEntries(CSV_FIELDS.map((k,i)=>[k,row[i]]));
  // Escaped spreadsheet-leading quote from our own exports is a transport sentinel.
  for(const k of ['company','role','notes','work_location'])if(/^'[=+@-]/.test(obj[k]))obj[k]=obj[k].slice(1);
  const norm=normaliseRecord(obj);if(seen.has(norm.id))throw Error('Duplicate IDs in CSV');seen.add(norm.id);return norm;
 });
}
function icsText(s){return s.replaceAll('\\','\\\\').replaceAll('\n','\\n').replaceAll(',','\\,').replaceAll(';','\\;').replaceAll('\r','');}
export function followupIcs(record){
 const r=normaliseRecord(record);if(!r.followup_date)throw Error('Set a follow-up date first');
 const day=r.followup_date.replaceAll('-','');
 const uid=r.id.replace(/[^A-Za-z0-9.-]/g,'-')+'@overseasjob.in';
 const dateStamp=new Date(r.updated_at).toISOString().replaceAll('-','').replaceAll(':','').replace(/\.\d{3}/,'');
 const lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//OverseasJob.in//Local Followup//EN','BEGIN:VEVENT',`UID:${uid}`,`DTSTAMP:${dateStamp}`,`DTSTART;VALUE=DATE:${day}`,`SUMMARY:${icsText('Follow up: '+r.role+' at '+r.company)}`,`DESCRIPTION:${icsText('User-entered reminder. Not an automated notification. '+(r.url||''))}`,'END:VEVENT','END:VCALENDAR',''];
 return lines.join('\r\n');
}
export function saveFromJob({jobId,title,company,url},store=globalThis.localStorage){
 const items=readTracker(store);const existing=items.find(x=>x.id===jobId);if(existing)return items;
 const now=new Date().toISOString();items.push(normaliseRecord({id:jobId,role:title,company:company||'Company not disclosed',url,status:'saved',notes:'Saved manually from OverseasJob.in',created_at:now,updated_at:now}));
 writeTracker(items,store);return items;
}
