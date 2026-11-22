<?php
declare(strict_types=1);
/** Bounded authoritative Himalayas job status reconciliation. Disabled until source policy review. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(2);}
$root=dirname(__DIR__);
require $root.'/src/Jobs.php';
require $root.'/src/ProviderHttp.php';
$fixture=null;
foreach(array_slice($argv,1) as $arg){
    if(str_starts_with($arg,'--fixture='))$fixture=substr($arg,10);
    else {fwrite(STDERR,"Usage: php scripts/check_himalayas_status.php [--fixture=path]\n");exit(2);}
}
if($fixture===null){
    $policies=json_decode((string)@file_get_contents($root.'/config/provider_policy.json'),true);
    $policy=$policies['himalayas']??[];
    if(($policy['enabled']??false)!==true || (strtotime((string)($policy['checked_at']??''))?:0)<time()-30*86400){
        fwrite(STDERR,"Himalayas source disabled or needs current rights review\n");exit(3);
    }
}
$cache=$root.'/storage/'.($fixture===null?'jobs.json':'fixture_jobs.json');
$lock=fopen($root.'/storage/sync.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Sync lock unavailable\n");exit(4);}
try{
    if(!is_file($cache)||is_link($cache)||filesize($cache)>16000000)throw new RuntimeException('Valid private job cache required');
    $snapshot=json_decode((string)file_get_contents($cache),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($snapshot['jobs']??null))throw new RuntimeException('Invalid job cache');
    $fixtureStatuses=null;
    if($fixture!==null){
        if(!is_file($fixture)||is_link($fixture)||filesize($fixture)>80000)throw new RuntimeException('Invalid status fixture');
        $fixtureStatuses=json_decode((string)file_get_contents($fixture),true,32,JSON_THROW_ON_ERROR)['statuses']??null;
        if(!is_array($fixtureStatuses))throw new RuntimeException('Missing status fixture map');
    }
    $candidateIds=[];
    foreach($snapshot['jobs'] as $i=>$job){
        if(!is_array($job)||($job['provider']??'')!=='himalayas')continue;
        $guid=$job['source_guid']??$job['source_url']??null;
        try{Jobs::himalayasStatusUrl((string)$guid);}catch(Throwable){continue;}
        $candidateIds[]=$i;
    }
    // Oldest status checks first, 10 checks per invocation; every job rotates over time.
    usort($candidateIds,static function(int $a,int $b)use($snapshot):int{
        $x=$snapshot['jobs'][$a]['last_status_checked_at']??'';
        $y=$snapshot['jobs'][$b]['last_status_checked_at']??'';
        return strcmp((string)$x,(string)$y) ?: ($a<=>$b);
    });
    $checked=0;$closed=0;$unresolved=0;$now=gmdate('Y-m-d\TH:i:s\Z');
    foreach(array_slice($candidateIds,0,10) as $i){
        $job=&$snapshot['jobs'][$i];
        $guid=(string)($job['source_guid']??$job['source_url']);
        $url=Jobs::himalayasStatusUrl($guid);
        $result=$fixtureStatuses!==null?($fixtureStatuses[$guid]??null):ProviderHttp::request($url,150000);
        if(!is_array($result)||!is_int($result['status']??null))throw new RuntimeException('Missing authoritative status response');
        $decision=Jobs::himalayasStatusDecision($result['status']);
        // Fail closed if not an explicitly documented 200/410/404 status.
        if($decision==='unknown'&&$result['status']!==404)throw new RuntimeException('Upstream status uncertain, old cache preserved');
        if($decision==='active'){
            $body=$result['body']??null;
            if(!is_string($body))throw new RuntimeException('Missing single-job response');
            $payload=json_decode($body,true,32,JSON_THROW_ON_ERROR);
            if(($payload['job']['guid']??null)!==$guid)throw new RuntimeException('Single-job GUID does not match; old cache preserved');
            $job['availability']='active';
            $job['last_verified_at']=$now;
        }elseif($decision==='closed'){
            $job['availability']='closed';$job['display_status']='hidden';$closed++;
            $job['reason_tags']=array_values(array_unique(array_merge($job['reason_tags']??[],['provider_http_410_closed'])));
        }else{
            // 404 is NOT authoritative closure. Hide conservatively, retain provenance.
            $job['availability']='unknown';$job['display_status']='hidden';$unresolved++;
            $job['reason_tags']=array_values(array_unique(array_merge($job['reason_tags']??[],['provider_http_404_unconfirmed'])));
        }
        $job['last_status_checked_at']=$now;$checked++;unset($job);
    }
    if($checked){
        $tmp=tempnam(dirname($cache),'status-');
        if($tmp===false)throw new RuntimeException('Unable to allocate private update');
        try{
            $json=json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('Unable to store status cache');
            chmod($tmp,0600);
            if(!rename($tmp,$cache))throw new RuntimeException('Unable to publish status cache');
        }finally{if(is_file($tmp))unlink($tmp);}
    }
    echo json_encode(['checked'=>$checked,'closed'=>$closed,'unconfirmed_404'=>$unresolved,
        'network_live'=>$fixture===null,'remaining_unchecked'=>max(0,count($candidateIds)-$checked)],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,"Status check failed safely: ".$e->getMessage()."\n");exit(1);}
finally{flock($lock,LOCK_UN);fclose($lock);}
