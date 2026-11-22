<?php
declare(strict_types=1);
/** M10 trusted owner-only moderation authentication. Fail closed unless configured and HTTPS. */
final class OwnerAuth
{
    public static function configured():bool
    {
        $hash=getenv('OWNER_PASSWORD_HASH');$secret=getenv('OWNER_RATE_SECRET');
        return getenv('OWNER_PANEL_ENABLED')==='1'
            &&is_string($hash) && strlen($hash)>=50 && password_get_info($hash)['algo']!==null
            &&is_string($secret) && strlen($secret)>=32;
    }
    public static function secureRequest(array $server):bool
    {
        // Never trust arbitrary X-Forwarded-Proto from a public request.
        return isset($server['HTTPS']) && $server['HTTPS']!=='' && $server['HTTPS']!=='off';
    }
    public static function fingerprint(string $address):string
    {
        $secret=(string)getenv('OWNER_RATE_SECRET');
        if(strlen($secret)<32)throw new RuntimeException('Owner authentication not configured');
        if(!filter_var($address,FILTER_VALIDATE_IP))$address='0.0.0.0';
        return hash_hmac('sha256',$address,$secret);
    }
    private static function withRateLock(string $storage,callable $callback):mixed
    {
        if(!is_dir($storage))throw new RuntimeException('Owner rate store unavailable');
        $lock=fopen($storage.'/owner_rate.lock','c');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Owner rate store unavailable');
        try{
            $path=$storage.'/owner_rate.json';
            $raw=is_file($path)?file_get_contents($path):'{}';
            if(!is_string($raw)||strlen($raw)>65000)throw new RuntimeException('Owner rate store corrupt');
            $rows=json_decode($raw,true);
            if(!is_array($rows)||($rows!==[]&&array_is_list($rows)))throw new RuntimeException('Owner rate store corrupt');
            $result=$callback($rows);
            if($result['changed']){
                $temp=tempnam($storage,'rate-');if($temp===false)throw new RuntimeException('Cannot record attempts');
                try{
                    if(file_put_contents($temp,json_encode($rows,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Rate write failed');
                    chmod($temp,0600);
                    if(!rename($temp,$path))throw new RuntimeException('Rate save failed');
                }finally{if(is_file($temp))unlink($temp);}
            }
            return $result['result'];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function mayAttempt(string $storage,string $ip,int $now):bool
    {
        $id=self::fingerprint($ip);
        return self::withRateLock($storage,static function(array &$rows)use($id,$now){
            $state=$rows[$id]??null;
            $allowed=!is_array($state)||!isset($state['at'])||$now-(int)$state['at']>=900||(int)($state['count']??0)<5;
            return ['changed'=>false,'result'=>$allowed];
        });
    }
    public static function failedAttempt(string $storage,string $ip,int $now):void
    {
        $id=self::fingerprint($ip);
        self::withRateLock($storage,static function(array &$rows)use($id,$now){
            // Bound rate-store size; fail closed rather than discard active throttles.
            foreach($rows as $key=>$state){if(!is_array($state)||$now-(int)($state['at']??0)>900)unset($rows[$key]);}
            if(count($rows)>500)throw new RuntimeException('Owner rate capacity reached');
            $state=$rows[$id]??['at'=>$now,'count'=>0];
            if($now-(int)$state['at']>=900)$state=['at'=>$now,'count'=>0];
            $state['count']=min(100,(int)$state['count']+1);
            $rows[$id]=$state;
            return ['changed'=>true,'result'=>null];
        });
    }
    public static function verifyPassword(string $password):bool
    {
        if(!self::configured()||strlen($password)>1024)return false;
        return password_verify($password,(string)getenv('OWNER_PASSWORD_HASH'));
    }
    public static function active(array $session,int $now):bool
    {
        return ($session['owner_logged_in']??false)===true && is_int($session['owner_until']??null)&&$session['owner_until']>=$now;
    }
}
