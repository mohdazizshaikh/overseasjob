<?php
declare(strict_types=1);
require_once __DIR__.'/Guides.php';
/** Evidence-oriented deployment/readiness gate. Booleans are NOT evidence by themselves. */
final class Launch
{
    public static function status(string $root):array
    {
        $blockers=[];
        if(getenv('RELEASE_READY')!=='1')$blockers[]='RELEASE_READY is not set';
        $config=$root.'/config/release_evidence.json';
        $data=is_file($config)?json_decode((string)file_get_contents($config),true):null;
        $required=['hosting_ssl_cron','live_provider_rights_and_sample','reviewed_original_guides',
            'privacy_and_legal','backup_restore','staging_e2e_security_accessibility'];
        foreach($required as $key){
            $status=$data['checks'][$key]??null;$location=$status['evidence']??null;
            if(!is_array($status)||($status['verified']??false)!==true||!is_string($location)||!preg_match('#^docs/evidence/[A-Za-z0-9._-]+\.md$#',$location)){
                $blockers[]=$key.': verified check and safe evidence path required';continue;
            }
            $path=$root.'/'.$location;
            if(!is_file($path)||filesize($path)<150)$blockers[]=$key.': evidence file absent or empty';
        }
        $guides=Guides::all($root.'/content/guides.json');
        if(count($guides)!==5 || count(array_filter($guides,fn($g)=>($g['editorial_status']??'')==='reviewed'))!==5){
            $blockers[]='Five original guides require actual recorded editorial review';
        }
        $provider=json_decode((string)@file_get_contents($root.'/config/provider_policy.json'),true);
        if(!is_array($provider) || !is_array($provider['jobicy']??null))$blockers[]='Provider policy missing';
        else{
            $d=strtotime((string)($provider['jobicy']['checked_at']??''));
            if(!$d||$d<time()-30*86400)$blockers[]='Jobicy source permissions review expired';
        }
        return ['ready'=>count($blockers)===0,'blockers'=>$blockers,'documentation_only'=>false];
    }
    public static function allowed(string $root):bool{return self::status($root)['ready'];}
}
