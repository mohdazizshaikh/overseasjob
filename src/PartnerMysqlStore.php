<?php
declare(strict_types=1);
require_once __DIR__.'/PartnerApi.php';

/** Isolated MySQL 8+ pilot store. Must be schema-migrated and tested on staging before flags can be enabled.
 *  This adapter stores ONLY first-party employer jobs; provider imports are not queryable here.
 */
final class PartnerMysqlStore implements PartnerRepository
{
    public function __construct(private PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
        $db->exec("SET time_zone = '+00:00'");
    }
    public static function fromEnvironment():self
    {
        if(!extension_loaded('pdo_mysql'))throw new RuntimeException('MySQL driver unavailable');
        $dsn=getenv('PARTNER_DB_DSN');
        if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Database not configured');
        return new self(new PDO($dsn,(string)getenv('PARTNER_DB_USER'),(string)getenv('PARTNER_DB_PASSWORD'),[
           PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5
        ]));
    }
    private static function sqlTime(int $time):string{return gmdate('Y-m-d H:i:s',$time);}
    private function stmt(string $sql,array $parameters=[]):PDOStatement
    {
        $st=$this->db->prepare($sql);
        foreach($parameters as $key=>$v)$st->bindValue(is_int($key)?$key+1:':'.$key,$v,is_int($v)?PDO::PARAM_INT:($v===null?PDO::PARAM_NULL:PDO::PARAM_STR));
        $st->execute();return $st;
    }
    private static function decode(string $json):array
    {
        $value=json_decode($json,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($value))throw new RuntimeException('Invalid persisted partner record');
        return $value;
    }
    public function principal(string $fingerprint,string $prefix,int $now):?array
    {
        $r=$this->stmt('SELECT t.id token_id,t.org_id,t.scopes_json,o.status,o.email_verified_at,o.domain_verified_at,o.approved_at,o.verified_domains_json
          FROM oj_partner_tokens t JOIN oj_employer_orgs o ON o.id=t.org_id
          WHERE t.fingerprint=:fingerprint AND t.token_prefix=:prefix AND t.revoked_at IS NULL AND t.expires_at>:now LIMIT 1',
          ['fingerprint'=>$fingerprint,'prefix'=>$prefix,'now'=>self::sqlTime($now)])->fetch(PDO::FETCH_ASSOC);
        if(!$r)return null;
        $domains=self::decode($r['verified_domains_json']);$scopes=self::decode($r['scopes_json']);
        // Convert only trusted database approval fields, never caller request fields.
        $org=['id'=>$r['org_id'],'approved'=>$r['status']==='approved'&&$r['approved_at']!==null,
          'suspended'=>$r['status']==='suspended','email_verified'=>$r['email_verified_at']!==null,
          'domain_verified'=>$r['domain_verified_at']!==null,'verified_domains'=>$domains];
        return ['token_id'=>(int)$r['token_id'],'org'=>$org,'scopes'=>$scopes];
    }
    public function hitRate(int $tokenId,int $now):int
    {
        $bucket=gmdate('Y-m-d H:i:00',$now);
        $this->stmt('INSERT INTO oj_partner_rate (token_id,minute_utc,requests) VALUES (:token_id,:bucket,1)
          ON DUPLICATE KEY UPDATE requests=requests+1',['token_id'=>$tokenId,'bucket'=>$bucket]);
        return (int)$this->stmt('SELECT requests FROM oj_partner_rate WHERE token_id=:token_id AND minute_utc=:bucket',
          ['token_id'=>$tokenId,'bucket'=>$bucket])->fetchColumn();
    }
    private function load(string $orgId,string $jobId,bool $lock=false):?array
    {
        $r=$this->stmt('SELECT id,org_id,external_id,payload_json,status,revision,created_at,updated_at FROM oj_partner_jobs
             WHERE id=:id AND org_id=:org_id'.($lock?' FOR UPDATE':''),['id'=>$jobId,'org_id'=>$orgId])->fetch(PDO::FETCH_ASSOC);
        if(!$r)return null;
        $data=self::decode($r['payload_json']);
        return ['id'=>$r['id'],'external_id'=>$r['external_id'],'status'=>$r['status'],'revision'=>(int)$r['revision'],
            'created_at'=>gmdate('Y-m-d\TH:i:s\Z',strtotime($r['created_at'].' UTC')),
            'updated_at'=>gmdate('Y-m-d\TH:i:s\Z',strtotime($r['updated_at'].' UTC'))]+$data;
    }
    private function audit(string $orgId,string $jobId,string $action,int $now):void
    {
        $this->stmt('INSERT INTO oj_partner_audit (org_id,job_id,action,occurred_at) VALUES (:org_id,:job_id,:action,:at)',
          ['org_id'=>$orgId,'job_id'=>$jobId,'action'=>$action,'at'=>self::sqlTime($now)]);
    }
    public function create(array $org,array $job,string $key,string $requestHash,int $now):array
    {
        $orgId=$org['id'];$this->db->beginTransaction();
        try {
            // Serialize all creates for this organisation to enforce daily quotas and idempotency.
            $this->stmt('SELECT id FROM oj_employer_orgs WHERE id=:id AND status=\'approved\' FOR UPDATE',['id'=>$orgId])->fetchColumn()
                ?:throw new DomainException('Organisation approval revoked');
            $prev=$this->stmt('SELECT request_hash,response_json FROM oj_partner_idempotency
                WHERE org_id=:org AND request_key=:req FOR UPDATE',['org'=>$orgId,'req'=>$key])->fetch(PDO::FETCH_ASSOC);
            if($prev){
                if(!hash_equals($prev['request_hash'],$requestHash))throw new PartnerConflict('Key reused with different payload');
                $record=self::decode($prev['response_json']);$this->db->commit();
                return ['record'=>$record,'replayed'=>true];
            }
            if($this->stmt('SELECT 1 FROM oj_partner_jobs WHERE org_id=:org AND external_id=:ext LIMIT 1',
                    ['org'=>$orgId,'ext'=>$job['external_id']])->fetchColumn())throw new PartnerConflict('Duplicate organisation external ID');
            $today=gmdate('Y-m-d 00:00:00',$now);
            $created=(int)$this->stmt('SELECT COUNT(*) FROM oj_partner_jobs WHERE org_id=:org AND created_at>=:today',
                ['org'=>$orgId,'today'=>$today])->fetchColumn();
            if($created>=10)throw new DomainException('Pilot daily posting limit reached');
            $id='job_'.bin2hex(random_bytes(12));$at=self::sqlTime($now);
            $record=['id'=>$id,'created_at'=>gmdate('Y-m-d\TH:i:s\Z',$now),'updated_at'=>gmdate('Y-m-d\TH:i:s\Z',$now)]+$job;
            $this->stmt('INSERT INTO oj_partner_jobs(id,org_id,external_id,payload_json,status,revision,valid_through,created_at,updated_at)
                VALUES(:id,:org,:ext,:payload,\'pending_review\',1,:expiry,:created,:updated)',[
                'id'=>$id,'org'=>$orgId,'ext'=>$job['external_id'],
                'payload'=>json_encode($job,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
                'expiry'=>gmdate('Y-m-d H:i:s',strtotime($job['valid_through'])),'created'=>$at,'updated'=>$at]);
            $this->stmt('INSERT INTO oj_partner_idempotency(org_id,request_key,request_hash,response_json,created_at)
                VALUES(:org,:req,:hash,:body,:at)',[
                'org'=>$orgId,'req'=>$key,'hash'=>$requestHash,
                'body'=>json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),'at'=>$at]);
            $this->audit($orgId,$id,'created_pending_review',$now);
            $this->db->commit();return ['record'=>$record,'replayed'=>false];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function listJobs(string $orgId,int $limit,?string $cursor,?string $status,?string $externalId):array
    {
        $sql='SELECT id FROM oj_partner_jobs WHERE org_id=:org';$p=['org'=>$orgId];
        if($cursor!==null){$sql.=' AND id>:cursor';$p['cursor']=$cursor;}
        if($status!==null){$sql.=' AND status=:status';$p['status']=$status;}
        if($externalId!==null){$sql.=' AND external_id=:ext';$p['ext']=$externalId;}
        $sql.=' ORDER BY id ASC LIMIT :take';$p['take']=$limit+1;
        $ids=$this->stmt($sql,$p)->fetchAll(PDO::FETCH_COLUMN);
        $hasMore=count($ids)>$limit;$ids=array_slice($ids,0,$limit);
        $items=[];foreach($ids as $id){$r=$this->getJob($orgId,$id);if($r)$items[]=PartnerApi::publicJob($r);}
        return ['items'=>$items,'next_cursor'=>$hasMore?end($ids):null];
    }
    public function getJob(string $orgId,string $jobId):?array{return $this->load($orgId,$jobId);}
    public function patchJob(array $org,string $jobId,array $patch,int $revision,int $now,string $terms):?array
    {
        $this->db->beginTransaction();
        try{
            $existing=$this->load($org['id'],$jobId,true);
            if($existing===null){$this->db->commit();return null;}
            if($existing['revision']!==$revision)throw new PartnerConflict('Revision conflict');
            $candidate=EmployerContracts::validatePatch($existing,$patch,$org,$now,$terms,$revision);
            $at=self::sqlTime($now);
            $this->stmt('UPDATE oj_partner_jobs SET payload_json=:body,status=\'pending_review\',revision=:revision,valid_through=:expiry,updated_at=:updated
               WHERE id=:id AND org_id=:org AND revision=:old',[ 'body'=>json_encode($candidate,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
               'revision'=>$revision+1,'expiry'=>gmdate('Y-m-d H:i:s',strtotime($candidate['valid_through'])),
               'updated'=>$at,'id'=>$jobId,'org'=>$org['id'],'old'=>$revision]);
            $this->audit($org['id'],$jobId,'edited_back_to_review',$now);
            $this->db->commit();return $this->getJob($org['id'],$jobId);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function closeJob(string $orgId,string $jobId,int $now):?array
    {
        $this->db->beginTransaction();
        try{
            $r=$this->load($orgId,$jobId,true);
            if($r===null){$this->db->commit();return null;}
            if($r['status']!=='closed'){
                $this->stmt('UPDATE oj_partner_jobs SET status=\'closed\',revision=revision+1,updated_at=:at WHERE id=:id AND org_id=:org',
                  ['at'=>self::sqlTime($now),'id'=>$jobId,'org'=>$orgId]);
                $this->audit($orgId,$jobId,'closed',$now);
            }
            $this->db->commit();return $this->getJob($orgId,$jobId);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function usage(string $orgId,int $tokenId,int $now):array
    {
        $minute=gmdate('Y-m-d H:i:00',$now);$today=gmdate('Y-m-d 00:00:00',$now);
        $requests=(int)$this->stmt('SELECT COALESCE(MAX(requests),0) FROM oj_partner_rate WHERE token_id=:token AND minute_utc=:minute',
            ['token'=>$tokenId,'minute'=>$minute])->fetchColumn();
        $created=(int)$this->stmt('SELECT COUNT(*) FROM oj_partner_jobs WHERE org_id=:org AND created_at>=:today',
            ['org'=>$orgId,'today'=>$today])->fetchColumn();
        return ['requests_this_minute'=>$requests,'request_limit_per_minute'=>60,
            'new_jobs_today'=>$created,'max_new_jobs_per_day'=>10];
    }
}
