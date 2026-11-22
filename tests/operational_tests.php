<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Jobs.php';
require dirname(__DIR__).'/src/Reports.php';
require dirname(__DIR__).'/src/Guides.php';
$passed=0;
function ok(string $s,bool $condition):void{global $passed;if(!$condition){fwrite(STDERR,'FAIL '.$s."\n");exit(1);}echo 'PASS '.$s."\n";$passed++;}
$guides=Guides::all(dirname(__DIR__).'/content/guides.json');
ok('Exactly five substantive guide drafts',count($guides)===5);
foreach($guides as $g){
 ok('Guide '. $g['slug'].' has source and draft status',count($g['sources'])>0 && $g['editorial_status']==='draft_requires_editorial_review');
 $text='';foreach($g['sections'] as $section){$text.=implode(' ',$section['paragraphs']);}
 ok('Guide '.$g['slug'].' contains substantive original narrative',str_word_count($text)>=125);
}
$storage=sys_get_temp_dir().'/oj-moderation-'.bin2hex(random_bytes(5));mkdir($storage,0700);
$job=Jobs::fromJobicy(json_decode((string)file_get_contents(__DIR__.'/fixtures/jobicy.json'),true)['jobs'][0]);
Jobs::listPublic($storage.'/jobs.json');
file_put_contents($storage.'/jobs.json',json_encode(['jobs'=>[$job]]));
ok('Test job initially visible in isolated storage',count(Jobs::listPublic($storage.'/jobs.json'))===1);
$rid=Reports::submit($storage,$job['id'],'scam','Fixture: external link appears suspicious');
ok('A report ID and one pending item created',str_starts_with($rid,'report-')&&count(Reports::read($storage))===1);
Reports::decide($storage,$rid,'quarantine','Manual fixture review — hold link');
ok('Manual decision quarantines job immediately',count(Jobs::listPublic($storage.'/jobs.json'))===0);
ok('Quarantine is stored outside provider cache',isset(Reports::quarantine($storage)[$job['id']]));
// Simulate provider rewriting the public cache without knowing about moderation state.
file_put_contents($storage.'/jobs.json',json_encode(['jobs'=>[$job]]));
ok('Provider data update cannot resurrect quarantine',count(Jobs::listPublic($storage.'/jobs.json'))===0);
$duplicate=false;try{Reports::decide($storage,$rid,'resolve','Repeated decision');}catch(RuntimeException){$duplicate=true;}
ok('Cannot silently re-decide already reviewed report',$duplicate);
Reports::reopen($storage,$job['id'],'False positive resolved');
ok('Explicit reopen restores job after reason recorded',count(Jobs::listPublic($storage.'/jobs.json'))===1);
ok('Audit trace records both state transitions',count(array_filter(explode("\n",trim((string)file_get_contents($storage.'/moderation_audit.ndjson')))))===2);
$corrupt=false;file_put_contents($storage.'/quarantine.json','not-json');
ok('Corrupt quarantine hides all rather than ignoring holds',count(Jobs::listPublic($storage.'/jobs.json'))===0);
$wrongReason=false;try{Reports::submit($storage,$job['id'],'invalid','bad');}catch(InvalidArgumentException){$wrongReason=true;}
ok('Invalid report category rejected',$wrongReason);
// Public report throttling is server-side and never stores the raw source IP.
$rateStore=sys_get_temp_dir().'/oj-report-rate-'.bin2hex(random_bytes(5));mkdir($rateStore,0700);
$fakeKey=str_repeat('s',40);$sourceIP='203.0.113.19';$blocked=false;
try{
 for($i=0;$i<6;$i++)Reports::submit($rateStore,$job['id'],'other','Fixture report '.$i,$sourceIP,$fakeKey);
 try{Reports::submit($rateStore,$job['id'],'other','Seventh attempt',$sourceIP,$fakeKey);}catch(RuntimeException){$blocked=true;}
 ok('Server-side hourly report limit rejects seventh request from same IP',$blocked&&count(Reports::read($rateStore))===6);
 $rateFile=(string)file_get_contents($rateStore.'/report_rate.json');
 ok('Report rate store persists only hashed IP, not raw address',!str_contains($rateFile,$sourceIP)&&!str_contains($rateFile,$fakeKey));
 $invalidKey=false;
 try{Reports::submit($rateStore,$job['id'],'other','Without secret',$sourceIP,'short');}catch(RuntimeException){$invalidKey=true;}
 ok('Public reports fail closed without configured private key',$invalidKey);
}finally{foreach(glob($rateStore.'/*')?:[] as $f)unlink($f);rmdir($rateStore);}
function cleanDir($p){foreach(glob($p.'/*')?:[] as $f){if(is_dir($f))cleanDir($f);else unlink($f);}rmdir($p);}cleanDir($storage);
echo "\n$passed operational and guide checks passed.\n";
