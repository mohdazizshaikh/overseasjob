<?php
declare(strict_types=1);
/** DNS TXT proves domain control ONLY; it does not verify legal employer identity or representative authority. */
final class EmployerDomainProof
{
    public static function validDomain(string $domain):bool
    {
        if($domain!==strtolower($domain)||strlen($domain)>253||strlen($domain)<4
            ||str_contains($domain,'xn--')||str_contains($domain,'..')
            ||filter_var($domain,FILTER_VALIDATE_IP)!==false)return false;
        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D',$domain)===1
            &&!preg_match('/\.(?:local|internal|test|invalid|example)$/D',$domain);
    }
    public static function txtHost(string $domain):string
    {
        if(!self::validDomain($domain))throw new InvalidArgumentException('Invalid public DNS domain');
        return '_overseasjob-verify.'.$domain;
    }
    public static function makeChallenge():string{return 'overseasjob-domain-proof='.bin2hex(random_bytes(24));}
    public static function digest(string $challenge):string
    {
        if(!preg_match('/^overseasjob-domain-proof=[a-f0-9]{48}$/D',$challenge))throw new InvalidArgumentException('Invalid proof token');
        return hash('sha256',$challenge);
    }
    /** $txtRecords is the untrusted DNS resolver result; reject partial or concatenated matches. */
    public static function matches(string $digest,array $txtRecords):bool
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||count($txtRecords)>100)return false;
        foreach($txtRecords as $record){
            $value=is_string($record)?$record:(is_array($record)&&isset($record['txt'])&&is_string($record['txt'])?$record['txt']:null);
            if(is_string($value)&&strlen($value)===73 && preg_match('/^overseasjob-domain-proof=[a-f0-9]{48}$/D',$value)
              &&hash_equals($digest,hash('sha256',$value)))return true;
        }
        return false;
    }
}
