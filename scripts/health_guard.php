<?php
/** Read-only CLI health guard for an authorised host scheduler; no network calls, secrets or paid alerts. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit(2);}
require dirname(__DIR__).'/src/OpsSnapshot.php';
try{
    $snapshot=OpsSnapshot::collect(dirname(__DIR__),time());
    $healthy=$snapshot['cache_state']==='present' && $snapshot['last_sync']!==null
       && !$snapshot['warnings'];
    $out=['checked_at'=>$snapshot['at'],'healthy_for_local_cache_only'=>$healthy,
        'visible_jobs'=>$snapshot['visible'],'warnings'=>$snapshot['warnings'],
        'production_host_and_backups_certified'=>false];
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    exit($healthy?0:2);
}catch(Throwable){fwrite(STDERR,"Local operational guard failed safely\n");exit(2);}
