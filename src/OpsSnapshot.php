<?php
declare(strict_types=1);
require_once __DIR__.'/Jobs.php';
require_once __DIR__.'/Reports.php';
/** Private, read-only operational snapshot. Never contains report bodies or applicant data. */
final class OpsSnapshot
{
    public static function collect(string $root,int $now):array
    {
        $storage=$root.'/storage';$warnings=[];$state='absent';$doc=null;
        $path=$storage.'/jobs.json';
        if(is_link($path)){$state='invalid';$warnings[]='Jobs cache symlink refused';}
        elseif(is_file($path)){
            if(filesize($path)>16_000_000){$state='invalid';$warnings[]='Jobs cache exceeds size limit';}
            else{$doc=json_decode((string)file_get_contents($path),true);
                $state=is_array($doc)&&is_array($doc['jobs']??null)?'present':'invalid';
                if($state==='invalid'){$doc=null;$warnings[]='Jobs cache invalid';}
            }
        }
        $jobs=$state==='present'?$doc['jobs']:[];$last=$doc['synced_at']??null;
        $lastAt=is_string($last)?strtotime($last):false;
        if($state==='absent')$warnings[]='No live provider sync cache yet';
        elseif(!$lastAt)$warnings[]='Provider sync timestamp unavailable';
        elseif($lastAt>$now+300||$lastAt<$now-72*3600)$warnings[]='Provider sync is outside expected freshness window';
        $visible=$state==='present'?count(Jobs::listPublic($path)):0;
        $pending=null;$held=null;
        try{
            $pending=count(array_filter(Reports::read($storage),
                static fn($r)=>($r['state']??'')==='pending_review'));
            $held=count(Reports::quarantine($storage));
            if($pending>30)$warnings[]='Moderation pending queue requires owner attention';
        }catch(Throwable){$warnings[]='Moderation store unavailable';}
        $backupDir=$storage.'/backups';
        $backupFiles=is_dir($backupDir)?(glob($backupDir.'/*.bak')?:[]):[];
        if(!$backupFiles)$warnings[]='No local backup files found (not proof of host-level backups)';
        $latestBackup=null;
        foreach($backupFiles as $f){if(is_link($f))continue;$mtime=filemtime($f);if($mtime!==false&&($latestBackup===null||$mtime>$latestBackup))$latestBackup=$mtime;}
        if($latestBackup!==null && $latestBackup < $now-72*3600)
            $warnings[]='Last local file backup is older than 72 hours';
        $coverage=$storage.'/coverage.ndjson';$days=[];
        if(is_file($coverage)&&!is_link($coverage)&&filesize($coverage)<2_000_000){
            $handle=fopen($coverage,'rb');
            if($handle){
                $start=gmdate('Y-m-d',$now-13*86400);$end=gmdate('Y-m-d',$now);
                while(($line=fgets($handle))!==false){
                    if(strlen($line)>30_000)continue;$row=json_decode($line,true);
                    $day=$row['date']??null;
                    if(is_string($day)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)
                      &&$day>=$start&&$day<=$end&&in_array($row['provider']??null,['jobicy','himalayas'],true))$days[$day]=true;
                }fclose($handle);
            }
        }
        $result=['at'=>gmdate('c',$now),'cache_state'=>$state,'cache_total'=>count($jobs),
            'visible'=>$visible,'last_sync'=>$last,'pending_reports'=>$pending,'quarantined_jobs'=>$held,
            'actual_sample_days'=>count($days),'14_day_sample_complete'=>count($days)===14,
            'local_backup_files'=>count($backupFiles),'last_local_backup_at'=>$latestBackup?gmdate('c',$latestBackup):null,
            'external_hosting_verified'=>false,'real_inbox_delivery_verified'=>false,'host_backup_restore_verified'=>false,
            'warnings'=>$warnings];
        return $result;
    }
}
