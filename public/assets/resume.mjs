// T03 local-only resume and cover-letter templates; no AI-generated experience or ATS-score claim.
const MAX={name:120,role:140,email:180,phone:80,location:140,summary:1500,skills:1800,
  experience:5000,education:2500,projects:3000,evidence:1024,employer:180,
  achievement1:1400,achievement2:1400,recipient:180};
export function escapeHTML(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
const read=(v,key)=>{
  const s=String(v?.[key]??'').trim();
  if(s.length>MAX[key])throw new Error(`${key} exceeds its length limit.`);
  return s;
};
export function safeEvidenceLink(raw){
  if(!raw)return null;
  try{const url=new URL(raw);if(url.protocol!=='https:'||url.username||url.password||url.port||url.hostname==='localhost'||/\.(local|internal)$/i.test(url.hostname)||!url.hostname.includes('.'))throw Error();return url.toString();}
  catch{throw new Error('Evidence link must be a direct HTTPS URL without credentials.');}
}
const sections={experience:'Experience / Work history',projects:'Projects and work evidence',skills:'Skills',education:'Education'};
export function validateResume(v){
  const template=String(v?.template||'chronological');
  if(!['chronological','skills','fresher'].includes(template))throw new Error('Invalid resume layout.');
  const record=Object.fromEntries(Object.keys(MAX).map(k=>[k,read(v,k)]));
  if(!record.name)throw new Error('Your name is required.');
  if(!record.role)throw new Error('The target role is required.');
  if(record.email && !/^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(record.email))throw new Error('Invalid contact email.');
  record.evidence=safeEvidenceLink(record.evidence)||'';
  record.template=template;
  return record;
}
export function toPlainResume(v){
  const r=validateResume(v);
  const order=r.template==='skills'?['skills','projects','experience','education']:
    r.template==='fresher'?['education','projects','skills','experience']:['experience','projects','education','skills'];
  let content=`${r.name}\n${r.role}\n${[r.email,r.phone,r.location,r.evidence].filter(Boolean).join(' | ')}`;
  if(r.summary)content+=`\n\nPROFESSIONAL SUMMARY\n${r.summary}`;
  for(const key of order)if(r[key])content+=`\n\n${sections[key].toUpperCase()}\n${r[key]}`;
  return content.trim()+'\n';
}
const headStyle=`body{font:15px/1.5 Arial,Helvetica,sans-serif;color:#172735;margin:0 auto;max-width:790px;padding:34px}h1{font-size:26px;margin-bottom:4px}h2{font-size:15px;letter-spacing:.06em;text-transform:uppercase;border-bottom:1px solid #c8d1db;padding-bottom:5px;margin:22px 0 8px}p{white-space:pre-wrap;overflow-wrap:anywhere}header p{margin:2px 0;color:#364958}a{color:#153b65}main{max-width:100%}@media print{@page{size:A4;margin:16mm}body{padding:0;max-width:none;print-color-adjust:exact}}`;
export function resumeHTML(v){
  const r=validateResume(v);
  const order=r.template==='skills'?['skills','projects','experience','education']:
    r.template==='fresher'?['education','projects','skills','experience']:['experience','projects','education','skills'];
  const header=[r.email,r.phone,r.location].filter(Boolean).map(escapeHTML).join(' · ');
  const link=r.evidence?`<p><a href="${escapeHTML(r.evidence)}" rel="noopener noreferrer">${escapeHTML(r.evidence)}</a></p>`:'';
  let main=r.summary?`<section><h2>Professional summary</h2><p>${escapeHTML(r.summary)}</p></section>`:'';
  for(const key of order)if(r[key])main+=`<section><h2>${escapeHTML(sections[key])}</h2><p>${escapeHTML(r[key])}</p></section>`;
  return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+escapeHTML(r.name)+' — Resume</title><style>'+headStyle+'</style></head><body><header><h1>'+escapeHTML(r.name)+'</h1><p>'+escapeHTML(r.role)+'</p><p>'+header+'</p>'+link+'</header><main>'+main+'</main></body></html>';
}
export function coverLetterText(v){
  const r=validateResume(v);
  if(!r.employer)throw new Error('Enter the recipient organisation for the cover letter.');
  if(!r.achievement1||!r.achievement2)throw new Error('Enter both evidence-based achievement paragraphs.');
  return [r.name,[r.email,r.phone,r.location].filter(Boolean).join(' | '),'',r.recipient||'Hiring team',r.employer,'',`Re: Application for ${r.role}`,'',
    `Dear ${r.recipient||'Hiring team'},`,'',`I am applying for the ${r.role} position at ${r.employer}.`, '',r.achievement1,'',r.achievement2,'',
    'I would welcome the opportunity to discuss how my experience and projects relate to the role.','',`Sincerely,\n${r.name}`].join('\n')+'\n';
}
