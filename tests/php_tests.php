<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Jobs.php';
require dirname(__DIR__).'/src/ProviderHttp.php';
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
test('Documented Himalayas external ATS apply host is accepted unchanged', Jobs::fromHimalayas(array_merge($h[0],['applicationLink'=>'https://careers.example.com/apply']))['apply_url']==='https://careers.example.com/apply');
test('Unsafe Himalayas application URL is rejected', Jobs::fromHimalayas(array_merge($h[0],['applicationLink'=>'http://127.0.0.1/apply']))===null);
test('Himalayas applyLink preserved', $c['apply_url']===$h[0]['applicationLink']);
test('Canonical provider JobView includes employer country unknown and empty reason tags', array_key_exists('employer_country',$a)&&$a['employer_country']===null&&$a['reason_tags']===[]&&$a['source_policy_id']==='provider_jobicy_source_link_only');
test('Himalayas provenance retains the immutable provider guid', $c['source_guid']===$h[0]['guid']&&$c['source_policy_id']==='provider_himalayas_source_link_only');
test('Himalayas provider id is original GUID', $c['provider_job_id']===$h[0]['guid']);
test('Himalayas display route keeps stable hash', $c['provider_route_key']===hash('sha256',$h[0]['guid']));
test('Offline-only legacy status fixture URL construction', Jobs::himalayasStatusUrl('https://himalayas.app/companies/linear/jobs/senior-engineer')==='https://himalayas.app/jobs/api/companies/linear/jobs/senior-engineer');
test('Simulated 410 fixture maps closed, 404 unconfirmed', Jobs::himalayasStatusDecision(410)==='closed'&&Jobs::himalayasStatusDecision(200)==='active'&&Jobs::himalayasStatusDecision(404)==='unknown'&&Jobs::himalayasStatusDecision(429)==='unknown');
$badStatusUrl=false;try{Jobs::himalayasStatusUrl('https://himalayas.app.evil.example/companies/a/jobs/b');}catch(InvalidArgumentException){$badStatusUrl=true;}
test('Himalayas job checker rejects foreign hosts',$badStatusUrl);
$badStatusUrl=false;try{Jobs::himalayasStatusUrl('https://himalayas.app/companies/a/jobs/b?unexpected=1');}catch(InvalidArgumentException){$badStatusUrl=true;}
test('Himalayas status checker rejects extra query',$badStatusUrl);
test('Retry-After numeric 429 is respected', ProviderHttp::retryDelay(429,['Retry-After: 6'],0,0,0)===6);
test('Retry-After longer than retry budget safely stops', ProviderHttp::retryDelay(429,['Retry-After: 120'],0)===null);
test('Temporary 503 uses bounded exponential backoff', ProviderHttp::retryDelay(503,[],1)===2);
test('Permanent 403 is not retried', ProviderHttp::retryDelay(403,[],0)===null);
test('Retry-After bad value fails closed',ProviderHttp::retryDelay(429,['Retry-After: invalid'],0)===null);
$attempts=0;$delays=[];
$mock=static function(string $url,int $maxBytes)use(&$attempts):array{
 $attempts++;return ['status'=>$attempts===1?429:200,'headers'=>$attempts===1?['Retry-After: 2']:[],'body'=>'{"jobs":[]}'];
};
$sleep=static function(int $secs)use(&$delays):void{$delays[]=$secs;};
$mockResult=ProviderHttp::fetchJson('https://himalayas.app/jobs/api',2000,$mock,$sleep);
test('429 retry returns validated JSON without real sleeping',count($mockResult['jobs'])===0&&$attempts===2&&$delays===[2]);
$disallowed=false;try{ProviderHttp::fetchJson('https://evil.example/jobs/api',2000,$mock,$sleep);}catch(InvalidArgumentException){$disallowed=true;}
test('Provider client refuses arbitrary upstream hosts',$disallowed);
$disallowed=false;try{ProviderHttp::fetchJson('https://user@himalayas.app/jobs/api',2000,$mock,$sleep);}catch(InvalidArgumentException){$disallowed=true;}
test('Provider client refuses URL username',$disallowed);
$failureAttempts=0;
$failure=static function(string $url,int $limit)use(&$failureAttempts):array{
 $failureAttempts++;return ['status'=>429,'headers'=>['Retry-After: 60'],'body'=>''];
};
$closed=false;try{ProviderHttp::fetchJson('https://himalayas.app/jobs/api',2000,$failure,$sleep);}catch(RuntimeException){$closed=true;}
test('Long 429 Retry-After fails with single request, no premature retry',$closed&&$failureAttempts===1);
test('Official OpenAPI Himalayas pubDate is publication time', $c['source_published_at']===Jobs::utc($h[0]['pubDate'])&&$c['source_updated_at']===null);
test('Himalayas US-only not eligible', Jobs::fromHimalayas($h[1])['eligible_from_in']==='not_allowed');
test('Himalayas timezone mismatch not eligible', Jobs::fromHimalayas($h[2])['eligible_from_in']==='not_allowed');
test('Himalayas missing restrictions unknown', Jobs::fromHimalayas($h[3])['eligible_from_in']==='unknown');
test('Invalid timezone offset stays unknown', Jobs::himalayasIndia(['locationRestrictions'=>[],'timezoneRestrictions'=>['INVALID']])['eligibility']==='unknown');
test('Legacy helper still reads string-country numeric-zone cache', Jobs::himalayasIndia(['locationRestrictions'=>['IN'],'timezoneRestrictions'=>[5.5]])['eligibility']==='allowed');
test('Legacy helper numeric-zone exclusion stays intact', Jobs::himalayasIndia(['locationRestrictions'=>[],'timezoneRestrictions'=>[-5]])['eligibility']==='not_allowed');

