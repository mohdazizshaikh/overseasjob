<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Jobs.php';
$pass=0;
function test(string $name, bool $result):void{global $pass;if(!$result){fwrite(STDERR,"FAIL $name\n");exit(1);}echo "PASS $name\n";$pass++;}
$j=json_decode((string)file_get_contents(__DIR__.'/fixtures/jobicy.json'),true)['jobs'];
$h=json_decode((string)file_get_contents(__DIR__.'/fixtures/himalayas.json'),true)['jobs'];
$a=Jobs::fromJobicy($j[0]);
$b=Jobs::fromJobicy($j[1]);
test('Jobicy fixture maps stable source ID', $a['id']==='provider:jobicy:101');
test('Jobicy source and apply URL preserved', $a['source_url']===$j[0]['url']&&$a['apply_url']===$j[0]['url']);
test('Jobicy Anywhere not claimed as India-eligible', $a['eligible_from_in']==='unknown');
test('US-only not India eligible', $b['eligible_from_in']==='not_allowed');
test('Unknown visa sponsorship', $a['visa_sponsorship']==='unknown');
test('No imported JobPosting schema', !$a['rights']['allow_jobposting_schema']);
test('No external resyndication', !$a['rights']['allow_resyndication']);
test('Salary shown with original units and no invented net pay', Jobs::salaryLabel($a)==='USD 30,000–50,000 / year (gross/net not disclosed)');
test('Salary omitted when currency is missing', Jobs::salaryLabel(array_merge($a,['salary_currency'=>null]))===null);
test('Jobicy pubDate mapped source published', $a['source_published_at']==='2026-11-21T12:00:00Z');
$c=Jobs::fromHimalayas($h[0]);
test('Worldwide + all timezones confirmed IN', $c['eligible_from_in']==='allowed');
test('Himalayas raw geographic restrictions retained as evidence', $c['eligibility_evidence'][0]['fact']==='[]');
test('Himalayas raw time restrictions retained as evidence', $c['eligibility_evidence'][1]['fact']==='[]');
test('Himalayas non-provider apply host rejected', Jobs::fromHimalayas(array_merge($h[0],['applicationLink'=>'https://other.example.com/apply']))===null);
test('Himalayas applyLink preserved', $c['apply_url']===$h[0]['applicationLink']);
test('Himalayas pubDate is a source update, not original publication', $c['source_published_at']===null&&$c['source_updated_at']===Jobs::utc($h[0]['pubDate']));
test('Himalayas US-only not eligible', Jobs::fromHimalayas($h[1])['eligible_from_in']==='not_allowed');
test('Himalayas timezone mismatch not eligible', Jobs::fromHimalayas($h[2])['eligible_from_in']==='not_allowed');
test('Himalayas missing restrictions unknown', Jobs::fromHimalayas($h[3])['eligible_from_in']==='unknown');
test('Invalid timezone offset stays unknown', Jobs::himalayasIndia(['locationRestrictions'=>[],'timezoneRestrictions'=>['INVALID']])['eligibility']==='unknown');
test('Current upstream string[] location restrictions support India', Jobs::himalayasIndia(['locationRestrictions'=>['IN'],'timezoneRestrictions'=>[5.5]])['eligibility']==='allowed');
test('Current upstream numeric timezone excludes India', Jobs::himalayasIndia(['locationRestrictions'=>[],'timezoneRestrictions'=>[-5]])['eligibility']==='not_allowed');

test('Himalayas country full name India recognized', Jobs::himalayasIndia(['locationRestrictions'=>['India'],'timezoneRestrictions'=>[5.5]])['eligibility']==='allowed');
test('Himalayas full name US excluded', Jobs::himalayasIndia(['locationRestrictions'=>['United States'],'timezoneRestrictions'=>[]])['eligibility']==='not_allowed');
test('Himalayas mixed country names and codes', Jobs::himalayasIndia(['locationRestrictions'=>['United States','IN'],'timezoneRestrictions'=>['UTC+05:30']])['eligibility']==='allowed');
test('Himalayas name-only object accepted', Jobs::himalayasIndia(['locationRestrictions'=>[['name'=>'India']],'timezoneRestrictions'=>[]])['eligibility']==='allowed');
test('Himalayas conflicting code/name unknown', Jobs::himalayasIndia(['locationRestrictions'=>[['alpha2'=>'US','name'=>'India']],'timezoneRestrictions'=>[]])['eligibility']==='unknown');
test('Himalayas fabricated country unknown not restricted', Jobs::himalayasIndia(['locationRestrictions'=>['Narnia'],'timezoneRestrictions'=>[]])['eligibility']==='unknown');
test('ISO country unknown code rejected', Countries::alpha2('ZZ')===null);
test('ISO country alias resolves India', Countries::alpha2('india')==='IN');

