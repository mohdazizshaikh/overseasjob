<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Reports.php';
$storage=dirname(__DIR__).'/storage';
$action=$argv[1]??'list';
try{
 if($action==='list'){
   $rows=Reports::read($storage);$pending=array_values(array_filter($rows,fn($r)=>($r['state']??'')==='pending_review'));
   echo json_encode(['pending_count'=>count($pending),'pending'=>$pending],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
 } elseif(in_array($action,['quarantine','resolve','dismiss'],true)){
   if(!isset($argv[2],$argv[3]))throw new InvalidArgumentException('Usage: php scripts/review_reports.php '.$action.' REPORT_ID "reason"');
   Reports::decide($storage,$argv[2],$action,$argv[3]);echo "Moderation decision recorded.\n";
 } elseif($action==='reopen'){
   if(!isset($argv[2],$argv[3]))throw new InvalidArgumentException('Usage: php scripts/review_reports.php reopen JOB_ID "reason"');
   Reports::reopen($storage,$argv[2],$argv[3]);echo "Job unquarantined and audit recorded.\n";
 }else throw new InvalidArgumentException('Unknown command: list|quarantine|resolve|dismiss|reopen');
}catch(Throwable $e){fwrite(STDERR,'Review failed: '.$e->getMessage()."\n");exit(1);}
