<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/OpsSnapshot.php';
$n=0;function opCheck(bool $value,string $why):void{global $n;if(!$value)throw new RuntimeException($why);$n++;echo "PASS $why\n";}
$base=sys_get_temp_dir().'/oj-ops-'.bin2hex(random_bytes(6));mkdir($base,0700);mkdir($base.'/storage',0700);
$now=time();
try{
    $empty=OpsSnapshot::collect($base,$now);
    opCheck($empty['cache_state']==='absent'&&$empty['visible']===0,'No provider cache is not falsely healthy');
    opCheck($empty['actual_sample_days']===0&&!$empty['14_day_sample_complete'],'Missing 14-day evidence never fabricated');
    opCheck($empty['host_backup_restore_verified']===false,'Local reports cannot certify hosting disaster recovery');
    file_put_contents($base.'/storage/jobs.json','{bad json');
    $bad=OpsSnapshot::collect($base,$now);
    opCheck($bad['cache_state']==='invalid'&&$bad['visible']===0,'Corrupt cache fails safely');
    file_put_contents($base.'/storage/jobs.json',json_encode(['synced_at'=>gmdate('c',$now),'jobs'=>[]]));
    $ok=OpsSnapshot::collect($base,$now);
    opCheck($ok['cache_state']==='present'&&$ok['cache_total']===0,'Empty valid cache disclosed truthfully');
    file_put_contents($base.'/storage/reports.json',json_encode([
      ['id'=>'case-001','state'=>'pending_review','email'=>'secret_candidate@example.org','details'=>'PRIVATE']
    ]));
    $reported=OpsSnapshot::collect($base,$now);
    opCheck($reported['pending_reports']===1,'Owner-only report backlog counted');
    opCheck(!str_contains(json_encode($reported),'secret_candidate@example.org')&&!str_contains(json_encode($reported),'PRIVATE'),
       'Private report content not included in health snapshot');
    file_put_contents($base.'/storage/coverage.ndjson',json_encode(['date'=>gmdate('Y-m-d',$now),'provider'=>'jobicy'])."\n");
    $legacy=OpsSnapshot::collect($base,$now);
    opCheck($legacy['actual_sample_days']===0&&!$legacy['14_day_sample_complete'],
        'Bare legacy provider/day rows cannot count as fourteen-day evidence');
    opCheck($legacy['unverified_coverage_rows']===1,
        'Owner health reveals legacy unverified coverage without accepting it');
    $event=SourceCoverage::newLiveObservation('jobicy',
        [['eligible_from_in'=>'unknown']],0,true,gmdate('Y-m-d',$now),$now);
    file_put_contents($base.'/storage/coverage.ndjson',json_encode($event)."\n",FILE_APPEND);
    $covered=OpsSnapshot::collect($base,$now);
    opCheck($covered['actual_sample_days']===1&&!$covered['14_day_sample_complete'],
        'Strict successful live sync record accepted for one UTC date');
    opCheck($covered['live_provider_samples_independently_verified']===false,
        'Locally recorded observations do not falsely certify external source truth');
    opCheck($covered['real_inbox_delivery_verified']===false&&$covered['external_hosting_verified']===false,'No invented hosting or email delivery evidence');
    opCheck(count($covered['warnings'])>0,'Operational gaps generate explicit warnings');
}finally{
    foreach(glob($base.'/storage/*')?:[] as $f)unlink($f);
    rmdir($base.'/storage');rmdir($base);
}
echo "\n$n ops snapshot checks passed.\n";
