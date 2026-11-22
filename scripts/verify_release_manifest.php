<?php
declare(strict_types=1);
// Read-only check of the complete distributed source snapshot, excluding this manifest itself.
if(PHP_SAPI!=='cli')exit(2);
$root=dirname(__DIR__);
$data=json_decode((string)file_get_contents($root.'/RELEASE_MANIFEST.json'),true);
if(!is_array($data)||!is_array($data['files']??null)){
    fwrite(STDERR,"Release manifest missing or invalid.\n");exit(2);
}
$seen=[];
try{
    foreach($data['files'] as $record){
        $path=$record['path']??null;
        if(!is_string($path)||strlen($path)>400||preg_match('#^(?:/|\.\.|.*(?:^|/)\.\.(?:/|$))#',$path)
         ||str_contains($path,"\0")||isset($seen[$path]))throw new RuntimeException('Unsafe manifest entry');
        $seen[$path]=true;
        $file=$root.'/'.$path;
        if(is_link($file)||!is_file($file)||filesize($file)!==($record['bytes']??null)
           ||hash_file('sha256',$file)!==($record['sha256']??null))throw new RuntimeException('Manifest mismatch: '.$path);
    }
    if(count($seen)!==($data['file_count_excluding_manifest']??null))throw new RuntimeException('Manifest file count mismatch');
    echo 'PASS release SHA-256 manifest: '.count($seen).' source files checked (manifest excluded)' . "\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(2);}
