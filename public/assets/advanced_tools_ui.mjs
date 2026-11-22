import {interviewQuestions,selfReview,parseChecklist,reviewNeeded,cleanProgress,portfolioDocument,roadmapPlan,MAX_PROJECTS,checklistStorageKey} from './advanced_tools.mjs';
const $=s=>document.querySelector(s);
const input=(form,name)=>form.elements.namedItem(name)?.value??'';
const status=(id,message)=>{const el=$(id);if(el)el.textContent=message;};
const jsonData=id=>{try{return JSON.parse($(id)?.textContent||'{}');}catch{return {};}};
function download(name,text,type='application/json'){
 const blob=new Blob([text],{type:type+';charset=utf-8'}),u=URL.createObjectURL(blob),a=document.createElement('a');
 a.href=u;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(u),1200);
}
function node(tag,text,cls){const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(cls)el.className=cls;return el;}
function safeLocalGet(key){try{return localStorage.getItem(key);}catch{return null;}}
function safeLocalSet(key,value){try{localStorage.setItem(key,value);return true;}catch{return false;}}

// T07. Questions and rubrics are original static editorial exercises, never live LLM calls.
const interview=$('#interview-form');
if(interview){
 const bank=jsonData('#interview-data'),box=$('#interview-question'),rubric=$('#interview-rubric');
 let questions=[],position=0,selected=null,startAt=0,elapsed=0,timer=null;
 const timerOutput=$('#interview-timer');
 const redrawTime=()=>timerOutput.textContent=`Practice timer: ${Math.floor((elapsed+(startAt?Date.now()-startAt:0))/60000).toString().padStart(2,'0')}:${Math.floor((elapsed+(startAt?Date.now()-startAt:0))%60000/1000).toString().padStart(2,'0')}`;
 const stop=()=>{if(startAt){elapsed+=Date.now()-startAt;startAt=0;}if(timer){clearInterval(timer);timer=null;}redrawTime();};
 const show=()=>{
  stop();elapsed=0;redrawTime();selected=questions[position];
  box.replaceChildren(node('h3',selected.prompt),node('p',`Practice objective: ${selected.objective}`),node('p',`Suggested answer structure: ${selected.sample_structure}`));
  rubric.replaceChildren();selected.rubric.forEach((criterion,i)=>{
   const label=node('label');const c=document.createElement('input');c.type='checkbox';c.name='rubric'+i;label.append(c,document.createTextNode(' '+criterion));rubric.append(label);
  });
  $('#interview-answer').value='';status('#interview-message',`Question ${position+1} of ${questions.length}. Your answer stays in this browser session.`);
 };
 const choose=()=>{try{questions=interviewQuestions(bank,input(interview,'role'));position=0;show();}catch(err){status('#interview-message',err.message);}};
 interview.addEventListener('change',e=>{if(e.target===interview.elements.namedItem('role'))choose();});
 $('#interview-next').addEventListener('click',()=>{if(questions.length){position=(position+1)%questions.length;show();}});
 $('#interview-review').addEventListener('click',()=>{if(!selected)return;try{const checks=[...rubric.querySelectorAll('input')].map(x=>x.checked);const result=selfReview(selected,checks);status('#interview-message',`Self-review: covered ${result.covered} of ${result.total} rubric points. ${result.uncovered.length?'Consider: '+result.uncovered.join('; '):'All rubric prompts reviewed.'} This is NOT a hiring score.`);}catch(err){status('#interview-message',err.message);}});
 $('#interview-timer-start').addEventListener('click',()=>{if(!startAt){startAt=Date.now();timer=setInterval(redrawTime,1000);}});
 $('#interview-timer-pause').addEventListener('click',stop);
 $('#interview-timer-reset').addEventListener('click',()=>{stop();elapsed=0;redrawTime();});
 $('#interview-export').addEventListener('click',()=>{if(!selected)return;download('overseasjob-interview-practice.txt',`Practice question: ${selected.prompt}\nObjective: ${selected.objective}\n\nYour own draft answer:\n${$('#interview-answer').value.slice(0,4000)}\n\nSelf-review prompts:\n${selected.rubric.join('\n')}\n\nNot an employer evaluation.`, 'text/plain');});
 window.addEventListener('pagehide',stop,{once:true});choose();
}

