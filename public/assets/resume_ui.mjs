import {validateResume,toPlainResume,resumeHTML,coverLetterText} from './resume.mjs';
const form=document.getElementById('resume-form');
if(form){
 const $=(s)=>document.querySelector(s);
 const keys=['template','name','role','email','phone','location','summary','skills','experience','education','projects','evidence','employer','recipient','achievement1','achievement2'];
 const input=()=>Object.fromEntries(keys.map(k=>[k,form.elements.namedItem(k)?.value??'']));
 const say=(v)=>{$('#resume-feedback').textContent=v;};
 const download=(file,text,type)=>{const href=URL.createObjectURL(new Blob([text],{type}));const a=document.createElement('a');a.href=href;a.download=file;a.click();setTimeout(()=>URL.revokeObjectURL(href),1000);};
 const preview=(type)=>{try{const data=input(),value=type==='cover'?coverLetterText(data):toPlainResume(data);$('#resume-preview').textContent=value;say('Preview contains only your entered information. Export to retain a copy.');return value;}catch(err){say(err.message);return null;}};
 $('#resume-preview-button').addEventListener('click',()=>preview('resume'));
 $('#resume-export-text').addEventListener('click',()=>{const content=preview('resume');if(content)download('overseasjob-resume.txt',content,'text/plain;charset=utf-8');});
 $('#resume-export-html').addEventListener('click',()=>{try{const html=resumeHTML(input());download('overseasjob-resume.html',html,'text/html;charset=utf-8');preview('resume');}catch(err){say(err.message);}});
 $('#resume-export-cover').addEventListener('click',()=>{const content=preview('cover');if(content)download('overseasjob-cover-letter.txt',content,'text/plain;charset=utf-8');});
 $('#resume-print').addEventListener('click',()=>{const content=preview('resume');if(content){$('#resume-preview').classList.add('resume-print-view');window.print();}});
 $('#resume-reset').addEventListener('click',()=>{form.reset();$('#resume-preview').textContent='';say('Editor reset. OverseasJob.in did not store your data.');});
}
