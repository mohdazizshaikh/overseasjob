<?php
declare(strict_types=1);
require_once __DIR__.'/Jobs.php';
/**
 * M17: honest 2–3-role feasibility sampling, logged ONLY after authorised live sync.
 * This is a LOCAL evidence-shape/accounting check, not proof of upstream licensing,
 * complete country eligibility, real employer demand, or host continuity.
 */
final class RoleCoverage
{
    private const MAX_BYTES=2_000_000;
    private const MAX_LINE=30000;
    private const SOURCE_NAMES=['jobicy','himalayas'];
    private const ELIGIBILITY=['allowed','unknown','not_allowed'];
    private const COHORTS=[
        'it_support'=>['it support','help desk','helpdesk','service desk','technical support'],
        'customer_support'=>['customer support','customer service','customer care','client support'],
        'junior_web_developer'=>['junior web developer','junior frontend','junior front end','junior front-end','web developer'],
    ];
    public static function cohorts():array{return array_keys(self::COHORTS);}
    private static function policyDate(string $value,string $day):bool
    {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)||$value>$day)return false;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        if($date===false||$date->format('Y-m-d')!==$value)return false;
        $today=DateTimeImmutable::createFromFormat('!Y-m-d',$day,new DateTimeZone('UTC'));
        return $today!==false && (int)$date->diff($today)->format('%r%a')<=30;
    }
    /** A title can match multiple cohorts, so summed cohort counts are NOT global unique. */
    private static function roleMatch(string $title,array $terms):bool
    {
        $title=function_exists('mb_strtolower')?mb_strtolower($title,'UTF-8'):strtolower($title);
        foreach($terms as $term)if(str_contains($title,$term))return true;
        return false;
    }
    public static function newLiveObservation(string $provider,array $jobs,int $fetched,
        int $invalid,int $duplicates,int $invalidApplyUrls,bool $partial,
        bool $fullyPaginated,string $policyDate,int $now):array
    {
        $date=gmdate('Y-m-d',$now);$unique=count($jobs);
        if(!in_array($provider,self::SOURCE_NAMES,true)||$unique<1||$unique>10000
            ||$fetched!==$unique+$invalid+$duplicates||$fetched>100000
            ||$invalid<0||$duplicates<0||$invalidApplyUrls<0||$invalidApplyUrls>$fetched
            ||!self::policyDate($policyDate,$date)
            ||($provider==='jobicy' && (!$partial||$fullyPaginated))
            ||($provider==='himalayas' && ($partial||!$fullyPaginated)))
            throw new InvalidArgumentException('Invalid live cohort observation inputs');
        $cohorts=[];
        foreach(self::COHORTS as $id=>$terms)$cohorts[$id]=[
            'relevant'=>0,'india_allowed'=>0,'india_unknown'=>0,
            'india_not_allowed'=>0,'safe_apply_https'=>0,'expired_by_source_date'=>0
        ];
        $seenIds=[];
        foreach($jobs as $j){
            if(!is_array($j)||!is_string($j['title']??null)
                ||!in_array($j['eligible_from_in']??null,self::ELIGIBILITY,true)
                ||!is_string($j['id']??null)||$j['id']===''
                ||isset($seenIds[$j['id']])
                ||Jobs::safeUrl($j['apply_url']??null)===null)
                throw new InvalidArgumentException('Invalid normalised job for cohort sample');
            $seenIds[$j['id']]=true;
            foreach(self::COHORTS as $id=>$terms){
                if(!self::roleMatch($j['title'],$terms))continue;
                $cohorts[$id]['relevant']++;
                $cohorts[$id]['india_'.$j['eligible_from_in']]++;
                $cohorts[$id]['safe_apply_https']++;
                $expiry=$j['valid_through']??null;
                if(is_string($expiry)&&($ts=strtotime($expiry))!==false&&$ts<=$now)
                    $cohorts[$id]['expired_by_source_date']++;
            }
        }
        return [
            'schema_version'=>1,'origin'=>'authorised_live_provider_sync',
            'from_fixture'=>false,'cache_published'=>true,
            'run_id'=>bin2hex(random_bytes(12)),
            'at'=>gmdate('Y-m-d\TH:i:s\Z',$now),'date'=>$date,
            'provider'=>$provider,'source_policy_checked_at'=>$policyDate,
            'cohort_definition'=>'starter_three_2026_11',
            'fetched_total'=>$fetched,'unique_valid'=>$unique,'invalid_rows'=>$invalid,
            'duplicate_rows'=>$duplicates,'raw_apply_url_failures'=>$invalidApplyUrls,
            'partial_provider_sample'=>$partial,'fully_paginated'=>$fullyPaginated,
            'cohorts'=>$cohorts
        ];
    }
    public static function validRow(mixed $r,int $now):bool
    {
        if(!is_array($r)||($r['schema_version']??null)!==1
            ||($r['origin']??null)!=='authorised_live_provider_sync'
            ||($r['from_fixture']??null)!==false||($r['cache_published']??null)!==true
            ||!in_array($r['provider']??null,self::SOURCE_NAMES,true)
            ||!is_string($r['run_id']??null)||!preg_match('/^[a-f0-9]{24}$/D',$r['run_id'])
            ||!is_string($r['at']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$r['at'])
            ||!is_string($r['date']??null)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$r['date'])
            ||($r['cohort_definition']??null)!=='starter_three_2026_11'
            ||!is_bool($r['partial_provider_sample']??null)
            ||!is_bool($r['fully_paginated']??null))return false;
        $at=strtotime($r['at']);
        if($at===false||gmdate('Y-m-d\TH:i:s\Z',$at)!==$r['at']
            ||gmdate('Y-m-d',$at)!==$r['date']||$at>$now+300
            ||$r['date']<gmdate('Y-m-d',$now-13*86400)
            ||$r['date']>gmdate('Y-m-d',$now)
            ||!self::policyDate((string)($r['source_policy_checked_at']??''),$r['date']))return false;
        if($r['provider']==='jobicy' && (!$r['partial_provider_sample']||$r['fully_paginated']))return false;
        if($r['provider']==='himalayas' && ($r['partial_provider_sample']||!$r['fully_paginated']))return false;
        foreach(['fetched_total','unique_valid','invalid_rows','duplicate_rows','raw_apply_url_failures'] as $field)
            if(!is_int($r[$field]??null)||$r[$field]<0||$r[$field]>100000)return false;
        if($r['unique_valid']<1||$r['unique_valid']>10000
            ||$r['fetched_total']!==$r['unique_valid']+$r['invalid_rows']+$r['duplicate_rows']
            ||$r['raw_apply_url_failures']>$r['fetched_total'])return false;
        if(!is_array($r['cohorts']??null)||array_keys($r['cohorts'])!==self::cohorts())return false;
        foreach($r['cohorts'] as $cohort){
            if(!is_array($cohort))return false;
            foreach(['relevant','india_allowed','india_unknown','india_not_allowed','safe_apply_https','expired_by_source_date'] as $key)
                if(!is_int($cohort[$key]??null)||$cohort[$key]<0||$cohort[$key]>$r['unique_valid'])return false;
            if($cohort['relevant']!==$cohort['india_allowed']+$cohort['india_unknown']+$cohort['india_not_allowed']
                ||$cohort['safe_apply_https']!==$cohort['relevant']
                ||$cohort['expired_by_source_date']>$cohort['relevant'])return false;
        }
        return true;
    }
    /** Review-only 14-day summary; the report may have zero relevant jobs. */
    public static function summarize(string $path,int $now):array
    {
        $days=[];$valid=0;$ignored=0;$rowsRead=0;$logValid=true;$seen=[];$latest=[];
        if(is_link($path)||(is_file($path)&&@filesize($path)>self::MAX_BYTES))$logValid=false;
        elseif(is_file($path)){
            $f=@fopen($path,'rb');if(!$f)$logValid=false;
            else{
                try{
                    while(($line=fgets($f,self::MAX_LINE+2))!==false){
                        if(strlen($line)>self::MAX_LINE||!str_ends_with($line,"\n")
                            ||++$rowsRead>20000){$logValid=false;break;}
                        $r=json_decode($line,true,32);
                        if(!self::validRow($r,$now)||isset($seen[$r['run_id']??''])){$ignored++;continue;}
                        $seen[$r['run_id']]=true;$valid++;
                        $key=$r['provider'].':'.$r['date'];
                        if(!isset($latest[$key])||strcmp($r['at'],$latest[$key]['at'])>0)$latest[$key]=$r;
                    }
                }finally{fclose($f);}
            }
        }
        if(!$logValid){$latest=[];$valid=0;}
        $roles=[];
        foreach(self::COHORTS as $id=>$terms)$roles[$id]=[
            'sampled_dates'=>0,'relevant_rows'=>0,'india_allowed'=>0,
            'india_unknown'=>0,'india_not_allowed'=>0,'safe_apply_https'=>0,'expired_by_source_date'=>0
        ];
        $sampledByProvider=[];$roleDateSets=[];$partialDays=0;$rawCount=0;$dupes=0;$invalidUrls=0;
        foreach($latest as $row){
            $days[$row['date']]=true;$sampledByProvider[$row['provider']][$row['date']]=true;
            $partialDays+=(int)$row['partial_provider_sample'];
            $rawCount+=$row['fetched_total'];$dupes+=$row['duplicate_rows'];$invalidUrls+=$row['raw_apply_url_failures'];
            foreach($row['cohorts'] as $id=>$item){
                $roleDateSets[$id][$row['date']]=true;
                foreach(['relevant','india_allowed','india_unknown','india_not_allowed','safe_apply_https','expired_by_source_date'] as $field){
                    $key=$field==='relevant'?'relevant_rows':$field;
                    $roles[$id][$key]+=$item[$field];
                }
            }
        }
        $count=count($days);$cohortComplete=0;
        foreach($roles as $id=>&$r){
            $r['sampled_dates']=count($roleDateSets[$id]??[]);
            if($r['sampled_dates']>=14)$cohortComplete++;
        }unset($r);
        return [
            'as_of'=>gmdate('Y-m-d',$now),'days_measured'=>$count,
            'cohorts_measured'=>array_keys($roles),'role_counts'=>$roles,
            'days_by_provider'=>array_map('count',$sampledByProvider),
            'successful_observation_rows'=>$valid,'ignored_invalid_rows'=>$ignored,
            'log_valid'=>$logValid,'total_fetched_daily_snapshots'=>$rawCount,
            'duplicate_rows_daily_snapshots'=>$dupes,'invalid_apply_urls_daily_snapshots'=>$invalidUrls,
            'partial_provider_day_samples'=>$partialDays,
            '14_days_for_2_to_3_role_cohorts_shape_valid'=>$logValid&&$count===14&&$cohortComplete>=2,
            'external_provider_permission_independently_verified'=>false,
            'original_hostinger_14_day_continuity_verified'=>false,
            'role_relevance_is_title_keyword_heuristic'=>true,
            'note'=>'Fixed three pilot role cohorts; zero matches remain zero. Data is locally logged after source sync, NOT independent proof of provider API results, legal rights, full coverage or host uptime.'
        ];
    }
}