// Official OpenAPI input examples (no real provider HTTP requests).
$officialIn=['alpha2'=>'IN','name'=>'India','slug'=>'india'];
$officialUs=['alpha2'=>'US','name'=>'United States','slug'=>'united-states'];
test('Strict Himalayas official Location and UTC string permit India',
    Jobs::himalayasIndia(['locationRestrictions'=>[$officialIn],'timezoneRestrictions'=>['UTC+5:30']],true)['eligibility']==='allowed');
test('Strict Himalayas zero-padded UTC string permit India',
    Jobs::himalayasIndia(['locationRestrictions'=>[],'timezoneRestrictions'=>['UTC+05:30']],true)['eligibility']==='allowed');
test('Strict Himalayas allowed location but US timezone excludes India',
    Jobs::himalayasIndia(['locationRestrictions'=>[$officialIn],'timezoneRestrictions'=>['UTC-5']],true)['eligibility']==='not_allowed');
test('Strict Himalayas US location excludes India',
    Jobs::himalayasIndia(['locationRestrictions'=>[$officialUs],'timezoneRestrictions'=>[]],true)['eligibility']==='not_allowed');
test('Strict Himalayas string country is NOT current upstream format',
    Jobs::himalayasIndia(['locationRestrictions'=>['IN'],'timezoneRestrictions'=>[]],true)['eligibility']==='unknown');
test('Strict Himalayas numeric timezone is NOT current upstream format',
    Jobs::himalayasIndia(['locationRestrictions'=>[],'timezoneRestrictions'=>[5.5]],true)['eligibility']==='unknown');
test('Strict Himalayas country object must include alpha2, name and slug',
    Jobs::himalayasIndia(['locationRestrictions'=>[['name'=>'India']],'timezoneRestrictions'=>[]],true)['eligibility']==='unknown');
test('Strict Himalayas contradictory location object is unknown',
    Jobs::himalayasIndia(['locationRestrictions'=>[['alpha2'=>'US','name'=>'India','slug'=>'india']],'timezoneRestrictions'=>[]],true)['eligibility']==='unknown');
