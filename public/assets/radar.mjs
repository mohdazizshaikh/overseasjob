/* M07: privacy-first, version-aware Opportunity Radar without background requests. */
export const PROFILE_KEY='overseasjob_mycareer_v1';
export const PATHS=['remote_from_india','remote_other','onsite_abroad'];
const ID=/^(?:provider|direct):[A-Za-z0-9:._-]{2,160}$/;
const VERSION=/^[a-f0-9]{16,64}$/;
const text=(v,n)=>typeof v==='string'?v.slice(0,n):'';
const unique=(v,limit)=>[...new Set(v)].slice(0,limit);
export function normaliseProfile(input={}){
 if(!input || typeof input!=='object' || Array.isArray(input))throw new Error('Invalid career profile');
 if(input.workPath!==undefined && !PATHS.includes(input.workPath))throw new Error('Invalid work preference');
 if(input.role!==undefined && typeof input.role!=='string')throw new Error('Invalid role');
 const saved=input.saved??[],seen=input.seen??[];
 if(!Array.isArray(saved)||saved.length>2000||!Array.isArray(seen)||seen.length>2000)throw new Error('Invalid saved job IDs');
 if(saved.some(x=>typeof x!=='string'||!ID.test(x))||seen.some(x=>typeof x!=='string'||!ID.test(x)))throw new Error('Invalid job identifier');
 const versions=input.seenVersions??{};
 if(!versions||typeof versions!=='object'||Array.isArray(versions)||Object.keys(versions).length>2000)throw new Error('Invalid comparison history');
 const seenVersions=Object.create(null);
 for(const [id,version] of Object.entries(versions)){
   if(!ID.test(id)||typeof version!=='string'||!VERSION.test(version))throw new Error('Invalid saved listing version');
   seenVersions[id]=version;
 }
 return {role:text(input.role,100),workPath:input.workPath??'remote_from_india',saved:unique(saved,2000),seen:unique(seen,2000),seenVersions};
}
export function importCareer(raw){
 if(!raw||typeof raw!=='object'||Array.isArray(raw)||![1,2].includes(raw.schema_version))throw new Error('Unsupported career backup version');
 return normaliseProfile(raw.profile);
}
export function exportCareer(profile){return JSON.stringify({schema_version:2,profile:normaliseProfile(profile)},null,2);}
export function normaliseJobView(job){
 if(!job||typeof job!=='object'||Array.isArray(job)||!ID.test(job.id)||typeof job.title!=='string'||job.title.length>180)throw new Error('Invalid radar listing');
 if(!VERSION.test(job.version||'')||!['allowed','not_allowed','unknown'].includes(job.eligibility)||!['remote','hybrid','onsite'].includes(job.workMode))throw new Error('Invalid radar evidence');
 if(!/^\/jobs\/(jobicy|himalayas)\/[A-Za-z0-9._%-]+\/view$/.test(job.url||''))throw new Error('Invalid local job path');
 return {id:job.id,title:job.title,eligibility:job.eligibility,workMode:job.workMode,url:job.url,version:job.version};
}
export function findRadarMatches(jobs,rawProfile){
 const profile=normaliseProfile(rawProfile);
 if(!Array.isArray(jobs)||jobs.length>2000)throw new Error('Radar results unavailable');
 const needle=profile.role.toLocaleLowerCase('en').trim();
 return jobs.map(normaliseJobView).filter(j=>{
   if(profile.workPath==='remote_from_india' && (j.eligibility!=='allowed'||j.workMode!=='remote'))return false;
   if(profile.workPath==='remote_other'&&(j.workMode!=='remote'||j.eligibility==='allowed'))return false;
   if(profile.workPath==='onsite_abroad'&&j.workMode!=='onsite')return false;
   return !needle||j.title.toLocaleLowerCase('en').includes(needle);
 }).map(j=>({...j,change:!profile.seen.includes(j.id)?'new_to_you':!profile.seenVersions[j.id]?'previously_seen':profile.seenVersions[j.id]!==j.version?'details_changed':'unchanged'}));
}
export function markRadarViewed(rawProfile,view){
 const p=normaliseProfile(rawProfile);
 if(!Array.isArray(view)||view.length>2000)throw new Error('Too many observed listings');
 const versions={...p.seenVersions};
 for(const item of view){const j=normaliseJobView(item);versions[j.id]=j.version;}
 const ids=unique([...p.seen,...view.map(j=>j.id)].slice(-2000),2000);
 const keep=Object.fromEntries(ids.filter(id=>typeof versions[id]==='string').map(id=>[id,versions[id]]));
 return normaliseProfile({...p,seen:ids,seenVersions:keep});
}
