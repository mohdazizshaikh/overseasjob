import {PROFILE_KEY,normaliseProfile,importCareer,exportCareer,findRadarMatches,markRadarViewed} from './radar.mjs';
const $=(selector,root=document)=>root.querySelector(selector);
export function getProfile(){try{const raw=localStorage.getItem(PROFILE_KEY);return normaliseProfile(raw?JSON.parse(raw):{});}catch{return normaliseProfile({});}}
export function writeProfile(p){try{localStorage.setItem(PROFILE_KEY,JSON.stringify(normaliseProfile(p)));return true;}catch{return false;}}
export function initRadar(){
 const form=$('#career-form');if(!form)return;
 const tell=(txt,error=false)=>{const el=$('#career-message');el.textContent=txt;el.classList.toggle('is-error',error);};
 const first=getProfile();
 for(const k of ['role','workPath'])if(first[k]&&form.elements.namedItem(k))form.elements.namedItem(k).value=first[k];
 let jobs=[];
 try{jobs=JSON.parse($('#career-data')?.textContent||'[]');if(!Array.isArray(jobs))jobs=[];}catch{tell('Job feed preview unavailable.',true);}
 const render=()=>{
   const area=$('#career-results');area.replaceChildren();
   try{
    const p=getProfile(),matches=findRadarMatches(jobs,p);
    if(!matches.length){area.textContent='No currently confirmed matches from approved sources. Try another role or visit the career tools.';return;}
    const labels={new_to_you:'New to your Radar — not necessarily newly published',details_changed:'Listing details changed since your last Radar visit',previously_seen:'Previously seen — older version unavailable',unchanged:'Previously seen · no observed changes'};
    for(const j of matches.slice(0,50)){
     const row=document.createElement('p'),a=document.createElement('a');a.href=j.url;a.textContent=j.title;
     row.append(a,document.createTextNode(` · ${labels[j.change]}`));area.append(row);
    }
    if(!writeProfile(markRadarViewed(p,matches))){tell('Browser storage is unavailable: results work now, but comparison history cannot be saved.',true);}
   }catch(err){area.textContent='Radar cannot safely display these listings.';tell(err.message,true);}
 };
 form.addEventListener('submit',e=>{e.preventDefault();const p=getProfile();p.role=String(form.elements.namedItem('role').value).slice(0,100);p.workPath=form.elements.namedItem('workPath').value;
  try{if(!writeProfile(p))tell('Preferences are session-only because storage is blocked.',true);else tell('Preferences saved on this device. No background monitoring or email alert is active.');render();}catch(err){tell(err.message,true);}
 });
 render();
 $('#profile-export')?.addEventListener('click',()=>{const url=URL.createObjectURL(new Blob([exportCareer(getProfile())],{type:'application/json'}));const a=document.createElement('a');a.href=url;a.download='overseasjob-career-profile.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});
 $('#profile-delete')?.addEventListener('click',()=>{if(!confirm('Delete all saved OverseasJob data on this device?'))return;
   for(const k of ['overseasjob_mycareer_v1','overseasjob_applications_v1','overseasjob_daily_v1','overseasjob_country_checklists_v1','overseasjob_roadmap_v1','overseasjob_privacy_v1']){try{localStorage.removeItem(k);}catch{}}
   location.reload();
 });
 $('#profile-import')?.addEventListener('change',async e=>{const file=e.target.files?.[0];if(!file)return;
  if(file.size>1024*1024){tell('Profile import must be smaller than 1 MiB.',true);return;}
  try{const profile=importCareer(JSON.parse(await file.text()));if(!confirm('Replace your current career profile with this backup?'))return;
    if(!writeProfile(profile))throw new Error('Browser storage not available');location.reload();}
  catch(err){tell(`Import rejected: ${err.message}`,true);}
 });
}
