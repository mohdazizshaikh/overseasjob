<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/ProviderHttp.php';
$count=0;
function checkTransport(string $label,bool $ok):void {
 global $count;
 if(!$ok)throw new RuntimeException("FAIL $label");
 $count++;echo "PASS $label\n";
}
$now=strtotime('2026-11-23T00:00:00Z');
checkTransport('429 HTTP-date delay respects upstream schedule',
 ProviderHttp::retryDelay(429,['HTTP/1.1 429','Retry-After: Mon, 23 Nov 2026 00:00:04 GMT'],0,0,$now)===4);
checkTransport('Too distant HTTP date defers to a later scheduled run',
 ProviderHttp::retryDelay(429,['Retry-After: Mon, 23 Nov 2026 00:02:00 GMT'],0,0,$now)===null);
checkTransport('Retry budget exhausted after two prior waits',
 ProviderHttp::retryDelay(503,[],1,12)===null);
$codes=[];$delays=[];
$transport=static function(string $url,int $maxBytes)use(&$codes):array {
 $status=count($codes)===0?503:200;$codes[]=$status;
 return ['status'=>$status,'headers'=>[],'body'=>'{"jobs":[{"id":1}]}'];
};
$sleep=static function(int $wait)use(&$delays):void{$delays[]=$wait;};
$jobs=ProviderHttp::fetchJson('https://jobicy.com/api/v2/remote-jobs?count=100',20000,$transport,$sleep);
checkTransport('503 then 200 completes with bounded wait',count($jobs['jobs'])===1&&$codes===[503,200]&&$delays===[1]);
$fail=false;try{ProviderHttp::fetchJson('https://himalayas.app.evil.tld/jobs/api',1000,$transport,$sleep);}
catch(InvalidArgumentException){$fail=true;}
checkTransport('Provider whitelist prevents suffix spoofing',$fail);
$fail=false;try{ProviderHttp::fetchJson('http://himalayas.app/jobs/api',1000,$transport,$sleep);}
catch(InvalidArgumentException){$fail=true;}
checkTransport('Provider client rejects non-HTTPS',$fail);
$fail=false;try{ProviderHttp::fetchJson('https://himalayas.app:8443/jobs/api',1000,$transport,$sleep);}
catch(InvalidArgumentException){$fail=true;}
checkTransport('Provider client rejects port changes',$fail);
$notFound=static fn(string $url,int $bytes):array=>['status'=>404,'headers'=>[],'body'=>''];
$fail=false;try{ProviderHttp::fetchJson('https://himalayas.app/jobs/api',1000,$notFound,$sleep);}
catch(RuntimeException){$fail=true;}
checkTransport('Main job feed 404 fails without claiming closure',$fail);
$legacyCalled=false;$legacyDenied=false;
try{ProviderHttp::request('https://himalayas.app/jobs/api/companies/sample/jobs/test',1000,
 static function(string $url,int $bytes)use(&$legacyCalled):array{
     $legacyCalled=true;return ['status'=>410,'headers'=>[],'body'=>''];
 });}catch(InvalidArgumentException){$legacyDenied=true;}
checkTransport('Undocumented Himalayas per-job 410 endpoint blocked before network',
    $legacyDenied&&!$legacyCalled);
$listed=ProviderHttp::request('https://himalayas.app/jobs/api?limit=20&offset=40',1000,
 static fn(string $url,int $bytes):array=>['status'=>200,'headers'=>[],'body'=>'{"jobs":[]}']);
checkTransport('Documented Himalayas browse pagination remains permitted',$listed['status']===200);
$wrongQuery=false;
try{ProviderHttp::request('https://himalayas.app/jobs/api?limit=20&offset=0&unsafe=1',1000,
 static fn(string $url,int $bytes):array=>['status'=>200,'headers'=>[],'body'=>'{}']);}
catch(InvalidArgumentException){$wrongQuery=true;}
checkTransport('Provider browse rejects unapproved extra query parameters',$wrongQuery);
$nonFeed=false;
try{ProviderHttp::request('https://jobicy.com/private/secret',1000,
 static fn(string $url,int $bytes):array=>['status'=>200,'headers'=>[],'body'=>'{}']);}
catch(InvalidArgumentException){$nonFeed=true;}
checkTransport('Approved Jobicy host cannot be reused for arbitrary endpoints',$nonFeed);
$bad=static fn(string $url,int $bytes):array=>['status'=>200,'headers'=>[],'body'=>str_repeat('X',1001)];
$fail=false;try{ProviderHttp::fetchJson('https://himalayas.app/jobs/api',1000,$bad,$sleep);}
catch(RuntimeException){$fail=true;}
checkTransport('Provider transport rejects oversized responses',$fail);
$attempts=0;$connectionWaits=[];
$network=static function(string $url,int $maxBytes)use(&$attempts):array{
    $attempts++;
    if($attempts===1)throw new RuntimeException('Simulated connection drop');
    return ['status'=>200,'headers'=>[],'body'=>'{"jobs":[]}'];
};
$wait=static function(int $seconds)use(&$connectionWaits):void{$connectionWaits[]=$seconds;};
$recovered=ProviderHttp::fetchJson('https://jobicy.com/api/v2/remote-jobs?count=100',5000,$network,$wait);
checkTransport('Bounded retry recovers from one transient network failure',
    $attempts===2&&$connectionWaits===[1]&&isset($recovered['jobs']));
echo "\n$count provider HTTP isolation checks passed.\n";