test('Strict Himalayas mixed valid and malformed location objects unknown',
    Jobs::himalayasIndia(['locationRestrictions'=>[$officialIn,'IN'],'timezoneRestrictions'=>[]],true)['eligibility']==='unknown');
test('Strict Himalayas missing timezone unknown',
    Jobs::himalayasIndia(['locationRestrictions'=>[$officialIn]],true)['eligibility']==='unknown');
test('Strict Himalayas missing geographic field unknown',
    Jobs::himalayasIndia(['timezoneRestrictions'=>['UTC+5:30']],true)['eligibility']==='unknown');
test('New provider normalization rejects legacy field types as proven eligible',
    Jobs::fromHimalayas(array_merge($h[0],['locationRestrictions'=>['IN'],'timezoneRestrictions'=>[5.5]]))['eligible_from_in']==='unknown');
test('New provider normalization accepts actual OpenAPI shape',
    Jobs::fromHimalayas(array_merge($h[0],['locationRestrictions'=>[$officialIn],'timezoneRestrictions'=>['UTC+5:30']]))['eligible_from_in']==='allowed');
test('Official publication and expiry integer ms normalize to UTC',
    is_int($h[0]['pubDate']) && $h[0]['pubDate']>1000000000000
    && Jobs::fromHimalayas($h[0])['source_published_at']===Jobs::utc($h[0]['pubDate'])
    && Jobs::fromHimalayas($h[0])['valid_through']===Jobs::utc($h[0]['expiryDate']));

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
test('Official browse first page includes zero offset', Jobs::himalayasBrowseUrl()==='https://himalayas.app/jobs/api?limit=20&offset=0');
test('Official browse page two increments offset', Jobs::himalayasBrowseUrl(20)==='https://himalayas.app/jobs/api?limit=20&offset=20');
$badOffset=false;try{Jobs::himalayasBrowseUrl(-1);}catch(InvalidArgumentException){$badOffset=true;}
test('Negative offset rejected',$badOffset);
test('Himalayas OpenAPI opaque GUID maps to original external ID',
  Jobs::fromHimalayas(array_merge($h[0],['guid'=>'stripe-senior-engineer-abc123']))['provider_job_id']==='stripe-senior-engineer-abc123');
$opaque=Jobs::fromHimalayas(array_merge($h[0],['guid'=>'stripe-senior-engineer-abc123','applicationLink'=>'https://boards.greenhouse.io/company/jobs/1234']));
test('Opaque GUID does not fabricate job-detail source URL', $opaque['source_url']==='https://himalayas.app/jobs'&&$opaque['source_url_is_job_specific']===false);
test('Opaque GUID external employer apply URL unchanged', $opaque['apply_url']==='https://boards.greenhouse.io/company/jobs/1234');
$badOffset=false;try{Jobs::himalayasNextOffset(['offset'=>0,'jobs'=>[['guid'=>'a']]],0);}catch(InvalidArgumentException){$badOffset=true;}
test('Missing totalCount fails closed',$badOffset);
$badOffset=false;try{Jobs::himalayasNextOffset(['offset'=>0,'totalCount'=>50,'jobs'=>[]],0);}catch(InvalidArgumentException){$badOffset=true;}
test('Premature empty Himalayas page fails closed',$badOffset);
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
foreach(['src/Jobs.php','src/Countries.php','src/country_aliases.json','src/ProviderHttp.php','src/SourceCoverage.php','src/RoleCoverage.php','src/SourcePermissionGate.php','scripts/sync_jobs.php','scripts/check_himalayas_status.php','config/provider_policy.json'] as $f)copy(dirname(__DIR__).'/'.$f,$testRoot.'/'.$f);
// A simulated cron invocation with no operator permission must fail before HTTP.
$ungatedCommand='SOURCE_LIVE_SYNC_ENABLED=0 SOURCE_JOBICY_RIGHTS_REVIEWED=0 '
    .escapeshellarg(PHP_BINARY).' '.escapeshellarg($testRoot.'/scripts/sync_jobs.php')
    .' jobicy 2>&1';
