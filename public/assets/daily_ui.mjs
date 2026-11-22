import {DAILY_KEY,EMPTY_DAILY,dailyMission,normalizeDailyState,recordCompletion,streakFor,utcToday} from './daily.mjs';
const root=document.querySelector('#daily-form');
if(root){
 const $=s=>document.querySelector(s);
 const bank=JSON.parse($('#daily-data')?.textContent||'{}').missions||[];
 const today=utcToday();
 const role=root.elements.namedItem('role');
 let state=EMPTY_DAILY;
 try{const raw=localStorage.getItem(DAILY_KEY);state=raw?normalizeDailyState(JSON.parse(raw)):EMPTY_DAILY;}catch{state=EMPTY_DAILY;}
 const message=(s,bad=false)=>{const node=$('#daily-feedback');node.textContent=s;node.classList.toggle('is-error',bad);};
 const download=(name,text)=>{const u=URL.createObjectURL(new Blob([text],{type:'application/json'}));const a=document.createElement('a');a.href=u;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(u),1000);};
 const render=()=>{
  try{
   const mission=dailyMission(bank,role.value,today),completed=state.completed.find(r=>r.day===today&&r.role===role.value);
   $('#daily-prompt').textContent=mission.prompt;
   $('#daily-deliverable').textContent=`Suggested outcome: ${mission.deliverable||'One practice note'}`;
   const list=$('#daily-steps');list.replaceChildren();for(const s of mission.steps){const item=document.createElement('li');item.textContent=s;list.append(item);}
   $('#daily-complete').disabled=Boolean(completed);
   $('#daily-complete').textContent=completed?'Completed today ✓':'Mark today completed';
   $('#daily-streak').textContent=`${streakFor(state,role.value,today)} day streak for this track`;
   $('#daily-date').textContent=`UTC date: ${today} — one mission per day per track`;
  }catch(e){message(e.message,true);$('#daily-complete').disabled=true;}
 };
 role.addEventListener('change',render);
 root.addEventListener('submit',e=>{
  e.preventDefault();try{state=recordCompletion(state,dailyMission(bank,role.value,today),role.value,today);
   try{localStorage.setItem(DAILY_KEY,JSON.stringify(state));message('Completed on this device. No account or automatic cloud backup.');}
   catch{message('Browser storage unavailable. Your progress will be lost on reload; export it now.',true);}
   render();
  }catch(err){message(err.message,true);}
 });
 $('#daily-export').addEventListener('click',()=>download('overseasjob-daily-progress.json',JSON.stringify(state,null,2)));
 $('#daily-import').addEventListener('change',async e=>{
  const file=e.target.files?.[0];if(!file)return;
  if(file.size>32000){message('Import file exceeds 32 KiB',true);return;}
  try{
   const parsed=normalizeDailyState(JSON.parse(await file.text()));
   // Explicit replace is required; never combine untrusted imported history silently.
   if(!confirm('Replace existing daily mission progress with this backup?'))return;
   state=parsed;try{localStorage.setItem(DAILY_KEY,JSON.stringify(state));}catch{message('Cannot save imported history to this browser',true);return;}
   render();message('Daily progress restored from your backup.');
  }catch(err){message(`Import rejected: ${err.message}`,true);}
 });
 $('#daily-delete').addEventListener('click',()=>{if(!confirm('Delete all Daily Mission progress on this device?'))return;try{localStorage.removeItem(DAILY_KEY);}catch{}state=EMPTY_DAILY;render();message('Daily progress deleted from this device.');});
 render();
}
