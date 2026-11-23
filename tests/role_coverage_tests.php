<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/RoleCoverage.php';
$pass=0;
function roleCheck(string $label,bool $ok):void{
    global $pass;
    if(!$ok)throw new RuntimeException('FAIL M17 role sample '.$label);
    $pass++;echo "PASS M17 role sample $label\n";
}
$now=strtotime('2026-11-23T12:00:00Z');
$jobs=[
    ['id'=>'provider:jobicy:1','title'=>'IT Support Specialist','eligible_from_in'=>'allowed','apply_url'=>'https://jobicy.com/jobs/one','valid_through'=>null],
    ['id'=>'provider:jobicy:2','title'=>'Customer Service Representative','eligible_from_in'=>'unknown','apply_url'=>'https://jobicy.com/jobs/two','valid_through'=>null],
    ['id'=>'provider:jobicy:3','title'=>'Junior Web Developer','eligible_from_in'=>'not_allowed','apply_url'=>'https://jobicy.com/jobs/three','valid_through'=>'2026-11-22T00:00:00Z'],
    ['id'=>'provider:jobicy:4','title'=>'Finance Intern','eligible_from_in'=>'unknown','apply_url'=>'https://jobicy.com/jobs/four','valid_through'=>null],
];
roleCheck('Three clearly named pilot cohort definitions',RoleCoverage::cohorts()===['it_support','customer_support','junior_web_developer']);
$r=RoleCoverage::newLiveObservation('jobicy',$jobs,6,1,1,1,true,false,'2026-11-22',$now);
roleCheck('Actual Jobicy bounded source explicitly never called complete pagination',
    $r['partial_provider_sample']===true&&$r['fully_paginated']===false);
roleCheck('Raw fetched, unique, duplicate, invalid and unsafe apply counts persisted',
    $r['fetched_total']===6&&$r['unique_valid']===4&&$r['duplicate_rows']===1
    &&$r['invalid_rows']===1&&$r['raw_apply_url_failures']===1);
roleCheck('One source job belongs to intended cohort and India classification',
    $r['cohorts']['it_support']['relevant']===1&&$r['cohorts']['it_support']['india_allowed']===1);
roleCheck('Unknown customer eligibility stays unknown',
    $r['cohorts']['customer_support']['india_unknown']===1);
roleCheck('Junior developer source-expiry evidence correctly classified',
    $r['cohorts']['junior_web_developer']['expired_by_source_date']===1
    &&$r['cohorts']['junior_web_developer']['india_not_allowed']===1);
roleCheck('Recorded role sample has valid strict shape',RoleCoverage::validRow($r,$now));
roleCheck('Future sample cannot count',!RoleCoverage::validRow(array_replace($r,['date'=>'2026-11-24','at'=>'2026-11-24T12:00:00Z']),$now));
roleCheck('Fixture-labelled report cannot count',!RoleCoverage::validRow(array_replace($r,['from_fixture'=>true]),$now));
roleCheck('Bogus role tally fails reconciliation',!RoleCoverage::validRow(array_replace_recursive($r,['cohorts'=>['it_support'=>['india_allowed'=>2]]]),$now));
roleCheck('Bogus duplicate count fails source record reconciliation',!RoleCoverage::validRow(array_replace($r,['duplicate_rows'=>10]),$now));
roleCheck('Jobicy feed cannot claim fully paginated',!RoleCoverage::validRow(array_replace($r,['fully_paginated'=>true]),$now));
roleCheck('Himalayas feed must claim complete pagination in the observation shape',
    !RoleCoverage::validRow(array_replace($r,['provider'=>'himalayas']),$now));
