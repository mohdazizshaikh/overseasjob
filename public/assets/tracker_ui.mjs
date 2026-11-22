import {TRACKER_KEY, STATUSES, normaliseRecord,readTracker,writeTracker,importTrackerJSON,exportTrackerJSON,exportTrackerCSV,parseTrackerCSV,followupIcs} from './tracker.mjs';
const $=q=>document.querySelector(q);
const form=$('#tracker-form');
if(form){
 const out=$('#tracker-results'), status=$('#tracker-status');
 const feedback=(message,isError=false)=>{status.textContent=message;status.classList.toggle('is-error',isError);};
 let rows=readTracker();
 const download=(name,body,type)=>{const u=URL.createObjectURL(new Blob([body],{type}));const a=document.createElement('a');a.href=u;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(u),1500);};
 const values=()=>Object.fromEntries(['id','company','role','url','work_location','status','applied_at','followup_date','notes'].map(key=>[key,form.elements.namedItem(key)?.value??'']));
 const reset=()=>{form.reset();form.elements.namedItem('id').value='';form.elements.namedItem('status').value='saved';$('#tracker-submit').textContent='Save application';};
 const persist=()=>{try{writeTracker(rows);return true;}catch(err){feedback('Could not save locally: '+err.message,true);return false;}};
 const render=()=>{
  out.replaceChildren();const sorted=[...rows].sort((a,b)=>(a.followup_date||'9999').localeCompare(b.followup_date||'9999'));
  if(!sorted.length){const p=document.createElement('p');p.textContent='No applications recorded on this device. Use the form to add one.';out.append(p);return;}
  for(const item of sorted){
   const card=document.createElement('article');card.className='tracker-entry';
   const title=document.createElement('h3');title.textContent=`${item.role} — ${item.company}`;card.append(title);
   const line=document.createElement('p');line.className='muted';line.textContent=`Status: ${item.status} · Applied: ${item.applied_at||'not recorded'} · Follow-up: ${item.followup_date||'none'}`;card.append(line);
   if(item.url){const link=document.createElement('a');link.href=item.url;link.textContent='Open saved external link';link.target='_blank';link.rel='noopener noreferrer nofollow';card.append(link);}
   const buttons=document.createElement('div');buttons.className='small-controls';
   const edit=document.createElement('button');edit.type='button';edit.textContent='Edit';edit.className='button button-secondary';edit.addEventListener('click',()=>{for(const field of ['id','company','role','url','work_location','status','applied_at','followup_date','notes']){const el=form.elements.namedItem(field);if(el)el.value=item[field]||'';}$('#tracker-submit').textContent='Update application';form.scrollIntoView({behavior:'smooth',block:'start'});});buttons.append(edit);
   if(item.followup_date){const remind=document.createElement('button');remind.type='button';remind.textContent='Export calendar reminder';remind.className='button button-secondary';remind.addEventListener('click',()=>{try{download('followup-'+item.id.replaceAll(':','-')+'.ics',followupIcs(item),'text/calendar;charset=utf-8');}catch(e){feedback(e.message,true);}});buttons.append(remind);}
   const remove=document.createElement('button');remove.type='button';remove.textContent='Delete';remove.className='button button-text';remove.addEventListener('click',()=>{if(confirm(`Delete ${item.role} at ${item.company}?`)){rows=rows.filter(r=>r.id!==item.id);if(persist()){render();feedback('Deleted from this device only.');}}});buttons.append(remove);
   card.append(buttons);out.append(card);
  }
 };
 form.addEventListener('submit',event=>{
  event.preventDefault();try{
   const vals=values();const now=new Date().toISOString();const prior=rows.find(r=>r.id===vals.id);
   if(!vals.id)vals.id='manual-'+(globalThis.crypto?.randomUUID?.()||`${Date.now()}-${Math.floor(Math.random()*1e9)}`);
   const record=normaliseRecord({...vals,created_at:prior?.created_at||now,updated_at:now});
   rows=rows.filter(r=>r.id!==record.id);rows.push(record);
   if(persist()){reset();render();feedback('Application saved locally. No data was sent to OverseasJob.in.');}
  }catch(e){feedback(e.message,true);}
 });
 $('#tracker-reset').addEventListener('click',reset);
 $('#tracker-json').addEventListener('click',()=>download('overseasjob-applications.json',exportTrackerJSON(rows),'application/json'));
 $('#tracker-csv').addEventListener('click',()=>download('overseasjob-applications.csv',exportTrackerCSV(rows),'text/csv;charset=utf-8'));
 $('#tracker-import').addEventListener('change',async e=>{
  const file=e.target.files?.[0];if(!file||file.size>1024*1024){feedback('Imports must be under 1 MiB.',true);return;}
  try{const text=await file.text();const incoming=file.name.toLowerCase().endsWith('.csv')?parseTrackerCSV(text):importTrackerJSON(text);
   if(!confirm(`Replace ${rows.length} local applications with ${incoming.length} imported records?`))return;
   const old=rows;rows=incoming;if(!persist())rows=old;else{reset();render();feedback('Imported successfully; existing local tracker replaced.');}
  }catch(e){feedback('Import rejected: '+e.message,true);}finally{e.target.value='';}
 });
 $('#tracker-clear').addEventListener('click',()=>{if(confirm('Permanently delete every locally tracked application on this device?')){rows=[];try{localStorage.removeItem(TRACKER_KEY);}catch{}reset();render();feedback('Local application records deleted.');}});
 render();
}
