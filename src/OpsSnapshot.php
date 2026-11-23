<?php
declare(strict_types=1);
require_once __DIR__.'/Jobs.php';
require_once __DIR__.'/Reports.php';
require_once __DIR__.'/SourceCoverage.php';
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
        $coverage=SourceCoverage::summarize($storage.'/coverage.ndjson',$now);
        if(!$coverage['log_valid'])$warnings[]='Private source coverage audit log invalid; no days accepted';
        elseif($coverage['unverified_legacy_or_invalid_records']>0)
            $warnings[]='Source coverage contains legacy or invalid rows not counted as evidence';
        $result=['at'=>gmdate('c',$now),'cache_state'=>$state,'cache_total'=>count($jobs),
            'visible'=>$visible,'last_sync'=>$last,'pending_reports'=>$pending,'quarantined_jobs'=>$held,
            'actual_sample_days'=>$coverage['distinct_actual_days'],
            '14_day_sample_complete'=>$coverage['14_distinct_calendar_days_measured'],
            'source_sample_log_contract_valid'=>$coverage['log_valid'],
            'unverified_coverage_rows'=>$coverage['unverified_legacy_or_invalid_records'],
            'live_provider_samples_independently_verified'=>false,
            'local_backup_files'=>count($backupFiles),'last_local_backup_at'=>$latestBackup?gmdate('c',$latestBackup):null,
            'external_hosting_verified'=>false,'real_inbox_delivery_verified'=>false,'host_backup_restore_verified'=>false,
            'warnings'=>$warnings];
        return $result;
    }
}
