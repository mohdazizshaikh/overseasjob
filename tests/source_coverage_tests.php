<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/SourceCoverage.php';
$count=0;
function coverageCheck(string $label,bool $ok):void{
    global $count;if(!$ok)throw new RuntimeException('FAIL M17 '.$label);
    $count++;echo "PASS M17 $label\n";
}
$today=strtotime('2026-11-23T12:00:00Z');
$job1=['eligible_from_in'=>'allowed'];$job2=['eligible_from_in'=>'unknown'];$job3=['eligible_from_in'=>'not_allowed'];
$base=SourceCoverage::newLiveObservation('jobicy',[$job1,$job2,$job3],1,true,'2026-11-22',$today);
coverageCheck('Successful live record is v2 and not a fixture',
    $base['schema_version']===2&&$base['origin']==='live_provider_sync'
    &&$base['cache_published']===true&&$base['from_fixture']===false);
coverageCheck('Eligibility counts reconcile with exact accepted jobs',
    $base['valid']===3&&$base['india_allowed']===1&&$base['india_unknown']===1
    &&$base['india_not_allowed']===1);
coverageCheck('V2 successful observation passes strict validator',SourceCoverage::validRow($base,$today));
function mutated(array $row,array $changes):array{return array_replace($row,$changes);}
coverageCheck('Fixture record cannot count',!SourceCoverage::validRow(mutated($base,['from_fixture'=>true]),$today));
coverageCheck('Legacy date-provider-only row cannot count',
    !SourceCoverage::validRow(['date'=>'2026-11-23','provider'=>'jobicy'],$today));
coverageCheck('Incorrect eligibility partition cannot count',
    !SourceCoverage::validRow(mutated($base,['india_allowed'=>2]),$today));
coverageCheck('Zero-valid listings cannot count',
    !SourceCoverage::validRow(mutated($base,['valid'=>0]),$today));
coverageCheck('Future forged day cannot count',
    !SourceCoverage::validRow(mutated($base,['date'=>'2026-11-24','at'=>'2026-11-24T12:00:00Z']),$today));
coverageCheck('Date/timestamp mismatch cannot count',
    !SourceCoverage::validRow(mutated($base,['date'=>'2026-11-22']),$today));
coverageCheck('Expired source-policy review cannot count',
    !SourceCoverage::validRow(mutated($base,['source_policy_checked_at'=>'2026-09-01']),$today));
coverageCheck('Unknown source never accepted',
    !SourceCoverage::validRow(mutated($base,['provider'=>'unknown_provider']),$today));
coverageCheck('Published-cache flag required',
    !SourceCoverage::validRow(mutated($base,['cache_published'=>false]),$today));
coverageCheck('Exact UTC timestamp required',
    !SourceCoverage::validRow(mutated($base,['at'=>'2026-11-23T12:00:00+00:00']),$today));
coverageCheck('Run ID must be strongly random-looking',
    !SourceCoverage::validRow(mutated($base,['run_id'=>'123']),$today));
$bad=false;try{SourceCoverage::newLiveObservation('jobicy',[$job1,['eligible_from_in'=>'yes']],0,true,'2026-11-22',$today);}
catch(InvalidArgumentException){$bad=true;}
coverageCheck('Cannot create live event with invented eligibility status',$bad);
$bad=false;try{SourceCoverage::newLiveObservation('jobicy',[$job1],0,true,'2026-08-01',$today);}
catch(InvalidArgumentException){$bad=true;}
coverageCheck('Cannot create live event with obsolete policy date',$bad);
$temp=sys_get_temp_dir().'/oj-m17-'.bin2hex(random_bytes(6));mkdir($temp,0700);
try{
    $path=$temp.'/coverage.ndjson';
    $empty=SourceCoverage::summarize($path,$today);
    coverageCheck('Absent log never fabricates days',
        $empty['distinct_actual_days']===0&&!$empty['14_distinct_calendar_days_measured']);
    $legacy=json_encode(['date'=>'2026-11-23','provider'=>'jobicy']);
    file_put_contents($path,$legacy."\n");
    $untrusted=SourceCoverage::summarize($path,$today);
    coverageCheck('Old partial rows reported but never trusted',
        $untrusted['distinct_actual_days']===0&&$untrusted['unverified_legacy_or_invalid_records']===1);
    $lines=[];
    for($day=0;$day<14;$day++){
        $time=$today-$day*86400;
        $lines[]=json_encode(SourceCoverage::newLiveObservation('jobicy',[$job1,$job2],1,true,
            '2026-11-01',$time),JSON_THROW_ON_ERROR);
    }
    file_put_contents($path,implode("\n",$lines)."\n");
    $verified=SourceCoverage::summarize($path,$today);
    coverageCheck('Only fourteen valid UTC observed days pass shape gate',
        $verified['14_distinct_calendar_days_measured']&&$verified['distinct_actual_days']===14);
    coverageCheck('Daily eligibility breakdown based on one sample per provider and day',
        $verified['observed_india_eligibility_rows']['allowed']===14
        &&$verified['observed_india_eligibility_rows']['unknown']===14);
    coverageCheck('Even 14 valid records do NOT certify upstream reality',
        $verified['live_upstream_source_independently_verified']===false
        &&$verified['hostinger_14_day_run_verified']===false);
    $duplicate=json_decode($lines[0],true);
    $duplicate['at']='2026-11-23T11:59:00Z';
    file_put_contents($path,json_encode($duplicate)."\n",FILE_APPEND);
    $same=SourceCoverage::summarize($path,$today);
    coverageCheck('Repeated identical run ID never inflates daily sample',
        $same['distinct_actual_days']===14&&$same['unverified_legacy_or_invalid_records']===1);
    $tamper=mutated($base,['date'=>'2026-11-23','at'=>'2026-11-23T12:00:00Z',
       'source_policy_checked_at'=>'2026-11-22','from_fixture'=>true,'run_id'=>bin2hex(random_bytes(12))]);
    file_put_contents($path,json_encode($tamper)."\n",FILE_APPEND);
    $fixture=SourceCoverage::summarize($path,$today);
    coverageCheck('Fixture-labelled event never adds valid sync observation',
        $fixture['actual_sync_observations']===14&&$fixture['unverified_legacy_or_invalid_records']===2);
    file_put_contents($path,"malformed\n",FILE_APPEND);
    $improper=SourceCoverage::summarize($path,$today);
    coverageCheck('Bad JSON does not count as source evidence',
        $improper['unverified_legacy_or_invalid_records']===3
        &&$improper['distinct_actual_days']===14);
    $truncated=file_get_contents($path);file_put_contents($path,$truncated."unterminated");
    $corrupt=SourceCoverage::summarize($path,$today);
    coverageCheck('Truncated record makes entire audit log invalid',
        !$corrupt['log_valid']&&!$corrupt['14_distinct_calendar_days_measured']);
    unlink($path);
    symlink(__FILE__,$path);
    $linked=SourceCoverage::summarize($path,$today);
    coverageCheck('Symlinked coverage log rejected without reading target',
        !$linked['log_valid']&&$linked['actual_sync_observations']===0);
    unlink($path);
}finally{foreach(glob($temp.'/*')?:[] as $p)unlink($p);rmdir($temp);}
echo "\n$count strict M17 evidence-source checks passed.\n";
