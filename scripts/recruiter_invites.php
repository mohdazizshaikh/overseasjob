<?php
declare(strict_types=1);
// Private CLI only; confirmation codes are sent by a configured transport, never echoed.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/RecruiterMailer.php';
try{
    if(!RecruiterPortal::enabled()||!RecruiterMailer::configured())throw new RuntimeException('Invitations disabled');
    if(count($argv)!==3)throw new InvalidArgumentException('Usage: php scripts/recruiter_invites.php org_ID lowercase_verified_email');
    $service=RecruiterMysql::fromEnvironment();
    $sent=$service->invite($argv[1],strtolower($argv[2]),
        static fn(string $email,string $code):bool=>RecruiterMailer::send($email,$code,'invite'),time());
    echo $sent?'Invitation submitted to mail transport; inbox receipt must be confirmed independently.':"Invite unavailable.";
    echo "\n";
}catch(Throwable){fwrite(STDERR,"Recruiter invitation unavailable; confirm all private configuration and verified employer prerequisites.\n");exit(2);}