roleCheck('Untrusted date is not accepted',!RoleCoverage::validRow(array_replace($r,['source_policy_checked_at'=>'2026-09-01']),$now));
roleCheck('Unknown provider does not count',!RoleCoverage::validRow(array_replace($r,['provider'=>'random']),$now));
$bad=false;try{RoleCoverage::newLiveObservation('jobicy',$jobs,6,1,1,1,true,true,'2026-11-22',$now);}catch(InvalidArgumentException){$bad=true;}
roleCheck('Input cannot fabricate complete Jobicy pagination',$bad);
$bad=false;try{RoleCoverage::newLiveObservation('jobicy',$jobs,7,1,1,1,true,false,'2026-11-22',$now);}catch(InvalidArgumentException){$bad=true;}
roleCheck('Input cannot invent fetched-total rows',$bad);
$bad=false;try{RoleCoverage::newLiveObservation('jobicy',array_merge($jobs,[$jobs[0]]),7,1,1,1,true,false,'2026-11-22',$now);}catch(InvalidArgumentException){$bad=true;}
roleCheck('Duplicate normalized ID cannot be counted as unique',$bad);
$bad=false;$broken=$jobs;$broken[0]['apply_url']='http://insecure.example/apply';
try{RoleCoverage::newLiveObservation('jobicy',$broken,6,1,1,1,true,false,'2026-11-22',$now);}catch(InvalidArgumentException){$bad=true;}
roleCheck('Unsafe source apply link cannot count as valid sample',$bad);
$h=RoleCoverage::newLiveObservation('himalayas',$jobs,4,0,0,0,false,true,'2026-11-22',$now);
roleCheck('Himalayas completed browse shape allowed for offline tests',RoleCoverage::validRow($h,$now));
$tmp=sys_get_temp_dir().'/oj-cohorts-'.bin2hex(random_bytes(6));mkdir($tmp,0700);
try{
    $path=$tmp.'/role_coverage.ndjson';
    $empty=RoleCoverage::summarize($path,$now);
    roleCheck('No role log cannot invent 14 days',$empty['days_measured']===0&&!$empty['14_days_for_2_to_3_role_cohorts_shape_valid']);
    file_put_contents($path,json_encode($r)."\n".json_encode($h)."\n");
    $twoSourceSameDay=RoleCoverage::summarize($path,$now);
    roleCheck('Two providers on one UTC day still count as ONE calendar date per role',
        $twoSourceSameDay['days_measured']===1
        &&$twoSourceSameDay['role_counts']['it_support']['sampled_dates']===1
        &&$twoSourceSameDay['role_counts']['it_support']['relevant_rows']===2);
    $lines=[];
    for($day=0;$day<14;$day++){
        $then=$now-$day*86400;
        $obs=RoleCoverage::newLiveObservation('jobicy',$jobs,6,1,1,1,true,false,'2026-11-01',$then);
        $lines[]=json_encode($obs,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($path,implode("\n",$lines)."\n");
    $report=RoleCoverage::summarize($path,$now);
    roleCheck('Fourteen synthetic UTC dates pass only local shape counting',
        $report['days_measured']===14&&$report['14_days_for_2_to_3_role_cohorts_shape_valid']);
    roleCheck('Role-level totals separate allowed, unknown and not allowed',
        $report['role_counts']['it_support']['india_allowed']===14
        &&$report['role_counts']['customer_support']['india_unknown']===14
        &&$report['role_counts']['junior_web_developer']['india_not_allowed']===14);
    roleCheck('Only latest snapshot per provider/day contributes role totals',
        $report['role_counts']['it_support']['relevant_rows']===14);
    roleCheck('No synthetic log independently proves licensed upstream or Hostinger history',
        $report['external_provider_permission_independently_verified']===false
        &&$report['original_hostinger_14_day_continuity_verified']===false);
    file_put_contents($path,implode("\n",array_slice($lines,1))."\n");
    $short=RoleCoverage::summarize($path,$now);
    roleCheck('Only 13 actual UTC days cannot clear role-supply gate',
        $short['days_measured']===13&&!$short['14_days_for_2_to_3_role_cohorts_shape_valid']);
    file_put_contents($path,implode("\n",$lines)."\n");
    $badRow=json_decode($lines[0],true);$badRow['from_fixture']=true;
    file_put_contents($path,json_encode($badRow)."\n",FILE_APPEND);
    $ignored=RoleCoverage::summarize($path,$now);
    roleCheck('Fixture-labelled extra row excluded, reported invalid',
        $ignored['days_measured']===14&&$ignored['ignored_invalid_rows']===1);
    file_put_contents($path,"unterminated",FILE_APPEND);
    $corrupt=RoleCoverage::summarize($path,$now);
    roleCheck('Truncated role observation log invalidates entire 14-day tally',
        !$corrupt['log_valid']&&!$corrupt['14_days_for_2_to_3_role_cohorts_shape_valid']);
    unlink($path);symlink(__FILE__,$path);
    $linked=RoleCoverage::summarize($path,$now);
    roleCheck('Symlinked role log refused rather than trusting arbitrary source',
        !$linked['log_valid']&&$linked['days_measured']===0);
    unlink($path);
    $noMatch=[['id'=>'provider:jobicy:x','title'=>'General Internship','eligible_from_in'=>'unknown','apply_url'=>'https://jobicy.com/jobs/a']];
    $zero=RoleCoverage::newLiveObservation('jobicy',$noMatch,1,0,0,0,true,false,'2026-11-22',$now);
    file_put_contents($path,json_encode($zero)."\n");
    $zeroReport=RoleCoverage::summarize($path,$now);
    roleCheck('Zero observed role matches are reported as zero, not invented supply',
        array_sum(array_column($zeroReport['role_counts'],'relevant_rows'))===0);
    unlink($path);
}finally{if(is_file($path)||is_link($path))unlink($path);rmdir($tmp);}
echo "\n$pass strict M17 role-cohort evidence checks passed (synthetic only).\n";
