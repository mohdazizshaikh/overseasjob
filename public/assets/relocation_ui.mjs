import {relocationBudget,exportBudget} from './relocation.mjs';
const form=document.getElementById('relocation-form');
if(form){
 const $=(selector)=>document.querySelector(selector);
 const keys=['flight','visa','documents','deposit','setup','arrival','rent','food','utilities','transport','insurance','other','reserveMonths','contingencyPct'];
 const read=()=>Object.fromEntries(keys.map(k=>[k,form.elements.namedItem(k)?.value??'0']));
 const say=(message)=>{$('#relocation-feedback').textContent=message;};
 const show=(result)=>{
   const currency=form.elements.namedItem('currency').value.toUpperCase();
   if(!/^[A-Z]{3}$/.test(currency))throw new Error('Enter a three-letter currency code.');
   const money=(n)=>new Intl.NumberFormat('en-IN',{style:'currency',currency,maximumFractionDigits:2}).format(n);
   for(const [key,id] of Object.entries({oneTimeTotal:'relocation-once',monthlyCost:'relocation-monthly',reserve:'relocation-reserve',contingency:'relocation-contingency',targetCash:'relocation-total'}))$('#'+id).textContent=money(result[key]);
   $('#relocation-result').hidden=false;
   say('Only your own estimates are included. Visa/permit figures are not verified official fees.');
 };
 form.addEventListener('submit',e=>{e.preventDefault();try{show(relocationBudget(read()));}catch(err){$('#relocation-result').hidden=true;say(err.message);}});
 $('#relocation-download').addEventListener('click',()=>{
   try{
     const currency=form.elements.namedItem('currency').value.toUpperCase(),text=exportBudget(read(),currency);
     const url=URL.createObjectURL(new Blob([text],{type:'application/json;charset=utf-8'}));
     const a=document.createElement('a');a.href=url;a.download='overseasjob-relocation-budget.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
     say('Your private estimate was downloaded. No data was sent to OverseasJob.in.');
   }catch(err){say(err.message);}
 });
 $('#relocation-print').addEventListener('click',()=>{try{show(relocationBudget(read()));window.print();}catch(err){say(err.message);}});
 $('#relocation-reset').addEventListener('click',()=>{form.reset();$('#relocation-result').hidden=true;say('All figures cleared. Nothing is saved on our server.');});
}
