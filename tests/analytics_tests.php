<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Analytics.php';
$n=0;
function checkA(string $name,bool $ok):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $name\n");exit(1);}echo "PASS $name\n";$n++;}
function rejects(callable $f):bool{try{$f();return false;}catch(Throwable){return true;}}
$dir=sys_get_temp_dir().'/oj-analytics-'.bin2hex(random_bytes(5));mkdir($dir,0700);
checkA('Anonymous events allowlisted',Analytics::validEvent('tool_open'));
checkA('Unknown analytics event rejected',!Analytics::validEvent('candidate_email'));
checkA('Structured tracker payload refused',!Analytics::validEvent(['event'=>'tool_open']));
checkA('Missing analytics record starts empty',Analytics::read($dir)===[]);
Analytics::record($dir,'page_home','2026-11-22');
checkA('First anonymous counter succeeds',Analytics::read($dir)['2026-11-22']['page_home']===1);
Analytics::record($dir,'page_home','2026-11-22');
checkA('Repeated requests aggregate only',Analytics::read($dir)['2026-11-22']['page_home']===2);
Analytics::record($dir,'tool_open','2026-11-22');
checkA('Event categories independent',Analytics::read($dir)['2026-11-22']['tool_open']===1);
checkA('No IP or agent stored',!str_contains((string)file_get_contents($dir.'/analytics_daily.json'),'127.0.0.1')&&!str_contains((string)file_get_contents($dir.'/analytics_daily.json'),'User-Agent'));
checkA('Only date+event object stored',array_keys(Analytics::read($dir)['2026-11-22'])===['page_home','tool_open']);
checkA('Private analytics file permissions',((fileperms($dir.'/analytics_daily.json')&0777)===0600));
checkA('Analytics rejects personal event name',rejects(fn()=>Analytics::record($dir,'user_login_email','2026-11-22')));
checkA('Analytics rejects malformed day',rejects(fn()=>Analytics::record($dir,'page_home','2026-02-30')));
checkA('Analytics rejects invalid month',rejects(fn()=>Analytics::record($dir,'page_home','2026-13-01')));
checkA('Analytics rejects malformed row storage', (static function()use($dir){file_put_contents($dir.'/analytics_daily.json','["oops"]');return rejects(fn()=>Analytics::read($dir));})());
unlink($dir.'/analytics_daily.json');
Analytics::record($dir,'outbound_apply','2026-11-23');
checkA('A later day has its own counter',Analytics::read($dir)['2026-11-23']['outbound_apply']===1);
checkA('No cookies or personal identifiers in analytics JSON',!preg_match('/(cookie|ip_address|user_id|email|fingerprint)/i',(string)file_get_contents($dir.'/analytics_daily.json')));
foreach(glob($dir.'/*')?:[] as $f)unlink($f);rmdir($dir);
echo "\n$n anonymous metrics PHP checks passed.\n";