$ungatedOutput=[];$ungatedCode=0;exec($ungatedCommand,$ungatedOutput,$ungatedCode);
test('Unapproved Jobicy live cron refused before external request',
    $ungatedCode===3
    &&str_contains(implode("\n",$ungatedOutput),'global_live_sync_opt_in_disabled')
    &&!is_file($testRoot.'/storage/coverage.ndjson'));
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
test('Fixture sync never writes live 14-day role observations', !is_file($testRoot.'/storage/role_coverage.ndjson'));
$cmdH=escapeshellarg(PHP_BINARY).' '.escapeshellarg($testRoot.'/scripts/sync_jobs.php').' himalayas --fixture='.escapeshellarg(__DIR__.'/fixtures/himalayas.json').' 2>&1';
$out=[];exec($cmdH,$out,$code);
$hFixture=json_decode((string)file_get_contents($testRoot.'/storage/fixture_jobs.json'),true);
$hRows=array_values(array_filter($hFixture['jobs']??[], static fn($j)=>($j['provider']??null)==='himalayas'));
$hRow=$hRows[0]??[];
test('Himalayas isolated fixture sync retains four Himalayas jobs plus two Jobicy jobs', $code===0 && count($hRows)===4 && count($hFixture['jobs']??[])===6);
test('Himalayas fixture cache retains published date without fictitious update', isset($hRow['source_published_at']) && ($hRow['source_updated_at']??null)===null);
// Authoritative status probe fixture: 410 closes, 404 remains unconfirmed, 200 re-verifies.
$statuses=[];
foreach($h as $i=>$item){
 $code=[410,404,200,200][$i];
 $statuses[$item['guid']]=['status'=>$code,'body'=>$code===200?json_encode(['job'=>['guid'=>$item['guid']]]):''];
}
$statusFixture=$testRoot.'/status_fixture.json';
file_put_contents($statusFixture,json_encode(['statuses'=>$statuses],JSON_THROW_ON_ERROR));
$statusCommand=escapeshellarg(PHP_BINARY).' '.escapeshellarg($testRoot.'/scripts/check_himalayas_status.php').' --fixture='.escapeshellarg($statusFixture).' 2>&1';
$out=[];exec($statusCommand,$out,$statusCode);
$statusCache=json_decode((string)file_get_contents($testRoot.'/storage/fixture_jobs.json'),true);
$hAfter=array_values(array_filter($statusCache['jobs'],static fn($j)=>($j['provider']??'')==='himalayas'));
$statusByGuid=[];foreach($hAfter as $row)$statusByGuid[$row['source_guid']]=$row;
test('Offline-only status fixture command succeeds without network',$statusCode===0);
test('HTTP 410 closes and hides listing',($statusByGuid[$h[0]['guid']]['availability']??null)==='closed'&&($statusByGuid[$h[0]['guid']]['display_status']??null)==='hidden');
test('HTTP 404 is unconfirmed rather than closed',($statusByGuid[$h[1]['guid']]['availability']??null)==='unknown'&&($statusByGuid[$h[1]['guid']]['display_status']??null)==='hidden');
test('HTTP 200 verifies exact original provider GUID',($statusByGuid[$h[2]['guid']]['availability']??null)==='active');
test('Status fixture does not alter public jobs cache',count(json_decode((string)file_get_contents($testRoot.'/storage/jobs.json'),true)['jobs'])===0);
$out=[];exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($testRoot.'/scripts/check_himalayas_status.php').' 2>&1',$out,$code);
test('Undocumented live status endpoint cannot be called',$code===3);
function rmrf(string $path):void{foreach(glob($path.'/*')?:[] as $p){if(is_dir($p))rmrf($p);else unlink($p);}rmdir($path);}rmrf($testRoot);
echo "\n$pass PHP checks passed.\n";
