<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Launch.php';
$pass=0;
function releaseCheck(string $label,bool $yes):void{
    global $pass;
    if(!$yes){fwrite(STDERR,"FAIL gate: $label\n");exit(1);}
    $pass++;echo "PASS gate: $label\n";
}
function blockedBy(array $state,string $fragment):bool{
    foreach($state['blockers'] as $reason)if(str_contains($reason,$fragment))return true;
    return false;
}
$now=strtotime('2026-11-24T12:00:00Z');
$tmp=sys_get_temp_dir().'/oj-release-'.bin2hex(random_bytes(5));
foreach(['','config','docs','docs/evidence','storage','content','SOURCE_OF_TRUTH_REFERENCE'] as $segment){
    $path=$tmp.($segment===''?'':'/'.$segment);
    if(!mkdir($path,0700))throw new RuntimeException('Fixture setup failed');
}
$priorReady=getenv('RELEASE_READY');
try{
    copy(dirname(__DIR__).'/content/guides.json',$tmp.'/content/guides.json');
    file_put_contents($tmp.'/RELEASE_MANIFEST.json',json_encode(['source_of_truth'=>'v1.3.3']));
    file_put_contents($tmp.'/module_status.json',json_encode(['spec_version'=>'v1.3.4']));
    file_put_contents($tmp.'/config/provider_policy.json',json_encode(['jobicy'=>['enabled'=>false]]));
    $checks=['hosting_ssl_cron','live_provider_rights_and_sample','reviewed_original_guides',
        'privacy_and_legal','backup_restore','staging_e2e_security_accessibility'];
    $register=['checks'=>[]];
    foreach($checks as $key)$register['checks'][$key]=['verified'=>false,'evidence'=>null];
    file_put_contents($tmp.'/config/release_evidence.json',json_encode($register));
    putenv('RELEASE_READY=0');
    $state=Launch::status($tmp,$now);
    releaseCheck('Preview flag denies launch',!$state['ready']&&blockedBy($state,'RELEASE_READY'));
    putenv('RELEASE_READY=1');
    $state=Launch::status($tmp,$now);
    releaseCheck('Flag cannot bypass historical canonical version',!$state['ready']&&blockedBy($state,'v1.4.1'));
    releaseCheck('Exact canonical ZIP hash required',blockedBy($state,'Exact founder'));
    releaseCheck('Unverified module and Hostinger status blocks release',
        blockedBy($state,'Module ledger public-launch'));
    releaseCheck('Fourteen real-dated sample rows are required',blockedBy($state,'Fourteen distinct'));
    releaseCheck('No default owner approval',blockedBy($state,'Private owner'));

    foreach($checks as $key){
        $path='docs/evidence/'.$key.'.md';
        file_put_contents($tmp.'/'.$path,str_repeat('SYNTHETIC PLACEHOLDER: NOT actual Hostinger or source verification. ',4));
        $register['checks'][$key]=['verified'=>true,'evidence'=>$path,
            'reviewed_at'=>'2026-11-24T11:30:00Z'];
    }
    file_put_contents($tmp.'/config/release_evidence.json',json_encode($register));
    releaseCheck('Padded verified Markdown cannot bypass SHA-256',
        blockedBy(Launch::status($tmp,$now),'current evidence review'));
    foreach($checks as $key){
        $register['checks'][$key]['sha256']=hash_file('sha256',$tmp.'/'.$register['checks'][$key]['evidence']);
    }
    file_put_contents($tmp.'/config/release_evidence.json',json_encode($register));
    $state=Launch::status($tmp,$now);
    releaseCheck('Exact hashed evidence passes only hash layer',
        !blockedBy($state,'current evidence review')&&!blockedBy($state,'evidence file changed'));
    $f=$tmp.'/docs/evidence/backup_restore.md';
    file_put_contents($f,"tamper\n",FILE_APPEND);
    releaseCheck('After-review edit invalidates SHA-256',blockedBy(Launch::status($tmp,$now),'evidence file changed'));
    file_put_contents($f,str_repeat('SYNTHETIC PLACEHOLDER: NOT actual Hostinger or source verification. ',4));
    $f=$tmp.'/docs/evidence/hosting_ssl_cron.md';
    unlink($f);
    symlink($tmp.'/docs/evidence/privacy_and_legal.md',$f);
    releaseCheck('Symlinked evidence cannot be trusted',blockedBy(Launch::status($tmp,$now),'evidence file changed'));
    unlink($f);
    file_put_contents($f,str_repeat('SYNTHETIC PLACEHOLDER: NOT actual Hostinger or source verification. ',4));

    $auth=[
        'schema_version'=>1,
        'approved_for_public_launch'=>true,
        'independent_external_checks_reviewed'=>true,
        'reviewer_role'=>'owner',
        'reviewed_at'=>'2026-11-24T11:30:00Z',
        'canonical_sha256'=>'3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9',
        'release_manifest_sha256'=>hash_file('sha256',$tmp.'/RELEASE_MANIFEST.json'),
        'evidence_register_sha256'=>hash_file('sha256',$tmp.'/config/release_evidence.json'),
        'review_notes'=>str_repeat('TEST ONLY: invented record is NOT production approval. ',3)
    ];
    $authPath=$tmp.'/storage/release_authorization.json';
    file_put_contents($authPath,json_encode($auth));
    chmod($authPath,0644);
    releaseCheck('World-readable approval file rejected',blockedBy(Launch::status($tmp,$now),'Private owner'));
    chmod($authPath,0600);
    $state=Launch::status($tmp,$now);
    releaseCheck('Private file only clears its own local integrity check',!blockedBy($state,'Private owner'));
    releaseCheck('Fake owner approval cannot clear missing canonical ZIP',blockedBy($state,'Exact founder'));
    releaseCheck('Fake owner approval cannot clear missing real samples',blockedBy($state,'Fourteen distinct'));
    releaseCheck('Disabled provider remains a launch blocker',blockedBy($state,'No permitted source'));
    file_put_contents($tmp.'/config/release_evidence.json',json_encode(['checks'=>[]]));
    releaseCheck('Altered evidence register invalidates owner binding',
        blockedBy(Launch::status($tmp,$now),'Private owner'));
    releaseCheck('Synthetic test never produces production readiness',Launch::status($tmp,$now)['ready']===false);
}finally{
    putenv('RELEASE_READY='.($priorReady===false?'':$priorReady));
    $walk=new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach($walk as $item){
        if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($tmp);
}
echo "\n$pass synthetic fail-closed launch-gate regression checks passed.\n";
