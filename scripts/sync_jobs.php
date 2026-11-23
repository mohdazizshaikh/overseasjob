<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Jobs.php';
require dirname(__DIR__).'/src/ProviderHttp.php';
require dirname(__DIR__).'/src/SourceCoverage.php';
require dirname(__DIR__).'/src/SourcePermissionGate.php';

/** Server-side scheduled ingestion. Run only by PHP CLI or cron, never from a web route. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$base=dirname(__DIR__);
$provider=$argv[1]??'jobicy';
if (!in_array($provider,['jobicy','himalayas'],true)) {fwrite(STDERR,"Usage: php scripts/sync_jobs.php jobicy|himalayas [--fixture=path]\n");exit(2);}
$fixture=null;
foreach ($argv as $arg) if(str_starts_with($arg,'--fixture='))$fixture=substr($arg,10);
$config=json_decode((string)file_get_contents($base.'/config/provider_policy.json'),true);
if (!is_array($config)||!is_array($config[$provider]??null)) {fwrite(STDERR,"Missing source-policy config\n");exit(2);}
$policy=$config[$provider];
if($fixture===null){
    // Legal rights are not established by a JSON enabled flag or a recent date.
    // Never issue any LIVE outbound request without independent operator intent
    // and a non-public attestation specific to this provider and policy revision.
    $activation=SourcePermissionGate::readiness($base,$provider,$policy,
        SourcePermissionGate::runtimeFlags(),time());
    if(!$activation['ready']){
        fwrite(STDERR,'Live source access blocked: '.implode(',',$activation['blockers'])."\n");
        exit(3);
    }
}
if (!$fixture && !$policy['enabled']) {fwrite(STDERR,"Provider disabled in policy configuration\n");exit(3);}
if (!$fixture && strtotime((string)($policy['checked_at']??'')) < time()-30*86400){fwrite(STDERR,"Provider rights need re-review (>30 days); sync blocked\n");exit(3);}
$lock=fopen($base.'/storage/sync.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Concurrent sync blocked\n");exit(4);}
try {
    $payloads=[]; $seenPageSignatures=[];
    // A recent-window Jobicy feed is never a full source inventory.
    $partial=($provider==='jobicy');
    if($fixture!==null){
        $data=json_decode((string)file_get_contents($fixture),true,512,JSON_THROW_ON_ERROR);
        if(!is_array($data)||!is_array($data['jobs']??null))throw new RuntimeException('Invalid fixture shape');
        $payloads=[$data];
    } elseif($provider==='jobicy'){
        $payloads=[ProviderHttp::fetchJson('https://jobicy.com/api/v2/remote-jobs?count=100')];
    } else {
        // Official 2026 OpenAPI browse: offset + limit + totalCount (no cursor).
        // Preserve old healthy cache if any page conflicts or a crawl is partial.
        $offset=0;$finished=false;$maxPages=100;
        for($page=0;$page<$maxPages;$page++){
            $data=ProviderHttp::fetchJson(Jobs::himalayasBrowseUrl($offset),1200000);
            $signature=Jobs::himalayasPageSignature($data);
            if(isset($seenPageSignatures[$signature]))throw new RuntimeException('Repeated Himalayas page; old cache preserved');
            $seenPageSignatures[$signature]=true;
            $next=Jobs::himalayasNextOffset($data,$offset);
            $payloads[]=$data;
            if($next===null){$finished=true;break;}
            $offset=$next;
        }
        if(!$finished)throw new RuntimeException('Himalayas offset page cap exceeded; old cache preserved');
    }
    $new=[];$invalid=0;
    foreach ($payloads as $data){
        if(!is_array($data['jobs']??null))throw new RuntimeException('Provider response lacks jobs');
        foreach($data['jobs'] as $job){
            if(!is_array($job)){$invalid++;continue;}
            $normal=$provider==='jobicy'?Jobs::fromJobicy($job):Jobs::fromHimalayas($job);
            if(!$normal){$invalid++;continue;}
            $new[$normal['id']]=$normal;
        }
    }
    if(!$new)throw new RuntimeException('Zero valid listings: preserve last good snapshot');
    if(count($new)<3 && !$fixture)throw new RuntimeException('Suspicious provider dataset size: snapshot preserved');
    $file=$base.'/storage/'.($fixture!==null?'fixture_jobs.json':'jobs.json');
    $old=is_file($file)?json_decode((string)file_get_contents($file),true):null;
    if(is_file($file)&&!is_array($old))throw new RuntimeException('Existing cache corrupt: refusing overwrite');
    $existing=[];
    foreach (($old['jobs']??[]) as $job)if(is_array($job)&&isset($job['id']))$existing[$job['id']]=$job;
    $updated=0;
    foreach($new as $id=>$job){
        if(isset($existing[$id])){
            $job['first_seen_at']=$existing[$id]['first_seen_at']??$job['first_seen_at'];
            $updated++;
        }
        $existing[$id]=$job;
    }
    // Missing from a bounded feed does NOT imply closed. Retention is visibility-limited by 72h freshness.
    $now=time();
    $existing=array_filter($existing,static function($j)use($now){
        return (strtotime($j['last_seen_at']??'')?:0)>$now-30*86400;
    });
    $summary=['provider'=>$provider,'success_at'=>gmdate('c'),'seen'=>count($new),'invalid'=>$invalid,'updated'=>$updated,'from_fixture'=>$fixture!==null,'partial_provider_sample'=>$partial,'pagination_mode'=>$provider==='himalayas'?'offset_limit_totalCount':'bounded_recent_feed'];
    $oldStats=is_array($old['providers']??null)?$old['providers']:[];
    $oldStats[$provider]=$summary;
    $output=['synced_at'=>gmdate('c'),'providers'=>$oldStats,'jobs'=>array_values($existing)];
    $temp=tempnam($base.'/storage','jobs-');
    if(!$temp)throw new RuntimeException('Cannot create cache temp file');
    if(file_put_contents($temp,json_encode($output,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot write cache');
    chmod($temp,0600);
    if(!rename($temp,$file))throw new RuntimeException('Atomic cache publish failed');
    if(!$fixture){
        // Record only an actually completed *live* sync, never fixture data or retroactive days.
        // The schema makes local log shape auditable; it is not external source authentication.
        $coverage=SourceCoverage::newLiveObservation($provider,$new,$invalid,$partial,
            (string)($policy['checked_at']??''),time());
        $written=file_put_contents($base.'/storage/coverage.ndjson',
            json_encode($coverage,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n",
            FILE_APPEND|LOCK_EX);
        if($written===false)throw new RuntimeException('Published cache, but audit coverage log could not be written; source evidence incomplete');
        $summary['coverage_logged_v2']=true;
    }
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e){fwrite(STDERR,"Sync failed safely: ".$e->getMessage()."\n");exit(1);}finally{flock($lock,LOCK_UN);fclose($lock);}
