/** T07–T10: deterministic, browser-only utilities. No network calls, AI model or file uploads. */
export const MAX_PROJECTS = 10;
const roles = ['it_support','customer_support','administration'];
const tracks = ['it_support','customer_support','junior_web_dev'];
const isObject = x => x !== null && typeof x === 'object' && !Array.isArray(x);
const bounded = (value,limit=600) => {
  const s = String(value ?? '').trim();
  if(s.length>limit) throw new Error(`Text exceeds ${limit} characters`);
  return s;
};
export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export function safeHttps(url){
  if(!url || !String(url).trim())return null;
  const value=bounded(url,1024);
  if(/[\u0000-\u001f\u007f]/.test(value))throw new Error('Invalid link');
  let u;try{u=new URL(value);}catch{throw new Error('Enter an absolute HTTPS link');}
  if(u.protocol!=='https:'||!u.hostname||u.username||u.password||/^(localhost|127\.|0\.|10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.|\[?(?:::1|fc|fd|fe80))/i.test(u.hostname))throw new Error('Only safe public HTTPS links are accepted');
  return u.href;
}
export function interviewQuestions(bank,role){
  if(!isObject(bank)||!Array.isArray(bank.questions))throw new Error('Interview question bank unavailable');
  if(!roles.includes(role))throw new Error('Unknown interview role');
  const matches=bank.questions.filter(q=>q.role===role);
  const ids=new Set();
  for(const q of matches){
    if(!isObject(q)||!/^[-a-z0-9]{3,35}$/.test(String(q.id))||ids.has(q.id)||!bounded(q.prompt,800)||!bounded(q.objective,300)||!Array.isArray(q.rubric)||q.rubric.length<2||q.rubric.length>6||q.rubric.some(x=>!bounded(x,300)))throw new Error('Invalid or duplicate question-bank entry');
    ids.add(q.id);
  }
  if(matches.length<3)throw new Error('Interview bank incomplete');
  return matches;
}
export function selfReview(question,checks){
  if(!question||!Array.isArray(question.rubric)||!Array.isArray(checks)||checks.length!==question.rubric.length||checks.some(x=>typeof x!=='boolean'))throw new Error('Invalid review');
  return {covered:checks.filter(Boolean).length,total:checks.length,uncovered:question.rubric.filter((_,i)=>!checks[i]),disclaimer:'Self-review only; not a hiring score or verified employer assessment'};
}
export function parseChecklist(data){
  if(!isObject(data)||data.version!==1||!isObject(data.country_profiles)||!Array.isArray(data.items))throw new Error('Checklist data unavailable');
  const ids=new Set();
  for(const i of data.items){if(!isObject(i)||!/^[-a-z0-9]{2,36}$/.test(i.id)||ids.has(i.id)||i.generic!==true||!bounded(i.label,300))throw new Error('Invalid generic checklist item');ids.add(i.id);}
  for(const p of Object.values(data.country_profiles)){
    if(!isObject(p)||!/^\d{4}-\d{2}-\d{2}$/.test(p.review_by)||!Array.isArray(p.official_resources)||!p.official_resources.length)throw new Error('Country profile incomplete');
    for(const x of p.official_resources){safeHttps(x.url);if(!bounded(x.title,200))throw new Error('Missing official reference title');}
  }
  return data;
}
export function reviewNeeded(profile,today=new Date().toISOString().slice(0,10)){
  if(!/^\d{4}-\d{2}-\d{2}$/.test(today))throw new Error('Invalid date');
  if(!profile||!/^\d{4}-\d{2}-\d{2}$/.test(profile.review_by))throw new Error('Invalid review schedule');
  return today>profile.review_by;
}
export function checklistStorageKey(country){
  if(!['UAE','Germany'].includes(country))throw new Error('Unknown checklist country');
  return 'overseasjob_country_checklist_v1_'+country;
}
export function cleanProgress(raw,data,country){
  parseChecklist(data);
  if(!Object.prototype.hasOwnProperty.call(data.country_profiles,country))throw new Error('Unknown country');
  if(!isObject(raw)||raw.schema_version!==1||raw.country!==country||!Array.isArray(raw.checked))throw new Error('Unsupported checklist backup');
  const allowed=new Set(data.items.map(i=>i.id));
  if(raw.checked.length>data.items.length)throw new Error('Too many checklist items');
  if(raw.checked.some(v=>typeof v!=='string'||!allowed.has(v)))throw new Error('Unknown checklist item');
  return {schema_version:1,country,checked:[...new Set(raw.checked)]};
}
export function portfolioDocument(raw){
  if(!isObject(raw))throw new Error('Invalid portfolio');
  const name=bounded(raw.name,120),role=bounded(raw.role,140),summary=bounded(raw.summary,1500);
  if(!name||!role)throw new Error('Your real name and target role are required');
  if(!Array.isArray(raw.projects)||raw.projects.length<1||raw.projects.length>MAX_PROJECTS)throw new Error('Include 1–10 genuine or explicitly labelled practice projects');
  const projects=raw.projects.map((p,i)=>{
    if(!isObject(p))throw new Error('Invalid project');
    const title=bounded(p.title,180),description=bounded(p.description,2500),skills=bounded(p.skills,500),url=safeHttps(p.url||'');
    if(!title||!description)throw new Error(`Project ${i+1} needs a title and truthful description`);
    return {title,description,skills,url};
  });
  const lines=projects.map((p,i)=>`<section class="project"><h2>${i+1}. ${escapeHtml(p.title)}</h2><p>${escapeHtml(p.description).replace(/\n/g,'<br>')}</p>${p.skills?`<p><b>Skills practised:</b> ${escapeHtml(p.skills)}</p>`:''}${p.url?`<p><a href="${escapeHtml(p.url)}" rel="noopener noreferrer nofollow" target="_blank">Project or evidence link</a></p>`:''}</section>`).join('\n');
  return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src \'none\'; script-src \'none\'; base-uri \'none\'; form-action \'none\'"><title>'+escapeHtml(name)+' — Self-reported Work Portfolio</title><style>body{font:16px/1.65 system-ui,sans-serif;color:#15283a;max-width:760px;margin:48px auto;padding:0 22px}h1{margin-bottom:0}h2{font-size:1.3em;margin-top:28px}.meta{color:#556779}.notice{background:#eff6fa;padding:12px;border-left:4px solid #206b98}.project{break-inside:avoid;border-top:1px solid #d8e5ec;margin-top:24px}a{color:#0b679e;overflow-wrap:anywhere}@page{size:A4;margin:18mm}@media print{body{margin:0;max-width:none}}</style></head><body><h1>'+escapeHtml(name)+'</h1><p class="meta">'+escapeHtml(role)+'</p><p class="notice"><strong>Self-reported work.</strong> Project details, skills and evidence links have not been independently verified.</p>'+(summary?'<h2>About</h2><p>'+escapeHtml(summary).replace(/\n/g,'<br>')+'</p>':'')+'<h2>Projects</h2>'+lines+'<p class="meta">Generated privately with OverseasJob.in. Review every statement before sharing. Save this file and print to PDF locally if needed.</p></body></html>';
}
export function roadmapPlan(data,track,hoursPerWeek,notes={}){
  if(!isObject(data)||data.version!==1||!isObject(data.tracks)||!tracks.includes(track))throw new Error('Roadmap catalogue unavailable');
  const template=data.tracks[track];
  if(!isObject(template)||!Array.isArray(template.milestones)||template.milestones.length!==3||!Array.isArray(template.resources)||!template.resources.length)throw new Error('Role track incomplete');
  const hours=Number(hoursPerWeek);
  if(!Number.isFinite(hours)||hours<1||hours>40||!Number.isInteger(hours))throw new Error('Use an integer 1–40 study hours per week');
  if(!isObject(notes))throw new Error('Invalid custom notes');
  const milestones=template.milestones.map((m,index)=>{
    if(m.day!==[30,60,90][index]||!Array.isArray(m.practice)||m.practice.length<2||!bounded(m.deliverable,400))throw new Error('Invalid roadmap milestone');
    const customNote=bounded(notes[m.day]||'',600);
    return {day:m.day,title:bounded(m.title,120),practice:m.practice.map(x=>bounded(x,400)),deliverable:m.deliverable,estimatedHours:hours*4,customNote};
  });
  return {schema_version:1,role:track,roleName:template.name,hoursPerWeek:hours,planningAssumption:'Each of the three stages is modelled as four weeks for scheduling; 30/60/90-day names are approximate milestones.',prerequisites:template.prerequisites,resources:template.resources.map(r=>({title:bounded(r.title,160),url:safeHttps(r.url)})),milestones,disclaimer:'Personal practice plan only. Not a job, degree or certificate guarantee.'};
}