test('Unknown remote India eligibility not fully confirmed', !Jobs::indiaFullyEligible($a));
test('India UTC+05:30 allowed', Jobs::himalayasIndia(['locationRestrictions'=>[],'timezoneRestrictions'=>['UTC+05:30']])['eligibility']==='allowed');
test('First Himalayas cursor request has no deprecated offset', Jobs::himalayasBrowseUrl()==='https://himalayas.app/jobs/api?limit=20');
test('Opaque Himalayas token is URL-encoded, not decoded or modified', Jobs::himalayasBrowseUrl('a+b/=')==='https://himalayas.app/jobs/api?limit=20&cursor=a%2Bb%2F%3D');
test('Himalayas next cursor accepted unchanged', Jobs::himalayasNextCursor(['jobs'=>[['guid'=>'x']],'nextCursor'=>'a+b/='])==='a+b/=');
test('Missing Himalayas cursor signals completed traversal', Jobs::himalayasNextCursor(['jobs'=>[['guid'=>'x']]])===null);
test('Null Himalayas cursor signals completed traversal', Jobs::himalayasNextCursor(['jobs'=>[['guid'=>'x']],'nextCursor'=>null])===null);
$cursorRejected=false;try{Jobs::himalayasNextCursor(['jobs'=>[['guid'=>'x']],'nextCursor'=>'']);}catch(InvalidArgumentException){$cursorRejected=true;}
test('Empty Himalayas cursor fails closed',$cursorRejected);
$cursorRejected=false;try{Jobs::himalayasNextCursor(['jobs'=>[],'nextCursor'=>'next']);}catch(InvalidArgumentException){$cursorRejected=true;}
test('Next cursor with zero jobs fails closed',$cursorRejected);
$cursorRejected=false;try{Jobs::himalayasBrowseUrl("a\\nb");}catch(InvalidArgumentException){$cursorRejected=true;}
test('Control bytes in cursor rejected',$cursorRejected);
$cursorRejected=false;try{Jobs::himalayasNextCursor(['jobs'=>array_fill(0,21,[]),'nextCursor'=>'next']);}catch(InvalidArgumentException){$cursorRejected=true;}
test('Himalayas oversized page rejected',$cursorRejected);

test('Himalayas documented offset next page 20', Jobs::himalayasNextOffset(['offset'=>0,'totalCount'=>41,'jobs'=>array_fill(0,20,[])],0)===20);
test('Himalayas documented final page stops', Jobs::himalayasNextOffset(['offset'=>20,'totalCount'=>40,'jobs'=>array_fill(0,20,[])],20)===null);
test('Himalayas short final page stops', Jobs::himalayasNextOffset(['offset'=>40,'totalCount'=>43,'jobs'=>array_fill(0,3,[])],40)===null);
test('Himalayas same jobs at changed offset share a repeated-page signature', Jobs::himalayasPageSignature(['offset'=>0,'jobs'=>[['guid'=>'a'],['guid'=>'b']]])===Jobs::himalayasPageSignature(['offset'=>20,'jobs'=>[['guid'=>'a'],['guid'=>'b']]]));
test('Himalayas different jobs yield different page signatures', Jobs::himalayasPageSignature(['jobs'=>[['guid'=>'a']]])!==Jobs::himalayasPageSignature(['jobs'=>[['guid'=>'b']]]));

$offsetMismatch=false;try{Jobs::himalayasNextOffset(['offset'=>0,'totalCount'=>100,'jobs'=>array_fill(0,20,[])],20);}catch(InvalidArgumentException){$offsetMismatch=true;}
test('Himalayas offset mismatch fails closed', $offsetMismatch);
$shortPage=false;try{Jobs::himalayasNextOffset(['offset'=>20,'totalCount'=>100,'jobs'=>array_fill(0,3,[])],20);}catch(InvalidArgumentException){$shortPage=true;}
test('Himalayas suspicious premature short page fails closed', $shortPage);

