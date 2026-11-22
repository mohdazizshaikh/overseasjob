// T04 relocation budgeting — deterministic arithmetic, all inputs supplied by user.
function amount(value, name) {
  if (value === '' || value === null || value === undefined) value=0;
  const n=Number(value);
  if(!Number.isFinite(n)||n<0||n>1e11)throw new Error(`${name} must be a finite non-negative amount.`);
  return n;
}
export const ONCE=['flight','visa','documents','deposit','setup','arrival'];
export const MONTHLY=['rent','food','utilities','transport','insurance','other'];
export function relocationBudget(data) {
  if(!data||typeof data!=='object')throw new Error('Missing budget inputs');
  const once=Object.fromEntries(ONCE.map(k=>[k,amount(data[k],k)]));
  const monthly=Object.fromEntries(MONTHLY.map(k=>[k,amount(data[k],k)]));
  const reserveMonths=amount(data.reserveMonths,'Reserve months');
  const rate=amount(data.contingencyPct,'Contingency rate');
  if(reserveMonths>36)throw new Error('Reserve months cannot exceed 36.');
  if(rate>100)throw new Error('Contingency rate cannot exceed 100%.');
  const oneTimeTotal=Object.values(once).reduce((a,b)=>a+b,0);
  const monthlyCost=Object.values(monthly).reduce((a,b)=>a+b,0);
  const reserve=reserveMonths*monthlyCost;
  const contingency=rate/100*(oneTimeTotal+reserve);
  const targetCash=oneTimeTotal+reserve+contingency;
  for(const n of [oneTimeTotal,monthlyCost,reserve,contingency,targetCash])if(!Number.isFinite(n)||n>1e13)throw new Error('Budget amount is too large.');
  return {oneTimeTotal,monthlyCost,reserve,contingency,targetCash,reserveMonths,rate,once,monthly};
}
export function exportBudget(data,currency='INR') {
  if(!/^[A-Z]{3}$/.test(currency))throw new Error('Currency must be a three-letter code.');
  const values=relocationBudget(data);
  return JSON.stringify({schema_version:1,source:'OverseasJob.in T04',currency,assumptions_user_entered:true,
    note:'Planning scenario only. Not an official visa, tax, airline or cost-of-living estimate.',
    inputs:data,result:values},null,2);
}
