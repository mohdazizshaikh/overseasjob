// The expected outcomes are streamed from the exact founder v1.4.1 ZIP by Python.
// These are executable code-vs-spec checks, not separate copied golden outcomes.
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {salarySavings,offerCompare,wallParts} from '../public/assets/calculators.mjs';
import {relocationBudget} from '../public/assets/relocation.mjs';

const g=JSON.parse(readFileSync(0,'utf8'));
assert.deepEqual(Object.keys(g).sort(),['T01','T02','T04','T05']);
const t1=salarySavings({salary:120000,basis:'gross',period:'monthly',rate:10,
  rent:30000,food:15000,transport:5000});
assert.equal(t1.monthlyTakeHome,g.T01.take_home,'T01 take-home vs canonical');
assert.equal(t1.monthlyExpenses,g.T01.expenses,'T01 expenses vs canonical');
assert.equal(t1.monthlySavings,g.T01.savings,'T01 savings vs canonical');
assert.ok(Math.abs(t1.savingsRate-g.T01.pct)<0.0001,'T01 savings rate vs canonical');

const t2=offerCompare(
  {base:100000,period:'monthly',currency:'INR',costs:60000,move:100000},
  {base:80000,period:'monthly',currency:'INR',costs:30000});
assert.equal(t2.a.firstYearSavings,g.T02.a_first_year,'T02 offer A vs canonical');
assert.equal(t2.b.firstYearSavings,g.T02.b_first_year,'T02 offer B vs canonical');

const t4=relocationBudget({flight:10000,visa:10000,documents:10000,
  deposit:10000,setup:10000,arrival:10000,rent:20000,food:10000,
  utilities:5000,transport:3000,insurance:2000,other:0,
  reserveMonths:3,contingencyPct:10});
assert.equal(t4.targetCash,g.T04.target,'T04 relocation target vs canonical');

const winter=new Date('2026-01-15T09:00:00Z');
const summer=new Date('2026-07-15T09:00:00Z');
assert.equal(wallParts(winter,'Europe/London').time,g.T05.london_winter_utc9,'T05 London winter');
assert.equal(wallParts(summer,'Europe/London').time,g.T05.london_summer_utc9,'T05 London summer DST');
assert.equal(wallParts(winter,'Asia/Kolkata').time,g.T05.kolkata_utc9,'T05 India winter');
assert.equal(wallParts(summer,'Asia/Kolkata').time,g.T05.kolkata_utc9,'T05 India summer');
console.log('PASS canonical ZIP 10 numerical/time checks across T01,T02,T04,T05');
