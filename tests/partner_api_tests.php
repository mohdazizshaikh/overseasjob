<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/PartnerApi.php';
require dirname(__DIR__).'/src/PartnerMysqlStore.php';
require dirname(__DIR__).'/src/PartnerTokenAdmin.php';
function testP(string $name,bool $ok):void {global $n;$n++;if(!$ok){fwrite(STDERR,"FAIL $name\n");exit(1);}echo "PASS $name\n";}
final class MemoryPartnerStore implements PartnerRepository
{
    public array $orgs=[];public array $tokens=[];public array $jobs=[];public array $keys=[];public array $rates=[];
    public function principal(string $fingerprint,string $prefix,int $now):?array{
        foreach($this->tokens as $t){if(hash_equals($t['fingerprint'],$fingerprint)&&$t['prefix']===$prefix&&$t['expires']>$now&&!$t['revoked'])
            return ['org'=>$this->orgs[$t['org']],'token_id'=>$t['id'],'scopes'=>$t['scopes']];}return null;
    }
    public function hitRate(int $tokenId,int $now):int{$key=$tokenId.'-'.intdiv($now,60);return $this->rates[$key]=($this->rates[$key]??0)+1;}
    public function create(array $org,array $job,string $key,string $requestHash,int $now):array{
        $key=$org['id'].':'.$key;
        if(isset($this->keys[$key])){
            if($this->keys[$key]['hash']!==$requestHash)throw new PartnerConflict();
            return ['record'=>$this->keys[$key]['record'],'replayed'=>true];
        }
        foreach($this->jobs as $j)if($j['owner_org_id']===$org['id']&&$j['external_id']===$job['external_id'])throw new PartnerConflict();
        $id='job_'.str_pad(dechex(count($this->jobs)+1),24,'0',STR_PAD_LEFT);
        $record=['id'=>$id,'created_at'=>gmdate('Y-m-d\TH:i:s\Z',$now),'updated_at'=>gmdate('Y-m-d\TH:i:s\Z',$now)]+$job;
        $this->jobs[$id]=$record;$this->keys[$key]=['hash'=>$requestHash,'record'=>$record];
        return ['record'=>$record,'replayed'=>false];
    }
    public function listJobs(string $orgId,int $limit,?string $cursor,?string $status,?string $externalId):array{
        $jobs=array_values(array_filter($this->jobs,fn($r)=>$r['owner_org_id']===$orgId&&($cursor===null||$r['id']>$cursor)&&($status===null||$r['status']===$status)&&($externalId===null||$r['external_id']===$externalId)));
        usort($jobs,fn($a,$b)=>strcmp($a['id'],$b['id']));$more=count($jobs)>$limit;$jobs=array_slice($jobs,0,$limit);
        return ['items'=>array_map([PartnerApi::class,'publicJob'],$jobs),'next_cursor'=>$more?end($jobs)['id']:null];
    }
    public function getJob(string $orgId,string $jobId):?array{ $j=$this->jobs[$jobId]??null;return $j&&$j['owner_org_id']===$orgId?$j:null;}
    public function patchJob(array $org,string $jobId,array $patch,int $revision,int $now,string $terms):?array{
        $old=$this->getJob($org['id'],$jobId);if(!$old)return null;
        if($old['revision']!==$revision)throw new PartnerConflict();
        $new=EmployerContracts::validatePatch($old,$patch,$org,$now,$terms,$revision);
        $this->jobs[$jobId]=['id'=>$jobId,'created_at'=>$old['created_at'],'updated_at'=>gmdate('Y-m-d\TH:i:s\Z',$now)]+$new;
        return $this->jobs[$jobId];
    }
    public function closeJob(string $orgId,string $jobId,int $now):?array{
        $old=$this->getJob($orgId,$jobId);if(!$old)return null;
        if($old['status']!=='closed'){$old['status']='closed';$old['revision']++;$old['updated_at']=gmdate('Y-m-d\TH:i:s\Z',$now);$this->jobs[$jobId]=$old;}return $old;
    }
    public function usage(string $orgId,int $tokenId,int $now):array{return ['requests_this_minute'=>$this->rates[$tokenId.'-'.intdiv($now,60)]??0,'request_limit_per_minute'=>60,'new_jobs_today'=>count(array_filter($this->jobs,fn($j)=>$j['owner_org_id']===$orgId)),'max_new_jobs_per_day'=>10];}
}
$n=0;$time=strtotime('2026-11-23T08:00:00Z');$secret=str_repeat('P',48);
$token=EmployerContracts::newOpaqueToken();$store=new MemoryPartnerStore;
$org=['id'=>'org_demo1','approved'=>true,'email_verified'=>true,'domain_verified'=>true,'verified_domains'=>['example.org'],'suspended'=>false];
$store->orgs[$org['id']]=$org;
$store->tokens[]=['id'=>1,'org'=>'org_demo1','prefix'=>substr($token,0,12),'fingerprint'=>EmployerContracts::tokenFingerprint($token,$secret),'scopes'=>['jobs:read','jobs:write','jobs:close'],'expires'=>$time+86400,'revoked'=>false];
$hdr=['authorization'=>'Bearer '.$token,'content-type'=>'application/json','idempotency-key'=>'safe-key-001'];
$https=['HTTPS'=>'on','REMOTE_ADDR'=>'203.0.113.1'];
$req=static fn(string $method,string $path,array $headers=[],string $body='',array $query=[],array $server=[])=>PartnerApi::dispatch($method,$path,$headers?:$hdr,$body,$query,$server?:$https,$store,$time,$secret,'terms-v1');
$valid=['external_id'=>'role-001','title'=>'Technical Support Specialist','description'=>'Provide customer troubleshooting and technical support for authorised products.','employment_type'=>'full_time','work_mode'=>'remote','location_text'=>'Remote from selected countries','candidate_eligible_countries'=>['IN'],'apply_url'=>'https://careers.example.org/apply/job-001','valid_through'=>gmdate('Y-m-d\TH:i:s\Z',$time+20*86400),'consent_to_publish'=>true];
$json=json_encode($valid,JSON_THROW_ON_ERROR);
testP('HTTPS enforced',$req('GET','/api/v1/partner/jobs',[], '',[],['REMOTE_ADDR'=>'1.1.1.1'])['status']===503);
testP('Unknown route never enumerates service',$req('GET','/api/v1/partner/secrets')['status']===404);
testP('No anonymous API access',$req('GET','/api/v1/partner/jobs',['authorization'=>''])['status']===401);
testP('Wrong bearer rejected',$req('GET','/api/v1/partner/jobs',['authorization'=>'Bearer '.str_repeat('0',64)])['status']===401);
testP('Unexpected token format rejected',$req('GET','/api/v1/partner/jobs',['authorization'=>'Basic secret'])['status']===401);
$unapproved=$org;$unapproved['approved']=false;$store->orgs['org_demo1']=$unapproved;
testP('Unapproved org cannot use valid token',$req('GET','/api/v1/partner/jobs')['status']===403);
$store->orgs['org_demo1']=$org;
$store->tokens[0]['scopes']=['jobs:read'];testP('Missing write scope rejected',$req('POST','/api/v1/partner/jobs',[],$json)['status']===403);
$store->tokens[0]['scopes']=['jobs:read','jobs:write','jobs:close'];
testP('Payload size cap',$req('POST','/api/v1/partner/jobs',[],str_repeat('X',32769))['status']===413);
testP('Content type check',$req('POST','/api/v1/partner/jobs',['authorization'=>'Bearer '.$token,'content-type'=>'text/plain','idempotency-key'=>'safe-key-001'],$json)['status']===415);
testP('Reject malformed JSON',$req('POST','/api/v1/partner/jobs',[],'{bad')['status']===422);
testP('Reject empty body',$req('POST','/api/v1/partner/jobs')['status']===400);
testP('Invalid idempotency key',$req('POST','/api/v1/partner/jobs',['authorization'=>'Bearer '.$token,'content-type'=>'application/json','idempotency-key'=>'x'],$json)['status']===422);
testP('Reject unverified arbitrary apply URL',$req('POST','/api/v1/partner/jobs',[],json_encode([...$valid,'apply_url'=>'https://evil.example.net/apply']))['status']===422);
testP('Reject mass-assigned publish status',$req('POST','/api/v1/partner/jobs',[],json_encode([...$valid,'status'=>'published']))['status']===422);
$created=$req('POST','/api/v1/partner/jobs',[],$json);
testP('Create returns 201 pending_review',$created['status']===201&&$created['body']['status']==='pending_review');
testP('No public URL on unreviewed job',array_key_exists('public_url',$created['body'])&&$created['body']['public_url']===null);
testP('Unreviewed job lacks fake certification',!array_key_exists('approved',$created['body'])&&!array_key_exists('owner_org_id',$created['body']));
testP('Job revision ETag',$created['headers']['ETag']==='"1"');
$id=$created['body']['id'];
$repeat=$req('POST','/api/v1/partner/jobs',[],$json);
testP('Idempotency replay responds 200',$repeat['status']===200&&$repeat['body']['id']===$id);
$reordered=array_reverse($valid,true);
testP('Canonical hash ignores JSON object field ordering',PartnerApi::canonicalHash($valid)===PartnerApi::canonicalHash($reordered));
testP('Same idempotency key different body conflicts',$req('POST','/api/v1/partner/jobs',[],json_encode([...$valid,'title'=>'Senior Technical Support Specialist']))['status']===409);
testP('New key cannot reuse external_id',$req('POST','/api/v1/partner/jobs',[...$hdr,'idempotency-key'=>'safe-key-002'],$json)['status']===409);
$gets=$req('GET','/api/v1/partner/jobs/'.$id);
testP('GET own listing',$gets['status']===200&&$gets['body']['id']===$id);
testP('List only own direct jobs',count($req('GET','/api/v1/partner/jobs')['body']['items'])===1);
testP('Exact external ID search',$req('GET','/api/v1/partner/jobs',[],'',['external_id'=>'role-001'])['body']['items'][0]['id']===$id);
testP('Unknown external ID returns empty',$req('GET','/api/v1/partner/jobs',[],'',['external_id'=>'not-found'])['body']['items']===[]);
testP('Unknown job returns 404',$req('GET','/api/v1/partner/jobs/job_'.str_repeat('a',24))['status']===404);
testP('Malformed list cursor denied',$req('GET','/api/v1/partner/jobs',[],'',['cursor'=>'<bad>'])['status']===422);
testP('Malformed limit denied',$req('GET','/api/v1/partner/jobs',[],'',['limit'=>'101'])['status']===422);
testP('Unexpected filter denied',$req('GET','/api/v1/partner/jobs',[],'',['include_provider'=>'1'])['status']===422);
$other=$org;$other['id']='org_other1';$store->orgs['org_other1']=$other;
$store->tokens[0]['org']='org_other1';
testP('Cross-tenant GET stays 404',$req('GET','/api/v1/partner/jobs/'.$id)['status']===404);
testP('Cross-tenant list returns no jobs',$req('GET','/api/v1/partner/jobs')['body']['items']===[]);
testP('Cross-tenant PATCH stays 404',$req('PATCH','/api/v1/partner/jobs/'.$id,[...$hdr,'if-match'=>'"1"'],json_encode(['title'=>'Other Org Edit']))['status']===404);
testP('Cross-tenant CLOSE stays 404',$req('POST','/api/v1/partner/jobs/'.$id.'/close')['status']===404);
$store->tokens[0]['org']='org_demo1';
testP('PATCH requires If-Match',$req('PATCH','/api/v1/partner/jobs/'.$id,[],'{"title":"New title"}')['status']===422);
testP('PATCH rejects protected fields',$req('PATCH','/api/v1/partner/jobs/'.$id,[...$hdr,'if-match'=>'"1"'],json_encode(['owner_org_id'=>'org_other1']))['status']===422);
$patched=$req('PATCH','/api/v1/partner/jobs/'.$id,[...$hdr,'if-match'=>'"1"'],json_encode(['title'=>'Senior Technical Support Specialist']));
testP('PATCH stays pending review',$patched['status']===200&&$patched['body']['status']==='pending_review');
testP('PATCH increments revision',$patched['body']['revision']===2&&$patched['headers']['ETag']==='"2"');
testP('Old revision rejected',$req('PATCH','/api/v1/partner/jobs/'.$id,[...$hdr,'if-match'=>'"1"'],json_encode(['title'=>'Stale Update']))['status']===409);
testP('Usage counters do not reveal tenant data',$req('GET','/api/v1/partner/usage')['body']['max_new_jobs_per_day']===10);
$closed=$req('POST','/api/v1/partner/jobs/'.$id.'/close');
testP('Close works without JSON body',$closed['status']===200&&$closed['body']['status']==='closed');
testP('Close is idempotent',$req('POST','/api/v1/partner/jobs/'.$id.'/close')['body']['revision']===$closed['body']['revision']);
testP('Closed job cannot be edited',$req('PATCH','/api/v1/partner/jobs/'.$id,[...$hdr,'if-match'=>'"'.$closed['body']['revision'].'"'],json_encode(['title'=>'Reopened without review']))['status']===403);
$store->tokens[0]['revoked']=true;
testP('Revoked token rejected',$req('GET','/api/v1/partner/jobs')['status']===401);
$store->tokens[0]['revoked']=false;$store->tokens[0]['expires']=$time-1;
testP('Expired token rejected',$req('GET','/api/v1/partner/jobs')['status']===401);
$store->tokens[0]['expires']=$time+86400;
$store->rates['1-'.intdiv($time,60)]=60;
$throttled=$req('GET','/api/v1/partner/jobs');
testP('Rate limit returns 429',$throttled['status']===429&&$throttled['headers']['Retry-After']==='60');
testP('Invalid crypto secret fails closed',PartnerApi::dispatch('GET','/api/v1/partner/jobs',$hdr,'',[],$https,$store,$time,'short','terms-v1')['status']===503);
testP('Public fields whitelist prevents internal leak',!array_key_exists('fingerprint',PartnerApi::publicJob(['fingerprint'=>'secret','title'=>'Example','status'=>'pending_review'])));
// SQL and operational test: actual MySQL integration is EXTERNAL and cannot be claimed as tested here.
$schema=file_get_contents(dirname(__DIR__).'/database/001_partner_pilot.mysql.sql');
testP('MySQL DDL tenant ownership',str_contains($schema,'UNIQUE KEY uq_partner_org_external')&&str_contains($schema,'FOREIGN KEY(org_id)'));
testP('MySQL DDL hashed-only token',str_contains($schema,'fingerprint CHAR(64)')&&!str_contains($schema,'secret_plaintext'));
testP('MySQL DDL supports transactional idempotency',str_contains($schema,'PRIMARY KEY(org_id,request_key)'));
testP('No public token issuance route',!str_contains(file_get_contents(dirname(__DIR__).'/public/index.php'),'/api/v1/partner/issue-token'));
testP('Public API feature remains OFF by default',getenv('PARTNER_POSTING_API_ENABLED')!=='1');
echo "\n$n partner API / tenant / security checks passed (in-memory contract; MySQL staging unverified).\n";
