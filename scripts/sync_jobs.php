<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Jobs.php';

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
if (!$fixture && !$policy['enabled']) {fwrite(STDERR,"Provider disabled in policy configuration\n");exit(3);}
if (!$fixture && strtotime((string)($policy['checked_at']??'')) < time()-30*86400){fwrite(STDERR,"Provider rights need re-review (>30 days); sync blocked\n");exit(3);}
$lock=fopen($base.'/storage/sync.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Concurrent sync blocked\n");exit(4);}
function fetchApi(string $url, int $limitBytes=2000000): array {
    // Only hard-coded upstream hosts and HTTPS; no user-provided fetch URLs.
    $allowed=['jobicy.com','himalayas.app'];
    $parts=parse_url($url);
    if(($parts['scheme']??'')!=='https'||!in_array(($parts['host']??''),$allowed,true))throw new RuntimeException('Forbidden upstream');
    $ctx=stream_context_create(['http'=>[
        'timeout'=>20,'follow_location'=>0,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\nUser-Agent: OverseasJob.in/0.1 (+https://overseasjob.in; source-linked cache)\r\n"
    ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $fp=@fopen($url,'rb',false,$ctx);
    if(!$fp)throw new RuntimeException('Source connection unavailable');
    $meta=stream_get_meta_data($fp);
    $headers=$meta['wrapper_data']??[];
    $line=is_array($headers)?($headers[0]??''):'';
    if(!preg_match('/^HTTP\/\S+ 200\b/',(string)$line)){fclose($fp);throw new RuntimeException('Unexpected provider HTTP status: '.substr((string)$line,0,70));}
    $body=stream_get_contents($fp,$limitBytes+1);fclose($fp);
    if($body===false||strlen($body)>$limitBytes)throw new RuntimeException('Source payload too large');
    $data=json_decode($body,true);
    if(!is_array($data))throw new RuntimeException('Invalid provider JSON');
    return $data;
}
try {
    $payloads=[]; $seenPageSignatures=[]; $partial=false;
    if($fixture!==null){
        $data=json_decode((string)file_get_contents($fixture),true,512,JSON_THROW_ON_ERROR);
        if(!is_array($data)||!is_array($data['jobs']??null))throw new RuntimeException('Invalid fixture shape');
        $payloads=[$data];
    } elseif($provider==='jobicy'){
        $payloads=[fetchApi('https://jobicy.com/api/v2/remote-jobs?count=100')];
    } else {
        // The current official browse endpoint uses offset+limit. Never switch
        // mid-traversal to a hypothetical cursor field; protect cache from repeated pages.
        $offset=0;$limit=20;$maxPages=10;$finished=false;
        for($page=0;$page<$maxPages;$page++){
            $url='https://himalayas.app/jobs/api?limit='.$limit.'&offset='.$offset;
            $data=fetchApi($url,1200000);
            $signature=Jobs::himalayasPageSignature($data);
            if(isset($seenPageSignatures[$signature]))throw new RuntimeException('Repeated Himalayas page despite advancing offset; previous good cache preserved');
            $seenPageSignatures[$signature]=true;
            $payloads[]=$data;
            $nextOffset=Jobs::himalayasNextOffset($data,$offset,$limit);
            if($nextOffset===null){$finished=true;break;}
            $offset=$nextOffset;
        }
        $partial=!$finished;
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
    $summary=['provider'=>$provider,'success_at'=>gmdate('c'),'seen'=>count($new),'invalid'=>$invalid,'updated'=>$updated,'from_fixture'=>$fixture!==null,'partial_provider_sample'=>$partial,'pagination_mode'=>$provider==='himalayas'?'offset_limit':'bounded_recent_feed'];
    $oldStats=is_array($old['providers']??null)?$old['providers']:[];
    $oldStats[$provider]=$summary;
    $output=['synced_at'=>gmdate('c'),'providers'=>$oldStats,'jobs'=>array_values($existing)];
    $temp=tempnam($base.'/storage','jobs-');
    if(!$temp)throw new RuntimeException('Cannot create cache temp file');
    if(file_put_contents($temp,json_encode($output,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot write cache');
    chmod($temp,0600);
    if(!rename($temp,$file))throw new RuntimeException('Atomic cache publish failed');
    if(!$fixture){
        // Longitudinal observations only; NEVER generate retrospective fake day counts.
        $coverage=['date'=>gmdate('Y-m-d'),'at'=>gmdate('c'),'provider'=>$provider,'valid'=>count($new),'invalid'=>$invalid,
            'india_allowed'=>count(array_filter($new,fn($j)=>$j['eligible_from_in']==='allowed')),
            'india_unknown'=>count(array_filter($new,fn($j)=>$j['eligible_from_in']==='unknown')),
            'india_not_allowed'=>count(array_filter($new,fn($j)=>$j['eligible_from_in']==='not_allowed')),'partial_provider_sample'=>$partial];
        file_put_contents($base.'/storage/coverage.ndjson',json_encode($coverage)."\n",FILE_APPEND|LOCK_EX);
    }
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e){fwrite(STDERR,"Sync failed safely: ".$e->getMessage()."\n");exit(1);}finally{flock($lock,LOCK_UN);fclose($lock);}
