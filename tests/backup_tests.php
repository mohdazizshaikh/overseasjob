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
 // TEST-ONLY private row, never considered real 14-day source evidence.
 $sample='{"test_only":true,"role":"it_support","not_real_provider_data":true}'."\n";
 file_put_contents($root.'/role_coverage.ndjson',$sample);
 file_put_contents($root.'/report_rate.json','{"hour":"2026112300","total":1,"per_client":{}}');
 file_put_contents($root.'/analytics_daily.json','{"2026-11-23":{"page_home":1}}');
 $extra=Backups::create($root);
 okBackup('Private moderation and analytics state included in file snapshots',
    count(array_filter($extra,static fn($m)=>in_array($m['source'],['report_rate.json','analytics_daily.json'],true)))===2);
 okBackup('Isolated recovery verifies newly added private state',
    count(array_intersect(['report_rate.json','analytics_daily.json'],Backups::isolatedRestoreDrill($root)))===2);
 $roleFiles=array_values(array_filter($extra,static fn($m)=>($m['source']??'')==='role_coverage.ndjson'));
 okBackup('M17 private role-cohort evidence log snapshotted with exact digest',
    count($roleFiles)===1 && $roleFiles[0]['sha256']===hash('sha256',$sample)
    && is_file($root.'/backups/'.$roleFiles[0]['file']));
 okBackup('M17 private role-cohort log preserved by isolated restore',
    in_array('role_coverage.ndjson',Backups::isolatedRestoreDrill($root),true));
 Backups::create($root);Backups::create($root);
 okBackup('Only two most recent restore points retained',count(glob($root.'/backups/jobs.json.*.bak'))===2);
 // Security regression: never follow a backup directory pointing elsewhere.
 $backups=$root.'/backups';
 $offsite=$root.'/isolated-backups-original';
 if(!rename($backups,$offsite))throw new RuntimeException('Failed test directory setup');
 symlink($offsite,$backups);
 $deniedCreate=false;$deniedRestore=false;
 try{Backups::create($root);}catch(RuntimeException){$deniedCreate=true;}
 try{Backups::isolatedRestoreDrill($root);}catch(RuntimeException){$deniedRestore=true;}
 okBackup('Symlinked backups directory rejected for snapshot creation',$deniedCreate);
 okBackup('Symlinked backups directory rejected for restore',$deniedRestore);
 unlink($backups);rename($offsite,$backups);
 // The storage root itself may not be a symlink.
 symlink($root,$root.'/linked-root');
 $deniedRoot=false;
 try{Backups::create($root.'/linked-root');}catch(RuntimeException){$deniedRoot=true;}
 okBackup('Symlinked storage root rejected before backup writes',$deniedRoot);
 unlink($root.'/linked-root');
 // Reject a symlinked candidate source, even if it points to a regular file.
 rename($root.'/jobs.json',$root.'/original-jobs.json');
 symlink('original-jobs.json',$root.'/jobs.json');
 $deniedSource=false;
 try{Backups::create($root);}catch(RuntimeException){$deniedSource=true;}
 okBackup('Symlinked source input rejected instead of copied',$deniedSource);
 unlink($root.'/jobs.json');rename($root.'/original-jobs.json',$root.'/jobs.json');
 rename($root.'/role_coverage.ndjson',$root.'/original-role-evidence.ndjson');
 symlink('original-role-evidence.ndjson',$root.'/role_coverage.ndjson');
 $deniedRole=false;try{Backups::create($root);}catch(RuntimeException){$deniedRole=true;}
 okBackup('Symlinked M17 role-cohort evidence refused before backup',$deniedRole);
 unlink($root.'/role_coverage.ndjson');rename($root.'/original-role-evidence.ndjson',$root.'/role_coverage.ndjson');
 // Most recent backup's JSON can also be substituted with a symlink.
 $candidates=glob($backups.'/jobs.json.*.bak');rsort($candidates);
 $metafile=$candidates[0].'.json';
 rename($metafile,$metafile.'.saved');
 symlink(basename($metafile).'.saved',$metafile);
 $deniedMeta=false;
 try{Backups::isolatedRestoreDrill($root);}catch(RuntimeException){$deniedMeta=true;}
 okBackup('Symlinked backup checksum metadata rejected',$deniedMeta);
 unlink($metafile);rename($metafile.'.saved',$metafile);
 okBackup('Untampered snapshots still restore safely after symlink refusals',
    in_array('jobs.json',Backups::isolatedRestoreDrill($root),true));
 okBackup('Private backup directory mode remains 0700',
    (fileperms($backups)&0777)===0700);
 okBackup('Checksum metadata mode remains 0600',
    (fileperms($metafile)&0777)===0600);
 $latest=glob($root.'/backups/jobs.json.*.bak');rsort($latest);file_put_contents($latest[0],'tampered backup');
 $detected=false;try{Backups::isolatedRestoreDrill($root);}catch(RuntimeException){$detected=true;}
 okBackup('Backup tampering causes restore failure',$detected);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}finally{
 foreach(glob($root.'/backups/*')?:[] as $f)unlink($f);if(is_dir($root.'/backups'))rmdir($root.'/backups');
 foreach(glob($root.'/*')?:[] as $f)unlink($f);rmdir($root);
}
echo "\n$passed backup and restore checks passed.\n";
