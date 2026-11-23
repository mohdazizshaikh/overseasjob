<?php
declare(strict_types=1);
/**
 * M17: evidence-shape validation for UTC observations from a completed live sync.
 * Correct shape does NOT independently prove upstream responses were genuine.
 * Never turn fixture, legacy, invalid, future, or partial-metadata rows into 14-day proof.
 */
final class SourceCoverage
{
    private const MAX_BYTES=2_000_000;
    private const MAX_LINE=30_000;
    private const PROVIDERS=['jobicy','himalayas'];
    private const ELIGIBILITY=['allowed','unknown','not_allowed'];

    /** Called AFTER the source cache has been atomically published in live CLI sync only. */
    public static function newLiveObservation(string $provider,array $jobs,int $invalid,
        bool $partial,string $policyDate,int $now):array
    {
        if(!in_array($provider,self::PROVIDERS,true) || !$jobs || count($jobs)>10_000
            || $invalid<0 || $invalid>100_000 || !self::validPolicyDate($policyDate,gmdate('Y-m-d',$now)))
            throw new InvalidArgumentException('Invalid successful provider observation');
        $counts=['allowed'=>0,'unknown'=>0,'not_allowed'=>0];
        foreach($jobs as $job){
            if(!is_array($job) || !in_array($job['eligible_from_in']??null,self::ELIGIBILITY,true))
                throw new InvalidArgumentException('Invalid eligibility in provider sample');
            $counts[$job['eligible_from_in']]++;
        }
        return [
            'schema_version'=>2,
            'origin'=>'live_provider_sync',
            'cache_published'=>true,
            'from_fixture'=>false,
            'run_id'=>bin2hex(random_bytes(12)),
            'at'=>gmdate('Y-m-d\TH:i:s\Z',$now),
            'date'=>gmdate('Y-m-d',$now),
            'provider'=>$provider,
            'source_policy_checked_at'=>$policyDate,
            'valid'=>count($jobs),
            'invalid'=>$invalid,
            'india_allowed'=>$counts['allowed'],
            'india_unknown'=>$counts['unknown'],
            'india_not_allowed'=>$counts['not_allowed'],
            'partial_provider_sample'=>$partial
        ];
    }

    private static function validPolicyDate(string $value,string $asOf):bool
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value) || $value>$asOf)return false;
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        $dateOK=$d!==false&&$d->format('Y-m-d')===$value;
        $days=$dateOK?(int)$d->diff(new DateTimeImmutable($asOf,new DateTimeZone('UTC')))->format('%r%a'):9999;
        return $dateOK&&$days>=0&&$days<=30;
    }

    public static function validRow(mixed $raw,int $now):bool
    {
        if(!is_array($raw) || ($raw['schema_version']??null)!==2
          || ($raw['origin']??null)!=='live_provider_sync'
          || ($raw['cache_published']??null)!==true
          || ($raw['from_fixture']??null)!==false
          || !in_array($raw['provider']??null,self::PROVIDERS,true)
          || !is_string($raw['run_id']??null)
          || !preg_match('/^[a-f0-9]{24}$/D',$raw['run_id'])
          || !is_string($raw['date']??null)
          || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$raw['date'])
          || !is_string($raw['at']??null)
          || !preg_match('/^\d{4}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$raw['at'])
          || !is_bool($raw['partial_provider_sample']??null))
            return false;
        $time=strtotime($raw['at']);
        if($time===false || gmdate('Y-m-d\TH:i:s\Z',$time)!==$raw['at']
          || gmdate('Y-m-d',$time)!==$raw['date'] || $time>$now+300
          || $raw['date']<gmdate('Y-m-d',$now-13*86400)
          || $raw['date']>gmdate('Y-m-d',$now))return false;
        if(!is_string($raw['source_policy_checked_at']??null)
          || !self::validPolicyDate($raw['source_policy_checked_at'],$raw['date']))return false;
        foreach(['valid','invalid','india_allowed','india_unknown','india_not_allowed'] as $key){
            if(!is_int($raw[$key]??null)||$raw[$key]<0||$raw[$key]>100_000)return false;
        }
        return $raw['valid']>=1 && $raw['valid']<=10_000
            && ($raw['india_allowed']+$raw['india_unknown']+$raw['india_not_allowed'])===$raw['valid'];
    }

    /** @return array<string,mixed> */
    public static function summarize(string $path,int $now):array
    {
        $end=gmdate('Y-m-d',$now);$start=gmdate('Y-m-d',$now-13*86400);
        $byDate=[];$byProvider=[];$daily=[];$validated=0;$ignored=0;$recordsRead=0;
        $logValid=true;$seenRunIds=[];
        if(is_link($path) || (is_file($path)&&filesize($path)>self::MAX_BYTES))$logValid=false;
        elseif(is_file($path)){
            $fp=@fopen($path,'rb');
            if(!$fp)$logValid=false;
            else{
                try{
                    while(($line=fgets($fp,self::MAX_LINE+2))!==false){
                        $recordsRead++;
                        if($recordsRead>20_000 || strlen($line)>self::MAX_LINE
                            || ($line!=='' && !str_ends_with($line,"\n"))){$logValid=false;break;}
                        $r=json_decode($line,true,32);
                        if(!self::validRow($r,$now) || isset($seenRunIds[$r['run_id']??''])){
                            $ignored++;continue;
                        }
                        $seenRunIds[$r['run_id']]=true;$validated++;
                        $key=$r['provider'].':'.$r['date'];
                        // Latest successful observation for a provider on a UTC day wins.
                        if(!isset($daily[$key]) || strcmp($r['at'],$daily[$key]['at'])>0)$daily[$key]=$r;
                    }
                }finally{fclose($fp);}
            }
        }
        if($logValid){
            foreach($daily as $r){
                $byDate[$r['date']]=true;
                $byProvider[$r['provider']][$r['date']]=true;
            }
        }else{$byDate=[];$byProvider=[];$daily=[];$validated=0;}
        $days=[];foreach(self::PROVIDERS as $p)$days[$p]=count($byProvider[$p]??[]);
        $eligibility=['allowed'=>0,'unknown'=>0,'not_allowed'=>0];$rows=0;$partialDays=0;
        foreach($daily as $r){
            $rows+=$r['valid'];
            $eligibility['allowed']+=$r['india_allowed'];
            $eligibility['unknown']+=$r['india_unknown'];
            $eligibility['not_allowed']+=$r['india_not_allowed'];
            if($r['partial_provider_sample'])$partialDays++;
        }
        return [
            'as_of'=>$end,'window_start'=>$start,
            'distinct_actual_days'=>count($byDate),
            'days_by_provider'=>$days,
            'actual_sync_observations'=>$validated,
            'unverified_legacy_or_invalid_records'=>$ignored,
            'log_valid'=>$logValid,
            '14_distinct_calendar_days_measured'=>$logValid&&count($byDate)===14,
            'observed_job_rows_from_daily_snapshots'=>$rows,
            'observed_india_eligibility_rows'=>$eligibility,
            'partial_provider_day_samples'=>$partialDays,
            'live_upstream_source_independently_verified'=>false,
            'hostinger_14_day_run_verified'=>false,
            'note'=>'V2 contract-valid log entries are locally observed sync reports, NOT independent proof of authorised source responses or a complete worldwide job inventory. Each provider/day contributes only its latest sample. Legacy/incomplete rows never count.'
        ];
    }
}