// T08. Only generic planning tasks; no automatic immigration eligibility claims.
const checklist=$('#checklist-form');
if(checklist){
 const raw=jsonData('#checklist-data');let data=null, country='', checked=new Set();const keyFor=checklistStorageKey;
 const save=()=>{if(!safeLocalSet(keyFor(country),JSON.stringify({schema_version:1,country,checked:[...checked]})))status('#checklist-message','Browser storage unavailable. Export your progress to avoid losing it.');};
 const paint=()=>{
  const p=data.country_profiles[country],list=$('#checklist-items'),refs=$('#checklist-official');list.replaceChildren();refs.replaceChildren();
  status('#checklist-warning',reviewNeeded(p)?'Country reference review date has passed. Recheck the official sources before relying on any requirement.':'Official starting links researched on '+p.research_date+'; this is not visa eligibility advice.');
  for(const i of data.items){const label=node('label',undefined,'checklist-item'),c=document.createElement('input');c.type='checkbox';c.checked=checked.has(i.id);c.addEventListener('change',()=>{c.checked?checked.add(i.id):checked.delete(i.id);save();progress();});label.append(c,document.createTextNode(' '+i.label));list.append(label);}
  p.official_resources.forEach(r=>{const line=node('li'),a=node('a',r.title);a.href=r.url;a.target='_blank';a.rel='noopener noreferrer nofollow';line.append(a);refs.append(line);});progress();
 };
 const progress=()=>status('#checklist-message',`${checked.size}/${data.items.length} generic planning tasks ticked. This does not establish any visa or employment eligibility.`);
 const change=()=>{country=input(checklist,'country');checked=new Set();const stored=safeLocalGet(keyFor(country));if(stored){try{const v=cleanProgress(JSON.parse(stored),data,country);checked=new Set(v.checked);}catch{}}paint();};
 try{data=parseChecklist(raw);checklist.addEventListener('change',e=>{if(e.target===checklist.elements.namedItem('country'))change();});change();}catch(err){status('#checklist-message',err.message);}
 $('#checklist-export').addEventListener('click',()=>download('overseasjob-checklist-'+country+'.json',JSON.stringify({schema_version:1,country,checked:[...checked]},null,2)));
 $('#checklist-import').addEventListener('change',async e=>{
  const file=e.target.files?.[0];if(!file)return;
  if(file.size>16384){status('#checklist-message','Import rejected: file too large.');return;}
  try{const cleaned=cleanProgress(JSON.parse(await file.text()),data,country);checked=new Set(cleaned.checked);save();paint();}catch(err){status('#checklist-message','Import rejected: '+err.message);}finally{e.target.value='';}
 });
 $('#checklist-delete').addEventListener('click',()=>{if(confirm('Delete checklist progress on this device?')){checked.clear();try{localStorage.removeItem(keyFor(country));}catch{}paint();}});
}

