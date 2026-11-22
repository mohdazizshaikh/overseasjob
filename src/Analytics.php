<?php
declare(strict_types=1);
/** Privacy-consented aggregate usage counts. No IP, cookie, user-agent or per-user IDs. OFF by default. */
final class Analytics
{
    private const EVENTS=['page_home','page_jobs','tool_open','guide_open','job_card_open','outbound_apply'];
    public static function validEvent(mixed $event):bool
    {
        return is_string($event) && in_array($event,self::EVENTS,true);
    }
    public static function record(string $privateStorage,string $event, ?string $day=null):void
    {
        if(!self::validEvent($event))throw new InvalidArgumentException('Unrecognised anonymous event');
        $day??=gmdate('Y-m-d');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$day)||!checkdate((int)substr($day,5,2),(int)substr($day,8,2),(int)substr($day,0,4)))throw new InvalidArgumentException('Invalid analytics date');
        if(!is_dir($privateStorage))throw new RuntimeException('Private analytics directory unavailable');
        $lock=fopen($privateStorage.'/analytics.lock','c');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Analytics storage unavailable');
        try{
            $target=$privateStorage.'/analytics_daily.json';
            $raw=is_file($target)?file_get_contents($target):'{}';
            if(!is_string($raw)||strlen($raw)>30000)throw new RuntimeException('Analytics data over limit');
            $rows=json_decode($raw,true);
            if(!is_array($rows)||($rows!==[]&&array_is_list($rows)))throw new RuntimeException('Invalid analytics data');
            $max=gmdate('Y-m-d',strtotime($day.' 00:00:00 UTC')-35*86400);
            $rows=array_filter($rows,static fn($k)=>is_string($k)&&$k>=$max&&$k<=$day,ARRAY_FILTER_USE_KEY);
            $today=$rows[$day]??[];
            if(!is_array($today))throw new RuntimeException('Analytics day invalid');
            $total=array_sum($today);
            if($total>=50000)throw new RuntimeException('Anonymous daily cap reached');
            $today[$event]=min(50000,(int)($today[$event]??0)+1);
            $rows[$day]=$today;
            $encoded=json_encode($rows,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $tmp=tempnam($privateStorage,'count-');
            if($tmp===false)throw new RuntimeException('Cannot write analytics');
            try{
                if(file_put_contents($tmp,$encoded,LOCK_EX)===false)throw new RuntimeException('Analytics write failed');
                chmod($tmp,0600);if(!rename($tmp,$target))throw new RuntimeException('Analytics swap failed');
            }finally{if(is_file($tmp))unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function read(string $privateStorage):array
    {
        $file=$privateStorage.'/analytics_daily.json';
        if(!is_file($file))return [];
        $contents=file_get_contents($file);
        if(!is_string($contents)||strlen($contents)>30000)throw new RuntimeException('Analytics unavailable');
        $decoded=json_decode($contents,true);
        if(!is_array($decoded)||($decoded!==[]&&array_is_list($decoded)))throw new RuntimeException('Analytics corrupt');
        return $decoded;
    }
}
