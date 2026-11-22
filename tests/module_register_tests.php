<?php
declare(strict_types=1);
/** Enforce the mandatory Source-of-Truth M00–M17 implementation register. */
$root=dirname(__DIR__);
$data=json_decode((string)file_get_contents($root.'/module_status.json'),true,64,JSON_THROW_ON_ERROR);
if(!is_array($data['modules']??null))throw new RuntimeException('Module register absent');
$expected=array_map(static fn(int $i)=>sprintf('M%02d',$i),range(0,17));
$allowed=['specified','implemented','tested','deployed','blocked_with_reason'];
$ids=[];
foreach($data['modules'] as $m){
    foreach(['module_id','spec_version','implementation_status','tests','routes','dependencies',
             'evidence_paths','blocker_reason','last_verified_at'] as $field)
        if(!array_key_exists($field,$m))throw new RuntimeException('Mandatory field missing: '.($m['module_id']??'?').' '.$field);
    $id=$m['module_id'];if(!is_string($id)||!in_array($id,$expected,true)||in_array($id,$ids,true))
        throw new RuntimeException('Duplicate or unknown module');
    $ids[]=$id;
    if(($m['spec_version']??null)!==($data['spec_version']??null)
        ||!in_array($m['implementation_status'],$allowed,true)
        ||!is_array($m['tests'])||!is_array($m['routes'])||!is_array($m['dependencies'])
        ||!is_array($m['evidence_paths']) || !is_string($m['blocker_reason']))
        throw new RuntimeException('Invalid module field values: '.$id);
    $stamp=$m['last_verified_at'];
    if($stamp!==null && (!is_string($stamp)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$stamp)
        ||strtotime($stamp)===false))throw new RuntimeException('Invalid verification timestamp');
    foreach($m['dependencies'] as $dep)if(!in_array($dep,$expected,true)||$dep===$id)
        throw new RuntimeException('Invalid module dependency: '.$id);
    foreach($m['evidence_paths'] as $evidence){
        if(!is_string($evidence)||!preg_match('#^(?:tests|scripts|docs|deploy|content|src|public|config|\.github)/[A-Za-z0-9._/-]+$#D',$evidence)
            ||str_contains($evidence,'..')||!is_file($root.'/'.$evidence))
            throw new RuntimeException('Missing real evidence path for '.$id);
    }
}
sort($ids);
if($ids!==$expected)throw new RuntimeException('M00–M17 inventory incomplete');
if(($data['public_launch_ready']??true)!==false||($data['hostinger_verified']??true)!==false)
    throw new RuntimeException('Unverified hosting may not be called ready');
echo 'PASS mandatory Source-of-Truth module register: 18/18 IDs, schema and evidence paths'."\n";
