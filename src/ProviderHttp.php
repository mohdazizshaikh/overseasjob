<?php
declare(strict_types=1);
/** Bounded, source-host-only HTTP client. No redirects, arbitrary hosts or unbounded retries. */
final class ProviderHttp
{
    public const MAX_ATTEMPTS=3;
    public const RETRY_BUDGET_SECONDS=12;

    private static function allowed(string $url):bool
    {
        $parts=parse_url($url);
        return is_array($parts) && ($parts['scheme']??'')==='https'
            && in_array($parts['host']??null,['jobicy.com','himalayas.app'],true)
            && !isset($parts['user'],$parts['pass']) && !isset($parts['port'])
            && !isset($parts['fragment']) && strlen($url)<=4096;
    }

    /** Parse Retry-After seconds or RFC-compliant HTTP date; never retry too early. */
    public static function retryDelay(int $status,array $headers,int $attempt,int $spent=0,?int $now=null):?int
    {
        if(!in_array($status,[429,500,502,503,504],true)
            || $attempt>=self::MAX_ATTEMPTS-1 || $spent>=self::RETRY_BUDGET_SECONDS)return null;
        $value=null;
        foreach($headers as $header)if(is_string($header)&&preg_match('/^Retry-After:\s*(.*?)\s*$/i',$header,$m)){$value=$m[1];break;}
        $backoff=min(8,1<<$attempt);
        if($value!==null){
            if(preg_match('/^[0-9]{1,9}$/D',$value))$wait=(int)$value;
            elseif(($until=strtotime($value))!==false)$wait=max(0,$until-($now??time()));
            else return null; // Malformed Retry-After: no speculative early retry.
            $backoff=max($backoff,$wait);
        }
        return ($spent+$backoff<=self::RETRY_BUDGET_SECONDS)?$backoff:null;
    }

    private static function liveTransport(string $url,int $maxBytes):array
    {
        $ctx=stream_context_create(['http'=>[
            'timeout'=>20,'follow_location'=>0,'ignore_errors'=>true,
            'header'=>"Accept: application/json\r\nUser-Agent: OverseasJob.in/0.9 (+https://overseasjob.in; source-linked cache)\r\n",
        ],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $fp=@fopen($url,'rb',false,$ctx);
        if(!$fp)throw new RuntimeException('Provider connection unavailable');
        try{
            $metadata=stream_get_meta_data($fp);
            $headers=$metadata['wrapper_data']??[];
            $headers=is_array($headers)?$headers:[];
            if(!preg_match('/^HTTP\/\S+\s+([0-9]{3})\b/',(string)($headers[0]??''),$m))
                throw new RuntimeException('Unrecognised provider HTTP response');
            $body=stream_get_contents($fp,$maxBytes+1);
            if(!is_string($body)||strlen($body)>$maxBytes)throw new RuntimeException('Provider payload too large');
            return ['status'=>(int)$m[1],'headers'=>$headers,'body'=>$body];
        }finally{fclose($fp);}
    }

    /**
     * $transport/$sleeper are test-only injection hooks. Retry budget is bounded
     * and a Retry-After greater than budget fails closed for next scheduled run.
     */
    public static function request(string $url,int $maxBytes=2000000,?callable $transport=null,?callable $sleeper=null):array
    {
        if(!self::allowed($url)||$maxBytes<1||$maxBytes>2000000)throw new InvalidArgumentException('Forbidden provider request');
        $transport??=static fn(string $url,int $limit):array=>self::liveTransport($url,$limit);
        $sleeper??=static function(int $seconds):void{sleep($seconds);};
        $spent=0;
        for($attempt=0;$attempt<self::MAX_ATTEMPTS;$attempt++){
            $r=$transport($url,$maxBytes);
            if(!is_array($r)||!is_int($r['status']??null)||!is_array($r['headers']??null)
                ||!is_string($r['body']??null)||strlen($r['body'])>$maxBytes)
                throw new RuntimeException('Malformed provider transport result');
            $status=$r['status'];
            if(in_array($status,[200,404,410],true))return $r;
            $delay=self::retryDelay($status,$r['headers'],$attempt,$spent);
            if($delay===null)throw new RuntimeException('Provider temporarily unavailable (HTTP '.$status.'); healthy cache preserved');
            $sleeper($delay);$spent+=$delay;
        }
        throw new RuntimeException('Provider retries exhausted');
    }

    public static function fetchJson(string $url,int $maxBytes=2000000,?callable $transport=null,?callable $sleeper=null):array
    {
        $r=self::request($url,$maxBytes,$transport,$sleeper);
        if($r['status']!==200)throw new RuntimeException('Provider did not return a job feed (HTTP '.$r['status'].')');
        $data=json_decode($r['body'],true,64);
        if(!is_array($data))throw new RuntimeException('Invalid provider JSON');
        return $data;
    }
}
