<?php
/** Real observed source runs only, never invented day-by-day historical coverage. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit(1);}
$base=dirname(__DIR__);
$file=$base.'/storage/coverage.ndjson';
$now=new DateTimeImmutable('today',new DateTimeZone('UTC'));
$start=$now->modify('-13 days')->format('Y-m-d');
$end=$now->format('Y-m-d');
$days=[];$providers=[];$samples=0;
if(is_file($file)){
    $fp=fopen($file,'rb');
    if(!$fp)throw new RuntimeException('Cannot read coverage');
    while(($line=fgets($fp))!==false){
        if(strlen($line)>30000)continue;
        $record=json_decode($line,true);
        if(!is_array($record))continue;
        $date=$record['date']??'';
        if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$date<$start||$date>$end)continue;
        if(!in_array($record['provider']??null,['jobicy','himalayas'],true))continue;
        $name=$record['provider'];
        $days[$date]=true;$providers[$name][$date]=true;$samples++;
    }
    fclose($fp);
}
$counts=[];foreach($providers as $k=>$v)$counts[$k]=count($v);
echo json_encode(['as_of'=>$end,'window_start'=>$start,'distinct_actual_days'=>count($days),
    'days_by_provider'=>$counts,'actual_sync_observations'=>$samples,
    '14_distinct_calendar_days_measured'=>count($days)===14,
    'note'=>'This only measures recorded observations, not full job coverage or real user reach.'],
    JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