test('Unsafe JS URL rejected', Jobs::safeUrl('javascript:alert(1)')===null);
test('HTTP URL rejected', Jobs::safeUrl('http://example.com/jobs')===null);
test('IP target URL rejected', Jobs::safeUrl('https://127.0.0.1/job')===null);
test('Wrong Jobicy source host rejected', Jobs::fromJobicy(['id'=>2,'url'=>'https://evil.example/jobs/test','jobTitle'=>'A'])===null);
test('Fresh active visible', Jobs::visible($a));
test('Closed hidden', !Jobs::visible(array_merge($a,['availability'=>'closed'])));
test('Stale after 72h hidden', !Jobs::visible(array_merge($a,['last_verified_at'=>'2025-01-01T00:00:00Z'])));
test('Expired job hidden', !Jobs::visible(array_merge($a,['valid_through'=>'2020-01-01T00:00:00Z'])));
test('IN confirmed filter excludes unknown and US-only', Jobs::search([$a,$b],'','remote_from_india')===[]);
test('Onsite abroad not faked by remote jobs', Jobs::search([$a,$b],'','onsite_abroad')===[]);
// Atomic CLI ingestion tested in an isolated copy: never public-facing fake jobs.
$testRoot=sys_get_temp_dir().'/oj-sync-'.bin2hex(random_bytes(4));
mkdir($testRoot);foreach(['src','scripts','config','storage'] as $d)mkdir($testRoot.'/'.$d);
foreach(['src/Jobs.php','src/Countries.php','src/country_aliases.json','scripts/sync_jobs.php','config/provider_policy.json'] as $f)copy(dirname(__DIR__).'/'.$f,$testRoot.'/'.$f);
// Runtime cache is intentionally gitignored. Seed the fixture sandbox itself, never a tracked/public job cache.
file_put_contents($testRoot.'/storage/jobs.json',json_encode(['jobs'=>[]], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($testRoot.'/scripts/sync_jobs.php').' jobicy --fixture='.escapeshellarg(__DIR__.'/fixtures/jobicy.json').' 2>&1';
exec($cmd,$out,$code);
test('Fixture jobicy import exits successfully', $code===0);
$after=json_decode((string)file_get_contents($testRoot.'/storage/fixture_jobs.json'),true);
test('Imported two fixture jobs only in isolated temp', count($after['jobs']??[])===2);
$first=$after['jobs'][0]['first_seen_at'];$out=[];exec($cmd,$out,$code);$again=json_decode((string)file_get_contents($testRoot.'/storage/fixture_jobs.json'),true);
test('Idempotent re-import: two jobs', $code===0&&count($again['jobs'])===2);
test('First seen preserved', $again['jobs'][0]['first_seen_at']===$first);
$fail=escapeshellarg(PHP_BINARY).' '.escapeshellarg($testRoot.'/scripts/sync_jobs.php').' jobicy --fixture='.escapeshellarg(__DIR__.'/fixtures/empty.json').' 2>&1';
$out=[];exec($fail,$out,$code);$preserved=json_decode((string)file_get_contents($testRoot.'/storage/fixture_jobs.json'),true);
test('Zero-job sync fails safely', $code!==0);
test('Last healthy fixture cache preserved on bad sync', count($preserved['jobs'])===2);
test('Fixture import never populates public job cache', count(json_decode((string)file_get_contents($testRoot.'/storage/jobs.json'),true)['jobs'])===0);
$cmdH=escapeshellarg(PHP_BINARY).' '.escapeshellarg($testRoot.'/scripts/sync_jobs.php').' himalayas --fixture='.escapeshellarg(__DIR__.'/fixtures/himalayas.json').' 2>&1';
$out=[];exec($cmdH,$out,$code);
$hFixture=json_decode((string)file_get_contents($testRoot.'/storage/fixture_jobs.json'),true);
$hRow=($hFixture['jobs']??[])[0]??[];
test('Himalayas isolated fixture sync succeeds without external API', $code===0 && count($hFixture['jobs']??[])===4);
test('Himalayas fixture cache keeps original publication unknown', ($hRow['source_published_at']??null)===null && isset($hRow['source_updated_at']));
function rmrf(string $path):void{foreach(glob($path.'/*')?:[] as $p){if(is_dir($p))rmrf($p);else unlink($p);}rmdir($path);}rmrf($testRoot);
echo "\n$pass PHP checks passed.\n";
