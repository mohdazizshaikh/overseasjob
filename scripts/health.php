<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Jobs.php';
require dirname(__DIR__).'/src/Reports.php';
$base=dirname(__DIR__);
$doc=json_decode((string)file_get_contents($base.'/storage/jobs.json'),true);
$valid=Jobs::listPublic($base.'/storage/jobs.json');
$coverageFile=$base.'/storage/coverage.ndjson';
$rows=is_file($coverageFile)?array_filter(explode("\n",trim((string)file_get_contents($coverageFile)))):[];
$dates=[];foreach($rows as $r){$x=json_decode($r,true);if(is_array($x))$dates[$x['date']??'']=true;}
echo json_encode(['at'=>gmdate('c'),'cache_total'=>count($doc['jobs']??[]), 'visible'=>count($valid),
 'last_sync'=>$doc['synced_at']??null,'source_stats'=>$doc['providers']??[],
 'pending_reports'=>count(array_filter(Reports::read($base.'/storage'),static fn($r)=>($r['state']??'')==='pending_review')),'quarantined_jobs'=>count(Reports::quarantine($base.'/storage')),
 'actual_sample_days'=>count($dates),'14_day_sample_complete'=>count($dates)>=14],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
