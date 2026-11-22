<?php
declare(strict_types=1);
require_once __DIR__.'/RecruiterPortal.php';
/** Explicitly configured, single-purpose plain-text transactional email; no email API subscription. */
final class RecruiterMailer
{
    public static function configured():bool
    {
        if(getenv('RECRUITER_MAIL_ENABLED')!=='1')return false;
        $from=(string)getenv('RECRUITER_EMAIL_FROM');
        $verify=(string)getenv('RECRUITER_VERIFY_URL');
        $reset=(string)getenv('RECRUITER_RESET_URL');
        if(!RecruiterPortal::validEmail($from))return false;
        foreach([$verify,$reset] as $url){
            if(!preg_match('#^https://([a-z0-9-]+\.)+[a-z]{2,}/employers/(verify|reset)$#D',$url))return false;
        }
        return parse_url($verify,PHP_URL_HOST)===parse_url($reset,PHP_URL_HOST)
            &&parse_url($verify,PHP_URL_PATH)==='/employers/verify'
            &&parse_url($reset,PHP_URL_PATH)==='/employers/reset';
    }
    /** Transport injection makes it possible to test body/header safety with no external delivery. */
    public static function send(string $email,string $code,string $purpose,?callable $transport=null):bool
    {
        if(!self::configured()||!RecruiterPortal::validEmail($email)
          ||!preg_match('/^[0-9]{8}$/D',$code)||!in_array($purpose,['invite','reset'],true)){
            throw new RuntimeException('Recruiter outbound email not safely configured');
        }
        $from=(string)getenv('RECRUITER_EMAIL_FROM');
        $url=(string)getenv($purpose==='invite'?'RECRUITER_VERIFY_URL':'RECRUITER_RESET_URL');
        $subject=$purpose==='invite'?'OverseasJob.in employer invitation':'OverseasJob.in employer password reset';
        $message=$purpose==='invite'
            ?"An OverseasJob.in employer account invitation was requested for this mailbox.\n"
            :"A password reset was requested for an invited OverseasJob.in employer account.\n";
        $message.="\nYour one-time code: ".$code."\nThis code expires in 15 minutes and can only be used once.\n"
            ."Open ".$url." and enter it manually. Do not forward the code.\n"
            ."If you did not request this, ignore the message. Do not reply or send documents.\n";
        $headers='From: '.$from."\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        if($transport!==null)return $transport($email,$subject,$message,$headers)===true;
        return mail($email,$subject,$message,$headers);
    }
}
