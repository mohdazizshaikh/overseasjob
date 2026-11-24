<?php
declare(strict_types=1);
require_once __DIR__.'/EmployerContracts.php';
require_once __DIR__.'/OwnerAuth.php';

/** Phase 3B inbound-only employer API; all operations are explicitly organisation-scoped.
 *  This core is testable without a database, but HTTP access fails closed until the
 *  verified MySQL, employer identity, hosting and moderation gates are satisfied.
 */
interface PartnerRepository
{
    /** Principal must come ONLY from server-side token + approved org state. */
    public function principal(string $fingerprint,string $prefix,int $now):?array;
    /** Atomic per-token per-minute counter; returns number including current request. */
    public function hitRate(int $tokenId,int $now):int;
    /** Returns {record,replayed}; creates in pending_review atomically. */
    public function create(array $org,array $job,string $key,string $requestHash,int $now):array;
    public function listJobs(string $orgId,int $limit,?string $cursor,?string $status,?string $externalId):array;
    public function getJob(string $orgId,string $jobId):?array;
    public function patchJob(array $org,string $jobId,array $patch,int $revision,int $now,string $terms):?array;
    public function closeJob(string $orgId,string $jobId,int $now):?array;
    public function usage(string $orgId,int $tokenId,int $now):array;
}
final class PartnerConflict extends RuntimeException {}
final class PartnerNotFound extends RuntimeException {}
final class PartnerApi
{
    public const MAX_JSON_BYTES=32768;
    private const SCOPES=['jobs:read','jobs:write','jobs:close'];

