import {CONSENT_KEY,canCount,choiceFromStorage,clearOverseasJobData,privacyEventForPath} from './privacy.mjs';
const signals={gpc:navigator.globalPrivacyControl===true,dnt:navigator.doNotTrack||window.doNotTrack||'0'};
const $=s=>document.querySelector(s);
const privacy=$('#privacy-controls');
const storage=(()=>{try{return window.localStorage;}catch{return null;}})();
if(privacy){
 const optin=$('#privacy-count-optin'),feedback=$('#privacy-feedback');
 optin.checked=choiceFromStorage(storage)==='yes';
 if(signals.gpc||signals.dnt==='1'||signals.dnt==='yes'){
  optin.checked=false;optin.disabled=true;feedback.textContent='Your browser has enabled a privacy preference (GPC/Do Not Track), so anonymous usage counting remains disabled.';
 }
 optin.addEventListener('change',()=>{
  try{storage.setItem(CONSENT_KEY,optin.checked?'yes':'no');feedback.textContent=optin.checked?'Consent saved for anonymous aggregate usage counts only; no cross-site identifiers.':'Optional usage counting disabled.';}
  catch{optin.checked=false;feedback.textContent='Browser storage unavailable; optional metrics remain disabled.';}
 });
 $('#privacy-delete-all')?.addEventListener('click',()=>{
  if(!confirm('Delete ALL OverseasJob local profile, tools, application, daily mission and consent data on this device?'))return;
  try{const n=clearOverseasJobData(storage);optin.checked=false;feedback.textContent=`Deleted ${n} OverseasJob local data entries. External downloads and server-side job reports are not affected.`;}
  catch{feedback.textContent='Could not clear browser storage. Try clearing website data in browser settings.';}
 });
}
const enabled=document.body?.dataset.analyticsEnabled==='1';
if(enabled&&canCount(choiceFromStorage(storage),signals)){
 const event=privacyEventForPath(location.pathname);
 if(event){
  try{fetch('/api/privacy-event',{method:'POST',credentials:'omit',cache:'no-store',keepalive:false,
   headers:{'Content-Type':'application/json'},body:JSON.stringify({event})}).catch(()=>{});
  }catch{} // Metrics never interrupt core career tools.
 }
 const applyLinks=document.querySelectorAll('a[data-offsite-apply]');
 for(const a of applyLinks)a.addEventListener('click',()=>{
  try{fetch('/api/privacy-event',{method:'POST',credentials:'omit',cache:'no-store',keepalive:true,
   headers:{'Content-Type':'application/json'},body:JSON.stringify({event:'outbound_apply'})}).catch(()=>{});
  }catch{}
 });
}
