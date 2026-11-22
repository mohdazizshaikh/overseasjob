import {salarySavings,offerCompare,overlapSlots} from './calculators.mjs';
import {saveFromJob,TRACKER_KEY} from './tracker.mjs';
const $=(sel,root=document)=>root.querySelector(sel);
const format=(n,curr='INR')=>new Intl.NumberFormat('en-IN',{style:'currency',currency:curr,maximumFractionDigits:2}).format(n);
const number=(name,root=document)=>{const f=$(`[name="${name}"]`,root);return f?f.value:'0'};
function say(target,msg,isError=false){const el=$(target);if(!el)return;el.textContent=msg;el.classList.toggle('is-error',isError);}
const salaryForm=$('#salary-form');
if(salaryForm){
 salaryForm.addEventListener('submit',e=>{
  e.preventDefault();try{
   const v={salary:number('salary',salaryForm),period:number('period',salaryForm),basis:number('basis',salaryForm),rate:number('rate',salaryForm),deduction:number('deduction',salaryForm),oneTime:number('oneTime',salaryForm)};
   for(const k of ['rent','food','transport','utilities','insurance','other'])v[k]=number(k,salaryForm);
   if(v.basis==='net'){v.rate=0;v.deduction=0;}
   const r=salarySavings(v),currency=(number('currency',salaryForm)||'INR').toUpperCase();
   $('#salary-result').hidden=false;
   say('#salary-total',format(r.monthlySavings,currency));say('#salary-take-home',format(r.monthlyTakeHome,currency));
   say('#salary-expenses',format(r.monthlyExpenses,currency));say('#salary-first-year',format(r.firstYearAfterMove,currency));
   say('#salary-rate',r.savingsRate===null?'N/A':`${r.savingsRate.toFixed(1)}%`);
   say('#salary-feedback',r.monthlySavings<0?'Your estimated expenses exceed take-home pay. Review the inputs.':'Estimates use only the figures you entered.');
  }catch(err){say('#salary-feedback',err.message,true);$('#salary-result').hidden=true;}
 });
 const basis=$('[name="basis"]',salaryForm);
 basis.addEventListener('change',()=>{for(const n of ['rate','deduction'])$(`[name="${n}"]`,salaryForm).disabled=basis.value==='net';});
 basis.dispatchEvent(new Event('change'));
}
const compareForm=$('#compare-form');
if(compareForm)compareForm.addEventListener('submit',e=>{
 e.preventDefault();try{
  const read=(p)=>Object.fromEntries(['base','period','currency','deductions','costs','move','conditionalBonus','guaranteedCash','hoursWeek','weeksYear','fxToA'].map(k=>[k,number(p+k,compareForm)]));
  const result=offerCompare(read('a'),read('b'));$('#compare-result').hidden=false;
  say('#compare-a',format(result.a.firstYearSavings,result.a.currency));say('#compare-b',format(result.b.firstYearSavings,result.b.currency));
  say('#compare-diff',result.comparable?`${format(Math.abs(result.difference),result.displayCurrency)} difference under entered assumptions.`:result.reason);
  say('#compare-feedback','One-time move costs are deducted; conditional bonuses are excluded. Higher savings do not prove a job is better or legitimate.');
 }catch(err){say('#compare-feedback',err.message,true);$('#compare-result').hidden=true;}
});
const tzForm=$('#tz-form');
if(tzForm)tzForm.addEventListener('submit',e=>{
 e.preventDefault();try{
  const opts=Object.fromEntries(['date','zoneA','zoneB','startA','endA','startB','endB','minutes'].map(k=>[k,number(k,tzForm)]));
  const res=overlapSlots(opts);$('#tz-result').hidden=false;
  const holder=$('#tz-slots');holder.replaceChildren();
  if(!res.slots.length)holder.textContent='No overlap found for these hours. Try a different date or shift.';
  for(const slot of res.slots){const p=document.createElement('p');p.textContent=`${slot.a} (${opts.zoneA}) ↔ ${slot.b} (${opts.zoneB})`;holder.append(p);}
  say('#tz-total',`${res.overlapMinutes} available overlapping minutes (across the selected working windows)`);
 }catch(err){say('#tz-feedback',err.message,true);$('#tz-result').hidden=true;}
});
const PROFILE_KEY='overseasjob_mycareer_v1';
function getProfile(){try{const raw=localStorage.getItem(PROFILE_KEY);const v=raw?JSON.parse(raw):{};return v&&typeof v==='object'?v:{};}catch{return {};}}
function writeProfile(p){try{localStorage.setItem(PROFILE_KEY,JSON.stringify(p));return true;}catch{say('#career-message','Browser storage unavailable. Preferences will not survive a reload.',true);return false;}}
function saveJob(button){const id=button.dataset.jobId; if(!id||id.length>160)return;
 const p=getProfile();const saves=Array.isArray(p.saved)?p.saved:[];
 const next=saves.includes(id)?saves.filter(x=>x!==id):[...saves,id].slice(0,2000);
 p.saved=next;writeProfile(p);
 if(next.includes(id)){
   try{saveFromJob({jobId:id,title:button.dataset.jobTitle||'Role not disclosed',company:button.dataset.jobCompany||'Company not disclosed',url:button.dataset.jobUrl||''});}
   catch(err){say('#career-message','Job saved in Radar; application tracker could not store it: '+err.message,true);}
 }
 button.textContent=next.includes(id)?'Saved ✓':'Save job';button.setAttribute('aria-pressed',String(next.includes(id)));
}
document.querySelectorAll('[data-job-id]').forEach(b=>{
 const id=b.dataset.jobId;const saved=getProfile().saved||[];
 b.textContent=saved.includes(id)?'Saved ✓':'Save job';b.setAttribute('aria-pressed',String(saved.includes(id)));
 b.addEventListener('click',()=>saveJob(b));
});
const career=$('#career-form');
if(career){
 const profile=getProfile();for(const key of ['role','workPath']){if(profile[key]&&$(`[name="${key}"]`,career))$(`[name="${key}"]`,career).value=profile[key];}
 const jobsNode=$('#career-data');let jobs=[];
 try{jobs=JSON.parse(jobsNode?.textContent||'[]');if(!Array.isArray(jobs))jobs=[];}catch{}
 const display=()=>{
  const p=getProfile(),root=$('#career-results');root.replaceChildren();
  const role=(p.role||'').toLowerCase().trim(),path=p.workPath||'remote_from_india';
  const matching=jobs.filter(j=>{
   if(path==='remote_from_india'&&j.eligibility!=='allowed')return false;
   if(path==='remote_other'&&j.eligibility==='allowed')return false;
   if(path==='onsite_abroad'&&j.workMode!=='onsite')return false;
   return !role||j.title.toLowerCase().includes(role);
  });
  if(!matching.length){root.textContent='No confirmed matches from currently refreshed sources. Explore tools or broaden your preferences.';return;}
  const seen=new Set(Array.isArray(p.seen)?p.seen:[]);
  for(const j of matching.slice(0,30)){
   const row=document.createElement('p');const a=document.createElement('a');a.href=j.url;a.textContent=j.title;
   row.append(a,document.createTextNode(seen.has(j.id)?' · Viewed in Radar':' · New to your Radar (not necessarily newly published)'));root.append(row);
  }
  p.seen=[...new Set([...seen,...matching.map(j=>j.id)])].slice(-2000);p.lastViewAt=new Date().toISOString();writeProfile(p);
 };
 career.addEventListener('submit',e=>{e.preventDefault();const p=getProfile();p.role=number('role',career).slice(0,100);p.workPath=number('workPath',career);writeProfile(p);display();say('#career-message','Preferences saved on this device only. No offline alert service is active.');});
 display();
 const exp=$('#profile-export');if(exp)exp.addEventListener('click',()=>{
  const raw=JSON.stringify({schema_version:1,profile:getProfile()},null,2);
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([raw],{type:'application/json'}));a.download='overseasjob-career-profile.json';a.click();URL.revokeObjectURL(a.href);
 });
 const del=$('#profile-delete');if(del)del.addEventListener('click',()=>{if(confirm('Delete all saved OverseasJob data on this device?')){try{localStorage.removeItem(PROFILE_KEY);localStorage.removeItem(TRACKER_KEY);}catch{}location.reload();}});
 const importer=$('#profile-import');if(importer)importer.addEventListener('change',async()=>{
  const file=importer.files?.[0];if(!file||file.size>1024*1024){say('#career-message','JSON import must be under 1 MiB.',true);return;}
  try{const value=JSON.parse(await file.text());if(value.schema_version!==1||typeof value.profile!=='object'||Array.isArray(value.profile))throw new Error('Incompatible profile version');
    const clean={role:String(value.profile.role||'').slice(0,100),workPath:['remote_from_india','remote_other','onsite_abroad'].includes(value.profile.workPath)?value.profile.workPath:'remote_from_india',
    saved:Array.isArray(value.profile.saved)?value.profile.saved.filter(x=>typeof x==='string'&&x.length<160).slice(0,2000):[],
    seen:Array.isArray(value.profile.seen)?value.profile.seen.filter(x=>typeof x==='string'&&x.length<160).slice(0,2000):[]};
    writeProfile(clean);location.reload();
  }catch(err){say('#career-message',`Import rejected: ${err.message}`,true);}
 });
}