    public static function gateReady():bool
    {
        return getenv('PARTNER_POSTING_API_ENABLED')==='1'
          && getenv('RECRUITER_LITE_ENABLED')==='1'
          && getenv('PARTNER_PILOT_VERIFIED')==='1'
          && extension_loaded('pdo_mysql')
          && is_string(getenv('PARTNER_DB_DSN')) && str_starts_with((string)getenv('PARTNER_DB_DSN'),'mysql:')
          && strlen((string)getenv('PARTNER_TOKEN_HMAC_SECRET'))>=32
          && strlen((string)getenv('PARTNER_TERMS_VERSION'))>=3;
    }
    public static function response(int $status,array $body,array $headers=[]):array
    {return ['status'=>$status,'body'=>$body,'headers'=>['Cache-Control'=>'no-store, private','Content-Type'=>'application/json; charset=utf-8','X-Content-Type-Options'=>'nosniff']+$headers];}
    private static function error(int $status,string $code,string $message,string $requestId,array $headers=[]):array
    {return self::response($status,['error'=>['code'=>$code,'message'=>$message,'request_id'=>$requestId]],$headers);}
    public static function canonicalHash(array $input):string
    {
        $sort=function (mixed $value)use (&$sort):mixed {
            if(!is_array($value))return $value;
            if(array_is_list($value))return array_map($sort,$value);
            ksort($value,SORT_STRING);
            return array_map($sort,$value);
        };
        return hash('sha256',json_encode($sort($input),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    /** @return array{status:int,body:array,headers:array} */
    public static function dispatch(string $method,string $path,array $headers,string $body,array $query,array $server,PartnerRepository $store,int $now,string $secret,string $terms):array
    {
        $rid='req_'.bin2hex(random_bytes(8));
        if(!OwnerAuth::secureRequest($server))return self::error(503,'tls_required','HTTPS is required',$rid);
        if(strlen($secret)<32||strlen($terms)<3)return self::error(503,'unavailable','Partner integration is unavailable',$rid);
        $method=strtoupper($method);
        $route=substr($path,strlen('/api/v1/partner'));
        if(!preg_match('#^/(?:jobs(?:/(?:job_[a-f0-9]{24})(?:/close)?)?|usage)$#',$route))return self::error(404,'not_found','Route unavailable',$rid);
        $bearer=(string)($headers['authorization']??'');
        if(!preg_match('/^Bearer ([a-f0-9]{64})$/',$bearer,$matches))return self::error(401,'unauthorized','Invalid credentials',$rid);
        try{$fingerprint=EmployerContracts::tokenFingerprint($matches[1],$secret);
            $principal=$store->principal($fingerprint,substr($matches[1],0,12),$now);
        }catch(Throwable){return self::error(503,'unavailable','Authentication is unavailable',$rid);}
        if($principal===null)return self::error(401,'unauthorized','Invalid credentials',$rid);
        $org=$principal['org']??null;$scopes=$principal['scopes']??null;
        if(!is_array($org)||!EmployerContracts::permittedForPilot($org)||!is_array($scopes))return self::error(403,'organisation_disabled','Organisation is not approved',$rid);
        $required=($route==='/usage'||($method==='GET'&&($route==='/jobs'||str_starts_with($route,'/jobs/'))))?'jobs:read':($method==='POST'&&str_ends_with($route,'/close')?'jobs:close':'jobs:write');
        if(!in_array($required,self::SCOPES,true)||!in_array($required,$scopes,true))return self::error(403,'scope_denied','Operation not permitted',$rid);
        try{$rate=$store->hitRate((int)$principal['token_id'],$now);}
        catch(Throwable){return self::error(503,'unavailable','Rate limiter unavailable',$rid);}
        if($rate>60)return self::error(429,'rate_limited','Request limit exceeded',$rid,['Retry-After'=>'60']);
        if(($method==='POST'&&$route==='/jobs')||$method==='PATCH'){
            if(strlen($body)>self::MAX_JSON_BYTES)return self::error(413,'payload_too_large','JSON body exceeds limit',$rid);
            if(!in_array((string)($headers['content-type']??''),['application/json','application/json; charset=utf-8'],true))return self::error(415,'content_type','Expected application/json',$rid);
            if($body==='')return self::error(400,'bad_json','JSON body required',$rid);
        }
        if($method==='POST'&&str_ends_with($route,'/close')&&$body!=='')return self::error(400,'unexpected_body','Close does not take request content',$rid);
        try{
            if($route==='/usage'&&$method==='GET')return self::response(200,$store->usage($org['id'],(int)$principal['token_id'],$now));
            if($route==='/jobs'&&$method==='GET'){
                if(count(array_diff(array_keys($query),['limit','cursor','status','external_id'])))throw new InvalidArgumentException('Unexpected query parameter');
                $limit=$query['limit']??25;
                if(!ctype_digit((string)$limit)||(int)$limit<1||(int)$limit>100)throw new InvalidArgumentException('Invalid limit');
                $cursor=$query['cursor']??null;
                if($cursor!==null&&(!is_string($cursor)||!preg_match('/^job_[a-f0-9]{24}$/',$cursor)))throw new InvalidArgumentException('Invalid cursor');
                $status=$query['status']??null;
                if($status!==null&&!in_array($status,['draft','pending_review','published','closed','expired','rejected','suspended'],true))throw new InvalidArgumentException('Invalid status');
                $external=$query['external_id']??null;
                if($external!==null&&(!is_string($external)||strlen($external)>160))throw new InvalidArgumentException('Invalid external_id');
                return self::response(200,$store->listJobs($org['id'],(int)$limit,$cursor,$status,$external));
            }
            if($route==='/jobs'&&$method==='POST'){
                $key=EmployerContracts::validateIdempotencyKey($headers['idempotency-key']??null);
                $json=json_decode($body,true,32,JSON_THROW_ON_ERROR);
                if(!is_array($json)||array_is_list($json))throw new InvalidArgumentException('Expected a JSON object');
                $normal=EmployerContracts::validateCreate($json,$org,$now,$terms);
                $result=$store->create($org,$normal,$key,self::canonicalHash($json),$now);
                return self::response($result['replayed']?200:201,self::publicJob($result['record']),['ETag'=>'"'.$result['record']['revision'].'"']);
            }
            if(preg_match('#^/jobs/(job_[a-f0-9]{24})(?:/(close))?$#',$route,$m)){
                $id=$m[1];$closing=isset($m[2]);
                if(!$closing&&$method==='GET'){$job=$store->getJob($org['id'],$id);if($job===null)throw new PartnerNotFound();return self::response(200,self::publicJob($job),['ETag'=>'"'.$job['revision'].'"']);}
                if($closing&&$method==='POST'){$job=$store->closeJob($org['id'],$id,$now);if($job===null)throw new PartnerNotFound();return self::response(200,self::publicJob($job));}
                if(!$closing&&$method==='PATCH'){
                    if(!preg_match('/^"([1-9][0-9]{0,8})"$/',(string)($headers['if-match']??''),$match))throw new InvalidArgumentException('If-Match revision required');
                    $patch=json_decode($body,true,32,JSON_THROW_ON_ERROR);
                    if(!is_array($patch)||array_is_list($patch))throw new InvalidArgumentException('Expected a JSON object');
                    $job=$store->patchJob($org,$id,$patch,(int)$match[1],$now,$terms);
                    if($job===null)throw new PartnerNotFound();
                    return self::response(200,self::publicJob($job),['ETag'=>'"'.$job['revision'].'"']);
                }
            }
        }catch(PartnerNotFound){return self::error(404,'not_found','Job not found',$rid);
        }catch(PartnerConflict){return self::error(409,'conflict','Idempotency, external ID or revision conflict',$rid);
        }catch(JsonException|InvalidArgumentException){return self::error(422,'validation_error','Request validation failed',$rid);
        }catch(DomainException){return self::error(403,'not_allowed','Action not permitted',$rid);
        }catch(Throwable){return self::error(503,'unavailable','Operation unavailable; check service health',$rid);}
        return self::error(404,'not_found','Route unavailable',$rid);
    }
    public static function publicJob(array $record):array
    {
        // Never reveal token data, evidence pointers, actor identifiers, or another tenant's data.
        $keys=['id','external_id','title','description','employment_type','work_mode','work_country','employer_country','city','location_text','candidate_eligible_countries','eligibility_statement','apply_url','valid_through','salary_min','salary_max','salary_currency','salary_period','visa_sponsorship','relocation_support','status','revision','created_at','updated_at'];
        $result=array_intersect_key($record,array_flip($keys));
        // Owner-authenticated API must report the *stored* redistribution decision.
        // Missing/legacy data defaults to NO; never infer or grant redistribution.
        $result['distribution_consent']=($record['distribution_consent']??null)===true;
        $result['public_url']=($record['status']??null)==='published'&&($record['public_approved']??false)===true?($record['public_url']??null):null;
        return $result;
    }
}
