// Pure functions: client-side only; no external calls, no inferred exchange rate or taxes.
export function finiteNonnegative(value, field='Amount') {
  const n=Number(value);
  if (value === '' || value===null || value===undefined || !Number.isFinite(n) || n<0 || n>1e11) throw new Error(`${field} must be a valid non-negative number.`);
  return n;
}
export function salarySavings(v) {
  const salary=finiteNonnegative(v.salary,'Salary');
  const period=v.period||'monthly'; if(!['monthly','annual'].includes(period))throw new Error('Invalid salary period');
  const basis=v.basis||'net'; if(!['gross','net'].includes(basis))throw new Error('Invalid salary basis');
  const monthly=period==='annual'?salary/12:salary;
  const deduction=finiteNonnegative(v.deduction||0,'Fixed deductions');
  const rate=finiteNonnegative(v.rate||0,'Deduction rate');
  if(rate>100)throw new Error('Deduction rate cannot exceed 100%');
  if(basis==='net'&&(deduction!==0||rate!==0))throw new Error('Net salary cannot have added deductions');
  const takeHome=basis==='gross'?Math.max(0,monthly-deduction-monthly*rate/100):monthly;
  const expenseKeys=['rent','food','transport','utilities','insurance','other'];
  const costs=expenseKeys.reduce((s,k)=>s+finiteNonnegative(v[k]||0,k),0);
  const savings=takeHome-costs;
  const oneTime=finiteNonnegative(v.oneTime||0,'One-time costs');
  return { monthlyTakeHome: takeHome,monthlyExpenses: costs,monthlySavings:savings,
    savingsRate:takeHome>0?100*savings/takeHome:null,
    annualSavings:savings*12,firstYearAfterMove:savings*12-oneTime };
}
export function offerCompare(a,b) {
  const normalize=(o)=>{
    const currency=(o.currency||'').trim().toUpperCase();
    if(!/^[A-Z]{3}$/.test(currency))throw new Error('Use a three-letter currency code');
    const base=finiteNonnegative(o.base,'Base salary');
    const period=o.period||'monthly'; let monthly;
    if(period==='monthly')monthly=base;
    else if(period==='annual')monthly=base/12;
    else if(period==='hourly')monthly=base*finiteNonnegative(o.hoursWeek,'Hours per week')*finiteNonnegative(o.weeksYear||52,'Weeks per year')/12;
    else throw new Error('Invalid offer period');
    monthly+=finiteNonnegative(o.guaranteedCash||0,'Guaranteed monthly cash');
    const deductions=finiteNonnegative(o.deductions||0,'Monthly deductions');
    const costs=finiteNonnegative(o.costs||0,'Monthly costs');
    const move=finiteNonnegative(o.move||0,'One-time move costs');
    const bonus=finiteNonnegative(o.conditionalBonus||0,'Conditional bonus'); // NEVER in guaranteed total
    const monthlyNet=monthly-deductions;
    return { currency,monthlyNet,monthlySavings:monthlyNet-costs,
      firstYearSavings:(monthlyNet-costs)*12-move,conditionalBonus:bonus };
  };
  const x=normalize(a), y=normalize(b);
  if(x.currency!==y.currency){
    const fx=Number(b.fxToA);
    if(!Number.isFinite(fx)||fx<=0||fx>1000000)return {a:x,b:y,comparable:false,reason:'Enter a valid user-supplied conversion rate for Offer B to Offer A.'};
    y.firstYearSavingsInA=y.firstYearSavings*fx;
    return {a:x,b:y,comparable:true,difference:x.firstYearSavings-y.firstYearSavingsInA,displayCurrency:x.currency,rateUserProvided:true};
  }
  return {a:x,b:y,comparable:true,difference:x.firstYearSavings-y.firstYearSavings,displayCurrency:x.currency,rateUserProvided:false};
}
export function wallParts(date,zone) {
  const f=new Intl.DateTimeFormat('en-US',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
  const parts=Object.fromEntries(f.formatToParts(date).filter(p=>p.type!=='literal').map(p=>[p.type,p.value]));
  return {date:`${parts.year}-${parts.month}-${parts.day}`,min:Number(parts.hour)*60+Number(parts.minute),time:`${parts.hour}:${parts.minute}`};
}
function inHours(min,start,end){return start<end?min>=start&&min<end:min>=start||min<end;}
function parseClock(s){if(!/^([01]\d|2[0-3]):[0-5]\d$/.test(s))throw new Error('Use HH:MM working hours');const [h,m]=s.split(':').map(Number);return h*60+m;}
export function overlapSlots({date,zoneA,zoneB,startA,endA,startB,endB,minutes=30}) {
  if(!/^\d{4}-\d{2}-\d{2}$/.test(date))throw new Error('Select a calendar date');
  const aS=parseClock(startA),aE=parseClock(endA),bS=parseClock(startB),bE=parseClock(endB);
  if(aS===aE||bS===bE)throw new Error('Start and end time must differ');
  const length=Number(minutes);if(!Number.isInteger(length)||length<15||length>240||length%15!==0)throw new Error('Meeting must be 15–240 minutes');
  // Throws RangeError if zone is not supported: no fake timezone output.
  wallParts(new Date(`${date}T12:00:00Z`),zoneA);wallParts(new Date(`${date}T12:00:00Z`),zoneB);
  const nextDate=new Date(`${date}T00:00:00Z`);nextDate.setUTCDate(nextDate.getUTCDate()+1);
  const plusOne=nextDate.toISOString().slice(0,10);
  const startEpoch=Date.parse(`${date}T00:00:00Z`)-14*3600000;
  const endEpoch=Date.parse(`${date}T00:00:00Z`)+38*3600000;
  const candidates=[];
  for(let t=startEpoch;t<endEpoch;t+=15*60000){
    const wA=wallParts(new Date(t),zoneA), wB=wallParts(new Date(t),zoneB);
    const validADate=wA.date===date||(aS>aE&&wA.date===plusOne&&wA.min<aE);
    if(!validADate||!inHours(wA.min,aS,aE)||!inHours(wB.min,bS,bE))continue;
    candidates.push(t);
  }
  // Merge contiguous valid instants into continuous overlap windows.
  const groups=[];let first=null,last=null;
  for(const t of candidates){
    if(first===null){first=t;last=t;continue;}
    if(t-last>15*60000){groups.push([first,last+15*60000]);first=t;}
    last=t;
  }
  if(first!==null)groups.push([first,last+15*60000]);
  const slots=[];
  for(const [s,e] of groups){
    for(let t=s;t+length*60000<=e && slots.length<5;t+=30*60000){
      const wa=wallParts(new Date(t),zoneA),wb=wallParts(new Date(t),zoneB);
      slots.push({utc:new Date(t).toISOString(),a:`${wa.date} ${wa.time}`,b:`${wb.date} ${wb.time}`});
    }
  }
  return {slots,overlapMinutes:groups.reduce((s,[a,b])=>s+(b-a)/60000,0)};
}
