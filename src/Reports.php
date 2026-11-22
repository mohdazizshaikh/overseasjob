<?php
declare(strict_types=1);
/** File-based bounded moderation queue for the non-public alpha. A database workflow is required before scale. */
final class Reports
{
    private const REASONS=['scam','wrong_eligibility','broken_apply','expired','other'];
    public static function read(string $storage):array
    {
        $file=$storage.'/reports.json';if(!is_file($file))return [];
        $raw=file_get_contents($file);if($raw===false||strlen($raw)>300000)throw new RuntimeException('Report queue unavailable');
        $data=json_decode($raw,true);if(!is_array($data)||!array_is_list($data))throw new RuntimeException('Report queue corrupted');
        return $data;
    }
    public static function quarantine(string $storage):array
    {
        $file=$storage.'/quarantine.json';if(!is_file($file))return [];
        $data=json_decode((string)file_get_contents($file),true);
        if(!is_array($data) || ($data!==[] && array_is_list($data))) throw new RuntimeException('Quarantine state corrupt');
        return $data;
    }
    public static function locked(string $storage,callable $callback):mixed
    {
        $handle=fopen($storage.'/moderation.lock','c');
        if(!$handle||!flock($handle,LOCK_EX))throw new RuntimeException('Moderation state locked');
        try{return $callback();}finally{flock($handle,LOCK_UN);fclose($handle);}
    }
    public static function atomicJson(string $file,mixed $value):void
    {
        $dir=dirname($file);$tmp=tempnam($dir,'mod-');if($tmp===false)throw new RuntimeException('Cannot create moderation state');
        try{
            $written=file_put_contents($tmp,json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),LOCK_EX);
            if($written===false)throw new RuntimeException('Cannot write moderation state');
            chmod($tmp,0600);
            if(!rename($tmp,$file))throw new RuntimeException('Cannot publish moderation state');
        }finally{if(is_file($tmp))unlink($tmp);}
    }
    public static function submit(string $storage,string $id,string $reason,string $details,?string $ip=null,?string $secret=null):string
    {
        if(!preg_match('/^(?:provider|direct):[A-Za-z0-9:._-]{2,160}$/',$id))throw new InvalidArgumentException('Provide a valid listing identifier');
        if(!in_array($reason,self::REASONS,true))throw new InvalidArgumentException('Select a valid reason');
        if(strlen($details)>2400)throw new InvalidArgumentException('Report details too long');
        $reportId='report-'.bin2hex(random_bytes(10));
        self::locked($storage,function()use($storage,$id,$reason,$details,$reportId,$ip,$secret){
            $rows=self::read($storage);
            // Refuse silent loss of pending complaints; no automatic deletion on full queue.
            if(count($rows)>=200)throw new RuntimeException('Review queue is full; try another trusted contact channel');
            if($ip!==null){
                if(!filter_var($ip,FILTER_VALIDATE_IP)||!is_string($secret)||strlen($secret)<32)
                    throw new RuntimeException('Private report rate limit unavailable');
                $hour=gmdate('YmdH');$key=hash_hmac('sha256',$ip,$secret);
                $ratePath=$storage.'/report_rate.json';
                $raw=is_file($ratePath)?file_get_contents($ratePath):'{}';
                if(!is_string($raw)||strlen($raw)>150000)throw new RuntimeException('Report abuse store unavailable');
                $state=json_decode($raw,true);
                if(!is_array($state)||($state!==[]&&array_is_list($state)))throw new RuntimeException('Report abuse store corrupt');
                if(($state['hour']??null)!==$hour)$state=['hour'=>$hour,'total'=>0,'per_client'=>[]];
                $clients=$state['per_client']??[];
                if(!is_array($clients)||count($clients)>=1000||($state['total']??0)>=120||($clients[$key]??0)>=6)
                    throw new RuntimeException('Rate limit reached for private report queue');
                // A rejected request never alters the existing moderation queue.
                $state['total']++;$state['per_client'][$key]=($clients[$key]??0)+1;
            }
            $rows[]=['id'=>$reportId,'at'=>gmdate('c'),'job_id'=>$id,'reason'=>$reason,'details'=>$details,'state'=>'pending_review'];
            if($ip!==null)self::atomicJson($ratePath,$state);
            self::atomicJson($storage.'/reports.json',$rows);
        });return $reportId;
    }
    /** Explicitly trusted CLI workflow; source sync never removes quarantine state. */
    public static function decide(string $storage,string $reportId,string $decision,string $note):void
    {
        if(!in_array($decision,['quarantine','dismiss','resolve'],true))throw new InvalidArgumentException('Unsupported review decision');
        if(trim($note)===''||strlen($note)>280)throw new InvalidArgumentException('A brief review reason is required');
        self::locked($storage,function()use($storage,$reportId,$decision,$note){
            $rows=self::read($storage);$found=null;
            foreach($rows as &$r){if(($r['id']??'')===$reportId){
                if(($r['state']??'')!=='pending_review')throw new RuntimeException('Report already decided');
                $r['state']=$decision==='quarantine'?'quarantined':($decision==='dismiss'?'dismissed':'resolved');
                $r['reviewed_at']=gmdate('c');$found=$r['job_id'];break;
            }}unset($r);
            if($found===null)throw new RuntimeException('Report ID does not exist');
            if($decision==='quarantine'){
                $quarantine=self::quarantine($storage);
                $quarantine[$found]=['held_at'=>gmdate('c'),'report_id'=>$reportId,'reason'=>substr($note,0,280)];
                self::atomicJson($storage.'/quarantine.json',$quarantine);
            }
            self::atomicJson($storage.'/reports.json',$rows);
            self::audit($storage,['action'=>$decision,'job_id'=>$found,'report_id'=>$reportId,'reason'=>substr($note,0,280)]);
        });
    }
    public static function reopen(string $storage,string $jobId,string $note):void
    {
        if(trim($note)===''||strlen($note)>280)throw new InvalidArgumentException('A reason is required');
        self::locked($storage,function()use($storage,$jobId,$note){
            $states=self::quarantine($storage);
            if(!isset($states[$jobId]))throw new RuntimeException('Job is not quarantined');
            unset($states[$jobId]);self::atomicJson($storage.'/quarantine.json',$states);
            self::audit($storage,['action'=>'reopen','job_id'=>$jobId,'reason'=>substr($note,0,280)]);
        });
    }
    private static function audit(string $storage,array $row):void
    {
        $row=['at'=>gmdate('c')]+$row;
        if(file_put_contents($storage.'/moderation_audit.ndjson',json_encode($row,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n",FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Moderation audit write failed');
    }
}
