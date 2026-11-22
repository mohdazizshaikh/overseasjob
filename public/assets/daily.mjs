/* M08: reproducible offline missions; all user progress stays in the browser. */
export const DAILY_KEY = 'overseasjob_daily_v1';
export const DAILY_ROLES = ['it_support', 'customer_support', 'web_development'];
const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
export function validDay(day) {
  if (typeof day !== 'string' || !DATE_RE.test(day)) return false;
  const ms = Date.parse(`${day}T00:00:00.000Z`);
  return Number.isFinite(ms) && new Date(ms).toISOString().slice(0, 10) === day;
}
export function utcToday(date = new Date()) {
  if (!(date instanceof Date) || !Number.isFinite(date.getTime())) throw new Error('Invalid clock');
  return date.toISOString().slice(0, 10);
}
export function dailyMission(bank, role, day) {
  if (!DAILY_ROLES.includes(role) || !validDay(day)) throw new Error('Invalid mission role or date');
  if (!Array.isArray(bank) || bank.length > 120) throw new Error('Invalid mission bank');
  const matches = bank.filter(m => m && m.role === role && /^mission-[a-z0-9-]{2,50}$/.test(m.id || '')
    && typeof m.prompt === 'string' && m.prompt.length >= 20 && m.prompt.length <= 450
    && Array.isArray(m.steps) && m.steps.length >= 2 && m.steps.length <= 6
    && m.steps.every(s => typeof s === 'string' && s.length >= 8 && s.length <= 190));
  if (!matches.length) throw new Error('No original missions for this track');
  const unique = new Set(matches.map(m => m.id));
  if (unique.size !== matches.length) throw new Error('Duplicate mission identifier');
  const dayNumber = Math.floor(Date.parse(`${day}T00:00:00.000Z`) / 86400000);
  // Weekly cycle per role, stable for the same UTC date regardless of device timezone.
  return matches[((dayNumber % matches.length) + matches.length) % matches.length];
}
export function normalizeDailyState(raw) {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw) || raw.schema_version !== 1) throw new Error('Unsupported daily progress version');
  if (!Array.isArray(raw.completed) || raw.completed.length > 365) throw new Error('Daily history exceeds safe limit');
  const completed = [], seen = new Set();
  for (const r of raw.completed) {
    if (!r || typeof r !== 'object' || Array.isArray(r)
      || !validDay(r.day) || !DAILY_ROLES.includes(r.role)
      || typeof r.mission_id !== 'string' || !/^mission-[a-z0-9-]{2,50}$/.test(r.mission_id)) throw new Error('Invalid completion record');
    const k = `${r.role}|${r.day}`;
    if (seen.has(k)) throw new Error('Duplicate completion date for role');
    seen.add(k);
    completed.push({ day: r.day, role: r.role, mission_id: r.mission_id });
  }
  return {schema_version:1, completed: completed.sort((a,b)=>a.day.localeCompare(b.day)).slice(-180)};
}
export function recordCompletion(state, mission, role, day) {
  const cleaned = normalizeDailyState(state);
  if (!validDay(day) || !DAILY_ROLES.includes(role) || !mission || mission.role !== role || !/^mission-[a-z0-9-]{2,50}$/.test(mission.id || '')) throw new Error('Invalid completion');
  const remaining = cleaned.completed.filter(r => r.role !== role || r.day !== day);
  return normalizeDailyState({schema_version:1,completed:[...remaining,{day,role,mission_id:mission.id}]});
}
export function streakFor(state, role, today) {
  if (!DAILY_ROLES.includes(role) || !validDay(today)) throw new Error('Invalid streak request');
  const data = normalizeDailyState(state).completed.filter(r=>r.role===role && r.day<=today);
  const set = new Set(data.map(r=>r.day));
  let cursor = Date.parse(today+'T00:00:00Z');
  if(!set.has(today))cursor -= 86400000; // yesterday's finished streak remains visible today
  let count = 0;
  while(count<180 && set.has(new Date(cursor).toISOString().slice(0,10))) {count++;cursor -= 86400000;}
  return count;
}
export const EMPTY_DAILY = {schema_version:1,completed:[]};
