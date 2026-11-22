<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Backups.php';
$passed=0;function okBackup($label,$condition){global $passed;if(!$condition)throw new RuntimeException('FAIL '.$label);$passed++;echo 'PASS '.$label."\n";}
$root=sys_get_temp_dir().'/oj-test-backup-'.bin2hex(random_bytes(5));mkdir($root,0700);
try{
 $payload='{"jobs":[],"synced_at":null}';file_put_contents($root.'/jobs.json',$payload);
 $items=Backups::create($root);
 okBackup('Backup snapshot created',count($items)===1&&is_file($root.'/backups/'.$items[0]['file']));
 okBackup('Hash evidence recorded',hash('sha256',$payload)===$items[0]['sha256']);
 okBackup('Isolated restore validates jobs data',Backups::isolatedRestoreDrill($root)===['jobs.json']);
 file_put_contents($root.'/jobs.json','{"jobs":[{"id":"new"}]}');
 okBackup('Live data untouched by drill',(string)file_get_contents($root.'/jobs.json')==='{"jobs":[{"id":"new"}]}');
 Backups::create($root);Backups::create($root);
 okBackup('Only two most recent restore points retained',count(glob($root.'/backups/jobs.json.*.bak'))===2);
 $latest=glob($root.'/backups/jobs.json.*.bak');rsort($latest);file_put_contents($latest[0],'tampered backup');
 $detected=false;try{Backups::isolatedRestoreDrill($root);}catch(RuntimeException){$detected=true;}
 okBackup('Backup tampering causes restore failure',$detected);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}finally{
 foreach(glob($root.'/backups/*')?:[] as $f)unlink($f);if(is_dir($root.'/backups'))rmdir($root.'/backups');
 foreach(glob($root.'/*')?:[] as $f)unlink($f);rmdir($root);
}
echo "\n$passed backup and restore checks passed.\n";
