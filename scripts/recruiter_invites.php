<?php
declare(strict_types=1);
// Private CLI-only mailer; one-time codes never printed or logged.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/RecruiterPortal.php';
try{
    if(!RecruiterPortal::enabled()||getenv('RECRUITER_MAIL_ENABLED')!=='1')throw new RuntimeException('Invitations disabled');
    if(count($argv)!==3)throw new InvalidArgumentException('Usage: php scripts/recruiter_invites.php org_ID lowercase_verified_email');
    $from=(string)getenv('RECRUITER_EMAIL_FROM');
    if(!RecruiterPortal::validEmail($from))throw new RuntimeException('Sender email missing or invalid');
    $verifyUrl=(string)getenv('RECRUITER_VERIFY_URL');
    if(!preg_match('#^https://[a-z0-9.-]+/employers/verify$#D',$verifyUrl))throw new RuntimeException('Configured HTTPS verify URL required');
    $service=RecruiterMysql::fromEnvironment();
    $sent=$service->invite($argv[1],strtolower($argv[2]),static function(string $email,string $code)use($from,$verifyUrl):bool{
        $subject='OverseasJob.in employer email confirmation';
        $message="An OverseasJob.in employer account invitation was requested for this email.\n\nYour one-time confirmation code: ".$code."\n\nExpires in 15 minutes. Visit ".$verifyUrl.". Do not share this code.\nIf you did not request it, ignore this email.\n";
        return mail($email,$subject,$message,'From: '.$from."\r\nContent-Type: text/plain; charset=UTF-8\r\n");
    },time());
    echo $sent?"Invitation message submitted to configured mail transport; inbox delivery must be verified externally.\n":"Delivery failed.\n";
}catch(Throwable $e){fwrite(STDERR,"Recruiter invitation not issued: ".$e->getMessage()."\n");exit(2);}
