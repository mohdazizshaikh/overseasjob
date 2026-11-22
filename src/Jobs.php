<?php
declare(strict_types=1);
require_once __DIR__.'/Countries.php';

/** Permission-aware normalisation; no outbound feed/re-syndication API. */
final class Jobs
{
    public const MAX_AGE_SECONDS = 259200; // source verification freshness: 72h
    public static function utc(int|string|float|null $input): ?string
    {
        if ($input === null || $input === '') return null;
        try {
            if (is_numeric($input)) {
                $n = (float)$input;
                if ($n <= 0 || !is_finite($n)) return null;
                if ($n > 100000000000) $n /= 1000; // ms to seconds
                return gmdate('Y-m-d\TH:i:s\Z', (int)$n);
            }
            $date = new DateTimeImmutable((string)$input);
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable) {return null;}
    }
    public static function safeUrl(mixed $url, ?string $expectedHost = null): ?string
    {
        if (!is_string($url) || strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL)) return null;
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') return null;
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!$host || $host === 'localhost' || str_ends_with($host, '.local') || filter_var($host, FILTER_VALIDATE_IP)) return null;
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return null;
        if ($expectedHost && $host !== $expectedHost && !str_ends_with($host, '.'.$expectedHost)) return null;
        return $url;
    }
    public static function text(mixed $input, int $length = 180): string
    {
        if (!is_string($input)) return '';
        $s = trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($input), ENT_QUOTES|ENT_HTML5, 'UTF-8')) ?? '');
        return function_exists('mb_substr') ? mb_substr($s,0,$length,'UTF-8') : substr($s,0,$length);
    }
    public static function isoCurrency(mixed $s): ?string
    {
        return is_string($s) && preg_match('/^[A-Z]{3}$/', strtoupper($s)) ? strtoupper($s) : null;
    }
    public static function salary(mixed $n): ?float
    {
        return is_numeric($n) && is_finite((float)$n) && (float)$n>=0 && (float)$n<10000000000 ? (float)$n : null;
    }
    public static function timezoneOffset(mixed $offset): ?int
    {
        if (!is_string($offset) && !is_numeric($offset)) return null;
        $s = trim((string)$offset);
        if (preg_match('/^(?:UTC|GMT)?\s*([+-])(\d{1,2})(?::?(\d{2}))?$/i',$s,$m)) {
            $minutes=((int)$m[2])*60+(int)($m[3]??0);
            if ($minutes>14*60 || (int)($m[3]??0)>=60) return null;
            return ($m[1]==='-'?-1:1)*$minutes;
        }
        if (preg_match('/^[+-]?\d+(?:\.\d+)?$/',$s) && abs((float)$s)<=14) return (int)round((float)$s*60);
        return null;
    }
    /** Current upstream schema: locationRestrictions is string[] (ISO alpha2),
     * timezoneRestrictions is number[] of UTC hour offsets. Legacy object values
     * are accepted only to avoid dropping older cached records during migration.
     * Missing restrictions remain UNKNOWN; empty arrays explicitly allow all.
     */
    public static function himalayasIndia(array $raw): array
    {
        $locations = $raw['locationRestrictions'] ?? null;
        $zones = $raw['timezoneRestrictions'] ?? null;
        $geo='unknown'; $time='unknown';
        if (is_array($locations)) {
            // Real provider responses can contain ISO2 codes, country names or
            // legacy {alpha2,name} objects. Validate ALL entries, not just India.
            $valid=true; $codes=[];
            foreach ($locations as $value) {
                $code=Countries::fromProviderValue($value);
                if ($code===null) {$valid=false;break;}
                $codes[]=$code;
            }
            if ($valid) $geo=(!$codes || in_array('IN',$codes,true))?'allowed':'not_allowed';
        }
        if (is_array($zones)) {
            $valid=true; $minutes=[];
            foreach ($zones as $zone) {
                $offset=self::timezoneOffset($zone);
                if ($offset===null) { $valid=false;break; }
                $minutes[]=$offset;
            }
            if ($valid) $time=(!$minutes || in_array(330,$minutes,true))?'allowed':'not_allowed';
        }
        if ($geo==='not_allowed' || $time==='not_allowed') $final='not_allowed';
        elseif ($geo==='allowed' && $time==='allowed') $final='allowed';
        else $final='unknown';
        return ['eligibility'=>$final,'geo'=>$geo,'timezone'=>$time];
    }
    public static function jobicyIndia(array $raw): array
    {
        $geo=strtolower(trim((string)($raw['jobGeo']??'')));
        // Upstream documents "Anywhere" as "no region specified", not evidence
        // that this employer accepts an applicant in every country.
        if (in_array($geo,['india','worldwide','global','remote worldwide'],true)) $geography='allowed';
        elseif (preg_match('/^(usa|us only|united states|canada|uk|united kingdom|europe|europe only)$/',$geo)) $geography='not_allowed';
        else $geography='unknown';
        // Jobicy free feed does not provide a separate reliable timezone restriction.
        // Therefore location allowed never implies a fully confirmed India match.
        $combined=$geography==='not_allowed'?'not_allowed':'unknown';
        return ['eligibility'=>$combined,'geography'=>$geography,'geo'=>$geo?:'not specified','timezone'=>'unknown'];
    }
    public static function indiaFullyEligible(array $j): bool
    {
        return ($j['work_mode']??null)==='remote' &&
            ($j['eligible_from_in']??null)==='allowed' &&
            ($j['time_zone_eligibility']??null)==='allowed';
    }
    private static function base(string $provider, string $pid, string $source, string $apply, string $title, array $extras): array
    {
        $now=gmdate('Y-m-d\TH:i:s\Z');
        return array_merge([
            'id'=>'provider:'.$provider.':'.$pid,'origin'=>'provider','provider'=>$provider,
            'provider_job_id'=>$pid,'source_name'=>ucfirst($provider),'source_url'=>$source,'apply_url'=>$apply,
            'title'=>$title,'company_name'=>null,'work_mode'=>'remote','work_country'=>null,'employer_country'=>null,
            'source_policy_id'=>($provider==='jobicy'?'provider_jobicy_source_link_only':'provider_himalayas_source_link_only'),
            'reason_tags'=>[],
            'eligible_from_in'=>'unknown','location_eligibility'=>'unknown','time_zone_eligibility'=>'unknown',
            'eligibility_evidence'=>[],'visa_sponsorship'=>'unknown',
            'salary_min'=>null,'salary_max'=>null,'salary_currency'=>null,'salary_period'=>null,
            'source_published_at'=>null,'source_updated_at'=>null,'first_seen_at'=>$now,
            'last_seen_at'=>$now,'last_verified_at'=>$now,'valid_through'=>null,
            'availability'=>'active','display_status'=>'visible','description'=>'',
            'rights'=>['attribution_required'=>true,'allow_jobposting_schema'=>false,'allow_resyndication'=>false]
        ],$extras);
    }
    public static function fromJobicy(array $j): ?array
    {
        $id=$j['id']??null;
        $url=self::safeUrl($j['url']??null,'jobicy.com');
        $title=self::text($j['jobTitle']??null,140);
        if (!is_scalar($id) || !$url || $title==='') return null;
        $geo=self::jobicyIndia($j);
        $period=strtolower((string)($j['salaryPeriod']??''));
        $periodMap=['hourly'=>'hour','daily'=>'day','weekly'=>'week','fortnightly'=>'fortnight','monthly'=>'month','yearly'=>'year','annual'=>'year'];
        return self::base('jobicy',(string)$id,$url,$url,$title,[
            'company_name'=>self::text($j['companyName']??'',100)?:null,
            'eligible_from_in'=>$geo['eligibility'],
            'location_eligibility'=>$geo['geography'],
            'time_zone_eligibility'=>'unknown',
            'eligibility_evidence'=>[['provider_field'=>'jobGeo','fact'=>self::text($j['jobGeo']??'not specified',140),'observed_at'=>gmdate('c')]],
            'salary_min'=>self::salary($j['salaryMin']??null),'salary_max'=>self::salary($j['salaryMax']??null),
            'salary_currency'=>self::isoCurrency($j['salaryCurrency']??null),
            'salary_period'=>$periodMap[$period]??null,
            'source_published_at'=>self::utc($j['pubDate']??null),
            'description'=>self::text($j['jobExcerpt']??$j['jobDescription']??'',300),
        ]);
    }
    public static function fromHimalayas(array $j): ?array
    {
        $guid=self::safeUrl($j['guid']??null,'himalayas.app');
        $apply=self::safeUrl($j['applicationLink']??null,'himalayas.app');
        $title=self::text($j['title']??null,140);
        if (!$guid || !$apply || !$title) return null;
        $elig=self::himalayasIndia($j);
        $period=strtolower((string)($j['salaryPeriod']??''));
        $periodMap=['hourly'=>'hour','daily'=>'day','weekly'=>'week','fortnightly'=>'fortnight','monthly'=>'month','annual'=>'year','yearly'=>'year'];
        return self::base('himalayas',hash('sha256',$guid),$guid,$apply,$title,[
            'company_name'=>self::text($j['companyName']??'',100)?:null,
            'eligible_from_in'=>$elig['eligibility'],'location_eligibility'=>$elig['geo'],'time_zone_eligibility'=>$elig['timezone'],
            'eligibility_evidence'=>[
                ['provider_field'=>'locationRestrictions','fact'=>self::text(json_encode($j['locationRestrictions']??'not supplied',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'not supplied',240),'observed_at'=>gmdate('c')],
                ['provider_field'=>'timezoneRestrictions','fact'=>self::text(json_encode($j['timezoneRestrictions']??'not supplied',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:'not supplied',240),'observed_at'=>gmdate('c')],
            ],
            'salary_min'=>self::salary($j['minSalary']??null),'salary_max'=>self::salary($j['maxSalary']??null),
            'salary_currency'=>self::isoCurrency($j['currency']??null),
            'salary_period'=>$periodMap[$period]??null,
            'source_updated_at'=>self::utc($j['pubDate']??null), // JSON browse pubDate means last updated, not first published
            'valid_through'=>self::utc($j['expiryDate']??null),
            'description'=>self::text($j['excerpt']??'',300),
        ]);
    }
    /** Disclosed amounts only; pay basis is not inferred from external feed. */
    /** Current public browse API offset/limit protocol. null means page complete. */
    /** Distinguish a real next page from an upstream endpoint ignoring offset.
     * The signature deliberately excludes offset metadata and only hashes listing identifiers.
     */
    public static function himalayasPageSignature(array $response): string
    {
        if(!is_array($response['jobs']??null))throw new InvalidArgumentException('Malformed Himalayas page');
        $ids=[];
        foreach($response['jobs'] as $j)$ids[]=is_array($j)&&is_string($j['guid']??null)?$j['guid']:null;
        return hash('sha256',json_encode($ids,JSON_THROW_ON_ERROR));
    }
    /** Current provider pagination: nextCursor is opaque and must be echoed unchanged. */
    public static function himalayasNextCursor(array $response, int $limit=20): ?string
    {
        if ($limit<1 || $limit>20 || !is_array($response['jobs']??null)
            || count($response['jobs'])>$limit)
            throw new InvalidArgumentException('Malformed Himalayas cursor page');
        if (!array_key_exists('nextCursor',$response) || $response['nextCursor']===null)
            return null;
        $cursor=$response['nextCursor'];
        if (!is_string($cursor) || $cursor==='' || strlen($cursor)>2048
            || preg_match('/[\x00-\x20\x7f]/',$cursor))
            throw new InvalidArgumentException('Invalid Himalayas cursor');
        if (count($response['jobs'])===0)
            throw new InvalidArgumentException('Next cursor supplied without jobs');
        return $cursor;
    }
    /** Build ONLY the documented read-only per-job status endpoint from its canonical guid.
     * Never dereference candidate-provided URLs; the resulting endpoint is confined to the API host.
     */
    public static function himalayasStatusUrl(string $guid): string
    {
        if (self::safeUrl($guid,'himalayas.app')===null)throw new InvalidArgumentException('Invalid Himalayas job guid');
        $parts=parse_url($guid);
        if(!is_array($parts) || strtolower((string)($parts['host']??''))!=='himalayas.app'
            || isset($parts['query']) || isset($parts['fragment'])
            || !preg_match('#^/companies/[a-z0-9-]+/jobs/[a-z0-9-]+/?$#D',(string)($parts['path']??'')))
            throw new InvalidArgumentException('Himalayas job path is not supported');
        return 'https://himalayas.app/jobs/api'.rtrim((string)$parts['path'],'/');
    }
    /** Only authoritative HTTP 410 is closure. 404/errors mean unknown, not closed. */
    public static function himalayasStatusDecision(int $status): string
    {
        return match($status){200=>'active',410=>'closed',default=>'unknown'};
    }
    public static function himalayasBrowseUrl(?string $cursor=null): string
    {
        $base='https://himalayas.app/jobs/api?limit=20';
        if ($cursor===null) return $base;
        if ($cursor==='' || strlen($cursor)>2048
            || preg_match('/[\x00-\x20\x7f]/',$cursor))
            throw new InvalidArgumentException('Invalid Himalayas cursor');
        return $base.'&cursor='.rawurlencode($cursor);
    }
    /** Legacy offset helper retained only for older fixture compatibility. */
    public static function himalayasNextOffset(array $response, int $requestedOffset, int $limit=20): ?int
    {
        if($requestedOffset<0||$limit<1||$limit>20 || !is_array($response['jobs']??null))
            throw new InvalidArgumentException('Malformed Himalayas page');
        if(isset($response['offset']) && (!is_int($response['offset']) || $response['offset']!==$requestedOffset))
            throw new InvalidArgumentException('Himalayas offset mismatch');
        if(count($response['jobs'])>$limit)throw new InvalidArgumentException('Page larger than requested');
        $count=count($response['jobs']);
        $total=$response['totalCount']??null;
        if($total!==null && (!is_int($total)||$total<0))
            throw new InvalidArgumentException('Malformed Himalayas totalCount');
        $next=$requestedOffset+$count;
        if($total!==null && $next>$total)
            throw new InvalidArgumentException('Page exceeds advertised Himalayas total');
        if($count<$limit && $total!==null && $next<$total)
            throw new InvalidArgumentException('Short Himalayas page before end of advertised total');
        if($count===0 || $count<$limit)return null;
        if($total!==null && $next>=$total)return null;
        return $next;
    }
    public static function salaryLabel(array $j): ?string
    {
        $currency=$j['salary_currency']??null;
        $period=$j['salary_period']??null;
        $minimum=$j['salary_min']??null;
        $maximum=$j['salary_max']??null;
        if (!is_string($currency) || !preg_match('/^[A-Z]{3}$/',$currency)) return null;
        if (!in_array($period,['hour','day','week','fortnight','month','year'],true)) return null;
        $numbers=[];
        foreach([$minimum,$maximum] as $n) {
            $numbers[] = $n===null?null:self::salary($n);
        }
        [$min,$max]=$numbers;
        if ($min===null && $max===null) return null;
        if ($min!==null && $max!==null && $min>$max) return null;
        $format=static fn(float $amount)=>number_format($amount, ($amount==(int)$amount?0:2),'.',',');
        if ($min!==null && $max!==null) $range=$format($min).'–'.$format($max);
        elseif ($min!==null) $range='from '.$format($min);
        else $range='up to '.$format($max);
        return $currency.' '.$range.' / '.$period.' (gross/net not disclosed)';
    }
    public static function visible(array $j, ?int $now=null): bool
    {
        $now??=time();
        if (($j['display_status']??'hidden')!=='visible'||($j['availability']??'unknown')!=='active') return false;
        $t=strtotime((string)($j['last_verified_at']??''));
        if (!$t || ($now-$t)>self::MAX_AGE_SECONDS || $t>$now+300) return false;
        $expiration=$j['valid_through']??null;
        if ($expiration && ($x=strtotime((string)$expiration)) && $x<=$now) return false;
        return self::safeUrl($j['apply_url']??null)!==null && self::safeUrl($j['source_url']??null)!==null;
    }
    public static function listPublic(string $path, ?int $now=null): array
    {
        if (!is_file($path))return [];
        $raw=file_get_contents($path);
        if ($raw===false||strlen($raw)>16000000)return [];
        $doc=json_decode($raw,true);
        if (!is_array($doc)||!is_array($doc['jobs']??null))return [];
        // Persisted manual quarantines override every provider refresh (no reappearing scam link).
        $holds=[];$holdFile=dirname($path).'/quarantine.json';
        if(is_file($holdFile)){
            $holds=json_decode((string)file_get_contents($holdFile),true);
            if(!is_array($holds) || ($holds!==[] && array_is_list($holds)))return []; // corrupt hold state: fail closed
        }
        return array_values(array_filter($doc['jobs'],fn($j)=>is_array($j)&&self::visible($j,$now)&&!array_key_exists((string)($j['id']??''),$holds)));
    }
    public static function search(array $jobs,string $keyword,string $path): array
    {
        $keyword=function_exists('mb_strtolower') ? mb_strtolower(trim($keyword),'UTF-8') : strtolower(trim($keyword));
        return array_values(array_filter($jobs,static function($j)use($keyword,$path){
            if ($path==='remote_from_india' && !self::indiaFullyEligible($j))return false;
            if ($path==='remote_other' && (($j['work_mode']??null)!=='remote'||self::indiaFullyEligible($j)))return false;
            if ($path==='onsite_abroad' && ($j['work_mode']!=='onsite'||empty($j['work_country'])))return false;
            if ($keyword && !str_contains(strtolower(($j['title']??'').' '.($j['company_name']??'')),$keyword))return false;
            return true;
        }));
    }
}
