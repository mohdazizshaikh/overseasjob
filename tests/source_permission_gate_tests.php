<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/SourcePermissionGate.php';
$checks=0;
function proofCheck(string $name,bool $passes):void {
    global $checks;
    if(!$passes)throw new RuntimeException('FAIL source gate: '.$name);
    $checks++;echo "PASS source gate: $name\n";
}
$root=sys_get_temp_dir().'/oj-rights-'.bin2hex(random_bytes(7));
if(!mkdir($root,0700)||!mkdir($root.'/storage',0700)
  ||!mkdir($root.'/storage/provider_rights',0700))
    throw new RuntimeException('Cannot create isolated private fixture');
$today=strtotime('2026-11-23T12:00:00Z');
$policy=['enabled'=>true,'checked_at'=>'2026-11-22',
    'terms_url'=>'https://jobicy.com/jobs-rss-feed','canonical_source_required'=>true,
    'allow_resyndication'=>false,'allow_jobposting_schema'=>false];
$flags=['SOURCE_LIVE_SYNC_ENABLED'=>'0',
    'SOURCE_JOBICY_RIGHTS_REVIEWED'=>'0','SOURCE_HIMALAYAS_RIGHTS_REVIEWED'=>'0'];
$path=$root.'/storage/provider_rights/jobicy.json';
$makeProof=static function(array $changes=[])use($policy):array {
    return array_replace(['schema_version'=>1,'provider'=>'jobicy',
        'reviewed_at'=>'2026-11-22','terms_url'=>$policy['terms_url'],
        'reviewer_role'=>'owner','scope'=>'source_linked_listing_only',
        'approved_for_single_source_display'=>true,
        'review_notes'=>'Operator confirmed intended source-link-only usage after reviewing current published provider terms.'],
        $changes);
};
$decision=static fn(array $p,array $f)=>SourcePermissionGate::readiness($root,'jobicy',$p,$f,$today);
try {
    $d=$decision($policy,$flags);
    proofCheck('Default source flags and absent operator attestation deny live sync',
        !$d['ready']&&in_array('global_live_sync_opt_in_disabled',$d['blockers'],true)
        &&in_array('private_operator_attestation_missing_or_invalid',$d['blockers'],true));
    $flags['SOURCE_LIVE_SYNC_ENABLED']='1';
    $d=$decision($policy,$flags);
    proofCheck('Global enabled without specific provider approval still blocked',
        !$d['ready']&&in_array('provider_operator_approval_disabled',$d['blockers'],true));
    $flags['SOURCE_JOBICY_RIGHTS_REVIEWED']='1';
    $d=$decision($policy,$flags);
    proofCheck('Both operator flags without private attestation still blocked',!$d['ready']);
    file_put_contents($path,json_encode($makeProof(),JSON_THROW_ON_ERROR));
    chmod($path,0644);
    proofCheck('Group/world-readable rights approval refuses live source',
        !$decision($policy,$flags)['ready']);
    chmod($path,0600);
    chmod($root.'/storage/provider_rights',0755);
    proofCheck('Group/world-traversable source rights directory refuses live sync',
        !$decision($policy,$flags)['ready']);
    chmod($root.'/storage/provider_rights',0700);
    $d=$decision($policy,$flags);
    proofCheck('Explicit flags and matching private simulated approval pass local gate',
        $d['ready']&&$d['blockers']===[]);
    proofCheck('Even passing local attestation NEVER claims independent licence proof',
        $d['operator_attestation_not_external_verification']);
    $old=$policy;$old['checked_at']='2026-09-01';
    proofCheck('Expired source-policy review date blocks requests',!$decision($old,$flags)['ready']);
    $future=$policy;$future['checked_at']='2026-11-24';
    proofCheck('Future policy-review date blocked',!$decision($future,$flags)['ready']);
    $bad=$policy;$bad['enabled']=false;
    proofCheck('Provider source policy disabled blocks any operator flags',!$decision($bad,$flags)['ready']);
    $bad=$policy;$bad['allow_resyndication']=true;
    proofCheck('Unsafe resyndication permission request rejected',!$decision($bad,$flags)['ready']);
    $bad=$policy;$bad['canonical_source_required']=false;
    proofCheck('Source attribution disablement rejected',!$decision($bad,$flags)['ready']);
    $bad=$policy;$bad['allow_jobposting_schema']=true;
    proofCheck('Undocumented JobPosting markup sharing rejected',!$decision($bad,$flags)['ready']);
    $bad=$policy;$bad['terms_url']='https://evil.example/terms';
    proofCheck('Off-provider terms URL rejected',!$decision($bad,$flags)['ready']);
    file_put_contents($path,json_encode($makeProof(['review_notes'=>'approve']),JSON_THROW_ON_ERROR));
    proofCheck('Short reason note rejected',!$decision($policy,$flags)['ready']);
    file_put_contents($path,json_encode($makeProof(['reviewer_role'=>'robot']),JSON_THROW_ON_ERROR));
    proofCheck('Not independently operator reviewed: refused',!$decision($policy,$flags)['ready']);
    file_put_contents($path,json_encode($makeProof(['reviewed_at'=>'2026-11-21']),JSON_THROW_ON_ERROR));
    proofCheck('Stale approval does not authorize new policy revision',!$decision($policy,$flags)['ready']);
    file_put_contents($path,json_encode($makeProof(['approved_for_single_source_display'=>false]),JSON_THROW_ON_ERROR));
    proofCheck('Draft/unapproved operator evidence refused',!$decision($policy,$flags)['ready']);
    file_put_contents($path,'not json');
    proofCheck('Corrupt operator attestation refused',!$decision($policy,$flags)['ready']);
    unlink($path);
    symlink(__FILE__,$path);
    proofCheck('Symlink private approval file refused',!$decision($policy,$flags)['ready']);
    unlink($path);
    file_put_contents($path,json_encode($makeProof(),JSON_THROW_ON_ERROR));
    $himPolicy=$policy;$himPolicy['terms_url']='https://himalayas.app/terms';
    $h=SourcePermissionGate::readiness($root,'himalayas',$himPolicy,$flags,$today);
    proofCheck('Provider-specific flags cannot accidentally enable Himalayas',!$h['ready']);
    proofCheck('Arbitrary unknown provider never allowed',
        !SourcePermissionGate::readiness($root,'untrusted_api',$policy,$flags,$today)['ready']);
    $source=(string)file_get_contents(dirname(__DIR__).'/scripts/sync_jobs.php');
    proofCheck('Real-sync HTTP gate exists before first ProviderHttp::fetchJson',
        strpos($source,'SourcePermissionGate::readiness')!==false
        &&strpos($source,'SourcePermissionGate::readiness')
            <strpos($source,'ProviderHttp::fetchJson'));
}finally{
    if(is_link($path)||is_file($path))unlink($path);
    rmdir($root.'/storage/provider_rights');
    rmdir($root.'/storage');
    rmdir($root);
}
echo "\n$checks private provider-live activation checks passed.\n";