// T09. Export generated standalone static HTML only; never publish a portfolio to the server.
const portfolio=$('#portfolio-form');
if(portfolio){
 const holder=$('#portfolio-projects');let count=0;let built='';
 function addProject(){if(count>=MAX_PROJECTS){status('#portfolio-message','Maximum 10 projects.');return;}
  count++;const box=node('fieldset',undefined,'portfolio-project');box.dataset.index=String(count);
  const legend=node('legend','Project '+count);box.append(legend);
  const fields=[['title','Project title','text',180],['description','What you actually did','textarea',2500],['skills','Skills practised (self-reported)','text',500],['url','Evidence or project link (HTTPS, optional)','url',1024]];
  for(const [name,labelText,type,max] of fields){const label=node('label',labelText),field=document.createElement(type==='textarea'?'textarea':'input');if(type!=='textarea')field.type=type;else field.rows=4;field.name=`p${count}_${name}`;field.maxLength=max;if(['title','description'].includes(name))field.required=true;label.append(field);box.append(label);}
  holder.append(box);
 }
 const collect=()=>({name:input(portfolio,'name'),role:input(portfolio,'role'),summary:input(portfolio,'summary'),projects:[...holder.querySelectorAll('fieldset')].map((_,i)=>({title:input(portfolio,`p${i+1}_title`),description:input(portfolio,`p${i+1}_description`),skills:input(portfolio,`p${i+1}_skills`),url:input(portfolio,`p${i+1}_url`)}))});
 const prepare=()=>{built=portfolioDocument(collect());const data=collect();$('#portfolio-preview').replaceChildren(node('h3',data.name+' — '+data.role),node('p','Self-reported work; not independently verified.'),node('p',data.summary));for(const p of data.projects){$('#portfolio-preview').append(node('h4',p.title),node('p',p.description));}status('#portfolio-message','Preview created locally. Review your claims before downloading or sharing.');};
 portfolio.addEventListener('submit',e=>{e.preventDefault();try{prepare();}catch(err){status('#portfolio-message',err.message);}});
 $('#portfolio-add').addEventListener('click',addProject);
 $('#portfolio-remove').addEventListener('click',()=>{if(count<=1){status('#portfolio-message','Keep at least one project.');return;}holder.lastElementChild?.remove();count--;status('#portfolio-message','Last project removed.');});
 $('#portfolio-export').addEventListener('click',()=>{try{prepare();download('overseasjob-self-reported-portfolio.html',built,'text/html');}catch(err){status('#portfolio-message',err.message);}});
 $('#portfolio-reset').addEventListener('click',()=>{portfolio.reset();holder.replaceChildren();count=0;addProject();built='';$('#portfolio-preview').replaceChildren();status('#portfolio-message','Fields reset. No data was stored remotely.');});
 addProject();
}

// T10. Curated deterministic plans; user comments are ephemeral until they export.
const roadmap=$('#roadmap-form');
if(roadmap){
 const data=jsonData('#roadmap-data'),view=$('#roadmap-result');let latest=null;
 const render=()=>{
  const notes={};for(const d of [30,60,90])notes[d]=$(`#roadmap-note-${d}`)?.value||'';
  latest=roadmapPlan(data,input(roadmap,'track'),input(roadmap,'hours'),notes);
  view.replaceChildren(node('h2',latest.roleName+' — practice plan'),node('p',latest.planningAssumption));
  view.append(node('p','Prerequisites: '+latest.prerequisites.join('; ')));
  for(const m of latest.milestones){const section=node('section',undefined,'roadmap-milestone');section.append(node('h3',`Day ${m.day}: ${m.title}`),node('p',`Time budget: ${m.estimatedHours} hours across a four-week study block`));
   const ul=node('ul');for(const p of m.practice)ul.append(node('li',p));section.append(ul,node('p','Evidence goal: '+m.deliverable));
   const label=node('label','Your own adjustment (optional)'),textarea=document.createElement('textarea');textarea.id=`roadmap-note-${m.day}`;textarea.rows=2;textarea.maxLength=600;textarea.value=m.customNote;label.append(textarea);section.append(label);view.append(section);
  }
  view.append(node('h3','Reviewed learning starting points'));const ul=node('ul');for(const r of latest.resources){const li=node('li'),a=node('a',r.title);a.href=r.url;a.target='_blank';a.rel='noopener noreferrer nofollow';li.append(a);ul.append(li);}view.append(ul,node('p',latest.disclaimer));
  status('#roadmap-message','Plan generated on this device. Edit your own milestones; use Export to keep a copy.');
 };
 roadmap.addEventListener('submit',e=>{e.preventDefault();try{render();}catch(err){status('#roadmap-message',err.message);}});
 $('#roadmap-export').addEventListener('click',()=>{try{render();download('overseasjob-career-roadmap.json',JSON.stringify(latest,null,2));}catch(err){status('#roadmap-message',err.message);}});
 $('#roadmap-print').addEventListener('click',()=>{try{render();window.print();}catch(err){status('#roadmap-message',err.message);}});
 $('#roadmap-reset').addEventListener('click',()=>{roadmap.reset();view.replaceChildren();latest=null;status('#roadmap-message','Plan cleared. Nothing is sent to a server.');});
}
