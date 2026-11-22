<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/RecruiterMailer.php';
require dirname(__DIR__).'/src/ThemeSafety.php';
$n=0;
function rcheck(bool $ok,string $what):void{global $n;if(!$ok)throw new RuntimeException('FAIL '.$what);$n++;echo 'PASS '.$what."\n";}
function rdenies(callable $fn):bool{try{$fn();return false;}catch(Throwable){return true;}}
$secret=str_repeat('q',48);$code=RecruiterPortal::newInviteCode();
rcheck(preg_match('/^[0-9]{8}$/',$code)===1,'Password recovery issues 8-digit code');
rcheck(RecruiterPortal::resetCodeHash(7,$code,$secret)!==RecruiterPortal::codeHash(7,$code,$secret),'Reset code and invite code use separate HMAC purposes');
rcheck(RecruiterPortal::resetCodeHash(7,$code,$secret)!==RecruiterPortal::resetCodeHash(8,$code,$secret),'Reset digest binds member ID');
rcheck(rdenies(fn()=>RecruiterPortal::resetCodeHash(7,'1234567',$secret)),'Reset digest rejects short code');
rcheck(rdenies(fn()=>RecruiterPortal::resetCodeHash(7,$code,'short')),'Reset digest rejects short HMAC secret');
$member=['id'=>7,'auth_version'=>3];$session=['recruiter_member'=>7,'recruiter_auth_version'=>3,'recruiter_until'=>1500];
rcheck(RecruiterPortal::sessionIsCurrent($session,$member,1000),'Matching session credential epoch is active');
rcheck(!RecruiterPortal::sessionIsCurrent($session,[...$member,'auth_version'=>4],1000),'Password reset invalidates an older browser session');
rcheck(!RecruiterPortal::sessionIsCurrent([...$session,'recruiter_member'=>9],$member,1000),'Session cannot switch member IDs');
rcheck(!RecruiterPortal::sessionIsCurrent([...$session,'recruiter_auth_version'=>'3'],$member,1000),'Session version must use native integer');
rcheck(!RecruiterPortal::sessionIsCurrent($session,$member,1500),'Expired session denied');
rcheck(!RecruiterPortal::sessionIsCurrent($session,null,1000),'Missing trusted DB member denies session');
rcheck(!RecruiterMailer::configured(),'Mail delivery off by default');
putenv('RECRUITER_MAIL_ENABLED=1');putenv('RECRUITER_EMAIL_FROM=team@overseasjob.in');
putenv('RECRUITER_VERIFY_URL=https://staging.example.org/employers/verify');
putenv('RECRUITER_RESET_URL=https://staging.example.org/employers/reset');
rcheck(RecruiterMailer::configured(),'Verified HTTPS staging mail URLs configured');
$sent=[];
$transport=static function(...$args)use(&$sent){$sent=$args;return true;};
rcheck(RecruiterMailer::send('manager@example.org',$code,'reset',$transport),'Reset mail can use injected transport without contacting Internet');
rcheck(count($sent)===4&&str_contains($sent[1],'password reset')&&str_contains($sent[2],$code)&&str_contains($sent[2],'/employers/reset'),'Reset message contains only private-purpose code and verified destination');
rcheck(!str_contains($sent[3],'Bcc:')&&str_contains($sent[3],'text/plain'),'Mail header uses strict plain text without injected Bcc');
rcheck(RecruiterMailer::send('manager@example.org',$code,'invite',$transport)&&str_contains($sent[2],'/employers/verify'),'Invitation uses separate verified URL');
rcheck(rdenies(fn()=>RecruiterMailer::send("bad@example.org\r\nBcc:spam@e.org",$code,'invite',$transport)),'CRLF mail recipient rejected');
rcheck(rdenies(fn()=>RecruiterMailer::send('manager@example.org',$code,'other',$transport)),'Arbitrary email purpose rejected');
putenv('RECRUITER_RESET_URL=http://staging.example.org/employers/reset');
rcheck(!RecruiterMailer::configured(),'HTTP reset URL rejected');
putenv('RECRUITER_RESET_URL=https://evil.example.org/employers/reset');
rcheck(!RecruiterMailer::configured(),'Mismatched mail host rejected');
putenv('RECRUITER_MAIL_ENABLED=0');
rcheck(!RecruiterMailer::configured(),'Disabling mail prevents delivery');

$tmp=sys_get_temp_dir().'/oj-theme-audit-'.bin2hex(random_bytes(5));
$live=$tmp.'/live';$candidate=$tmp.'/candidate';mkdir($live,0700,true);mkdir($candidate,0700,true);
file_put_contents($live.'/index.html','<html><h1>Founder existing theme</h1></html>');
mkdir($live.'/assets');file_put_contents($live.'/assets/style.css','body{color:purple}');
file_put_contents($candidate.'/index.php','<?php echo "Proposed new design";');
mkdir($candidate.'/assets');file_put_contents($candidate.'/assets/style.css','body{color:navy}');
$before=hash_file('sha256',$live.'/index.html');
$report=ThemeSafety::inspect($live,$candidate);
rcheck(($report['safe_to_replace_current_theme']??null)===false,'Current live theme cannot be auto-approved for replacement');
rcheck(($report['html_vs_php_entrypoint_conflict']??null)===true,'Static HTML versus PHP entrypoint conflict detected');
rcheck(($report['same_path_different']??0)===1&&($report['paths_that_would_change'][0]??'')==='assets/style.css','CSS theme collision is reported');
rcheck(hash_file('sha256',$live.'/index.html')===$before,'Theme inspection does not modify existing homepage');
rcheck(rdenies(fn()=>ThemeSafety::inspect($live,$live)),'Same directory not allowed as both audit inputs');
if(function_exists('symlink')&&symlink($live.'/index.html',$candidate.'/assets/unsafe-link')){
 rcheck(rdenies(fn()=>ThemeSafety::inspect($live,$candidate)),'Symlink is refused instead of followed into private files');unlink($candidate.'/assets/unsafe-link');
}
foreach(glob($candidate.'/assets/*') as $f)unlink($f);rmdir($candidate.'/assets');unlink($candidate.'/index.php');rmdir($candidate);
foreach(glob($live.'/assets/*') as $f)unlink($f);rmdir($live.'/assets');unlink($live.'/index.html');rmdir($live);rmdir($tmp);
$schema=(string)file_get_contents(dirname(__DIR__).'/database/005_recruiter_password_recovery.mysql.sql');
rcheck(str_contains($schema,'auth_version')&&str_contains($schema,'oj_employer_password_resets'),'Dedicated recovery schema includes global session invalidation');
rcheck(str_contains((string)file_get_contents(dirname(__DIR__).'/public/index.php'),"/employers/reset"),'Employer recovery route and form are implemented');
rcheck(str_contains((string)file_get_contents(dirname(__DIR__).'/scripts/theme_collision_audit.php'),'PHP_SAPI'),'Theme safety audit is CLI-only');
echo "\n$n recruiter recovery and theme-preservation checks passed.\n";
