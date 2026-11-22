/* M09/M15: opt-in only, no cross-site tracker and no identifiers. */
export const CONSENT_KEY='overseasjob_privacy_v1';
export const EVENTS=Object.freeze(['page_home','page_jobs','tool_open','guide_open','job_card_open','outbound_apply']);
export function canCount(consent,{gpc=false,dnt='0'}={}){
 return consent==='yes' && gpc!==true && dnt!=='1' && dnt!=='yes';
}
export function privacyEventForPath(path){
 if(path==='/')return 'page_home';
 if(path==='/jobs')return 'page_jobs';
 if(/^\/tools(?:\/|$)/.test(path))return 'tool_open';
 if(/^\/guides(?:\/|$)/.test(path))return 'guide_open';
 if(/^\/jobs\/(?:jobicy|himalayas)\//.test(path))return 'job_card_open';
 return null;
}
export function clearOverseasJobData(storage){
 if(!storage||typeof storage.key!=='function'||typeof storage.removeItem!=='function')throw new Error('Browser storage unavailable');
 const keys=[];
 for(let i=0;i<storage.length;i++){
  const key=storage.key(i);if(typeof key==='string'&&key.startsWith('overseasjob_'))keys.push(key);
 }
 for(const key of keys)storage.removeItem(key);
 return keys.length;
}
export function choiceFromStorage(storage){
 try{const v=storage.getItem(CONSENT_KEY);return v==='yes'?'yes':'no';}catch{return 'no';}
}
