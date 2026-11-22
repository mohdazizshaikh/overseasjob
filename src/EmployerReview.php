<?php
declare(strict_types=1);
require_once __DIR__.'/PartnerApi.php';

/** Human-controlled first-party publication. Never accepts an API bearer as an owner decision. */
final class EmployerReview
{
    public static function reviewEnabled():bool
    {
        return getenv('EMPLOYER_REVIEW_ENABLED')==='1' && getenv('RECRUITER_LITE_ENABLED')==='1'
            && getenv('OWNER_PANEL_ENABLED')==='1' && extension_loaded('pdo_mysql')
            && strlen((string)getenv('PARTNER_DB_DSN'))>6;
    }
    public static function publicEnabled():bool
    {
        return self::reviewEnabled() && getenv('EMPLOYER_PUBLIC_ENABLED')==='1'
            && getenv('PARTNER_PILOT_VERIFIED')==='1';
    }
    public static function validOrg(array $org):bool
    {
        $domains=$org['verified_domains_json']??null;
        if(is_string($domains)){
            $domains=json_decode($domains,true);
        }
        if(!is_array($domains)||!array_is_list($domains)||$domains===[])return false;
        foreach($domains as $d)if(!is_string($d)||!preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/D',$d))return false;
        return isset($org['id'],$org['company_name']) && preg_match('/^org_[A-Za-z0-9_-]{4,64}$/D',(string)$org['id'])===1
            &&is_string($org['company_name'])&&trim($org['company_name'])!==''
            &&($org['status']??'')==='approved'&&($org['email_verified_at']??null)!==null
            &&($org['domain_verified_at']??null)!==null&&($org['representative_verified_at']??null)!==null
            &&($org['approved_at']??null)!==null && trim((string)($org['approved_by']??''))!==''
            &&trim((string)($org['evidence_reference']??''))!=='';
    }
    public static function decision(array $record,array $org,string $action,int $revision,string $reason,int $now):array
    {
        if(!in_array($action,['publish','reject','suspend'],true))throw new InvalidArgumentException('Unsupported review decision');
        if(!is_string($reason)||strlen($reason)<12||strlen($reason)>500)throw new InvalidArgumentException('Review reason must be 12–500 characters');
        if(!preg_match('/^job_[a-f0-9]{24}$/D',(string)($record['id']??'')))throw new DomainException('Not a first-party job');
        if(($record['origin_type']??'')!=='direct_employer'||($record['owner_org_id']??'')!==($org['id']??null))throw new DomainException('Job not owned by this employer');
        if((int)($record['revision']??0)!==$revision||$revision<1)throw new PartnerConflict('Revision conflict');
        if(($record['status']??'')!=='pending_review')throw new DomainException('Only pending review listings can be decided');
        if(($record['source_policy_id']??'')!=='first_party_employer_pending_review')throw new DomainException('Provider jobs cannot use employer review');
        // Owner may reject or suspend an unsafe listing even if the organisation has lost verification.
        if($action==='publish'){
            if(!self::validOrg($org))throw new DomainException('Independent employer verification evidence missing');
            if(($record['publish_consent']??false)!==true)throw new DomainException('Employer has not consented');
            if(!is_string($record['valid_through']??null)||strtotime((string)$record['valid_through'])<=$now)throw new DomainException('Job has expired');
            if(Jobs::safeUrl($record['apply_url']??null)===null)throw new DomainException('Unsafe apply URL');
            $host=strtolower((string)parse_url($record['apply_url'],PHP_URL_HOST));
            $domains=$org['verified_domains_json'];$domains=is_string($domains)?json_decode($domains,true):$domains;
            $controlled=false;
            foreach($domains as $d)if($host===$d||str_ends_with($host,'.'.$d)){$controlled=true;break;}
            if(!$controlled)throw new DomainException('Application hostname is not independently verified');
        }
        $record['status']=match($action){'publish'=>'published','reject'=>'rejected','suspend'=>'suspended'};
        $record['revision']=$revision+1;
        $record['updated_at']=gmdate('Y-m-d\TH:i:s\Z',$now);
        return $record;
    }
    public static function publicProjection(array $record,array $org,int $now):?array
    {
        if(!self::validOrg($org)||($record['review_audit_confirmed']??false)!==true
          ||!in_array(($record['source_policy_id']??''),['first_party_employer_pending_review','first_party_employer_owned'],true)
          ||($record['origin_type']??'')!=='direct_employer'
          ||($record['owner_org_id']??'')!==$org['id']||($record['status']??'')!=='published'
          ||($record['publish_consent']??false)!==true||($record['display_status']??'visible')!=='visible'
          ||!is_string($record['valid_through']??null)||strtotime($record['valid_through'])<=$now)return null;
        $id=(string)($record['id']??'');
        if(!preg_match('/^job_[a-f0-9]{24}$/D',$id)||Jobs::safeUrl($record['apply_url']??null)===null)return null;
        $domains=$org['verified_domains_json'];$domains=is_string($domains)?json_decode($domains,true):$domains;
        $host=strtolower((string)parse_url($record['apply_url'],PHP_URL_HOST));$owned=false;
        foreach($domains as $d)if($host===$d||str_ends_with($host,'.'.$d)){$owned=true;break;}
        if(!$owned)return null;
        // This is only a narrow public view. No employer emails, identity evidence, tokens, audit notes or applicant data.
        return ['id'=>$id,'title'=>(string)($record['title']??''),'company_name'=>$org['company_name'],
          'description'=>(string)($record['description']??''),'work_mode'=>$record['work_mode']??'unknown',
          'work_country'=>$record['work_country']??null,'location_text'=>$record['location_text']??'',
          'candidate_eligible_countries'=>$record['candidate_eligible_countries']??[],
          'visa_sponsorship'=>$record['visa_sponsorship']??'unknown','salary_min'=>$record['salary_min']??null,
          'salary_max'=>$record['salary_max']??null,'salary_currency'=>$record['salary_currency']??null,
          'salary_period'=>$record['salary_period']??null,'apply_url'=>$record['apply_url'],
          'valid_through'=>$record['valid_through'],'source_name'=>'Direct employer submission',
          'origin_type'=>'direct_employer','source_policy_id'=>'first_party_employer_owned',
          'public_url'=>'/jobs/direct/'.$id];
    }
}

/** MySQL production adapter; no automated reviews, all transitions audited transactionally. */
final class EmployerReviewMysql
{
    private function __construct(private PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
        $db->exec("SET time_zone = '+00:00'");
    }
    public static function fromEnvironment():self
    {
        if(!extension_loaded('pdo_mysql'))throw new RuntimeException('MySQL unavailable');
        $dsn=(string)getenv('PARTNER_DB_DSN');
        if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Pilot DB unavailable');
        return new self(new PDO($dsn,(string)getenv('PARTNER_DB_USER'),(string)getenv('PARTNER_DB_PASSWORD'),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]));
    }
    private function fetch(string $sql,array $values):array
    {
        $stmt=$this->db->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private static function rowJob(array $row):array
    {
        $payload=json_decode((string)$row['payload_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($payload))throw new RuntimeException('Invalid job payload');
        return ['id'=>$row['id'],'revision'=>(int)$row['revision'],'status'=>$row['status'],
            'review_audit_confirmed'=>((int)($row['human_publish_review']??0))===1]+$payload;
    }
    private static function rowOrg(array $row):array
    {
        return ['id'=>$row['org_id'],'company_name'=>$row['company_name'],'status'=>$row['org_status'],
          'email_verified_at'=>$row['email_verified_at'],'domain_verified_at'=>$row['domain_verified_at'],
          'representative_verified_at'=>$row['representative_verified_at'],
          'approved_at'=>$row['approved_at'],'approved_by'=>$row['approved_by'],
          'evidence_reference'=>$row['evidence_reference'],'verified_domains_json'=>$row['verified_domains_json']];
    }
    private const JOINS=' FROM oj_partner_jobs j INNER JOIN oj_employer_orgs o ON o.id=j.org_id ';
    private const COLS='SELECT j.id,j.org_id,j.status,j.revision,j.payload_json,j.valid_through,o.company_name,o.status org_status,o.email_verified_at,o.domain_verified_at,o.representative_verified_at,o.approved_at,o.approved_by,o.evidence_reference,o.verified_domains_json, EXISTS(SELECT 1 FROM oj_partner_review_audit a WHERE a.job_id=j.id AND a.new_revision=j.revision AND a.decision=\'publish\') AS human_publish_review ';
    public function pending(int $limit=50):array
    {
        if($limit<1||$limit>100)throw new InvalidArgumentException('Invalid limit');
        $rows=$this->fetch(self::COLS.self::JOINS.' WHERE j.status=\'pending_review\' ORDER BY j.created_at ASC LIMIT '.$limit,[]);
        $result=[];foreach($rows as $row){$result[]=['job'=>self::rowJob($row),'org'=>self::rowOrg($row)];}return $result;
    }
    public function review(string $jobId,int $revision,string $action,string $reason,string $actor,int $now):array
    {
        if(!preg_match('/^job_[a-f0-9]{24}$/D',$jobId))throw new InvalidArgumentException('Invalid job ID');
        if(!preg_match('/^[A-Za-z0-9_.@-]{3,90}$/D',$actor))throw new InvalidArgumentException('Invalid reviewer');
        if(!in_array($action,['publish','reject','suspend'],true)||strlen($reason)<12||strlen($reason)>500)throw new InvalidArgumentException('Invalid review');
        $this->db->beginTransaction();
        try{
            $rows=$this->fetch(self::COLS.self::JOINS.' WHERE j.id=:id FOR UPDATE',['id'=>$jobId]);
            if(count($rows)!==1)throw new PartnerNotFound('No direct job');
            $row=$rows[0];$job=EmployerReview::decision(self::rowJob($row),self::rowOrg($row),$action,$revision,$reason,$now);
            $stmt=$this->db->prepare('UPDATE oj_partner_jobs SET status=:status,revision=:next,updated_at=:updated WHERE id=:id AND org_id=:org AND revision=:old AND status=\'pending_review\'');
            $stmt->execute(['status'=>$job['status'],'next'=>$job['revision'],'updated'=>gmdate('Y-m-d H:i:s',$now),
              'id'=>$jobId,'org'=>$row['org_id'],'old'=>$revision]);
            if($stmt->rowCount()!==1)throw new PartnerConflict('Review revision race');
            $stmt=$this->db->prepare('INSERT INTO oj_partner_review_audit (org_id,job_id,old_revision,new_revision,decision,reason,actor,decided_at) VALUES (:org,:job,:old,:next,:decision,:reason,:actor,:at)');
            $stmt->execute(['org'=>$row['org_id'],'job'=>$jobId,'old'=>$revision,'next'=>$job['revision'],
                'decision'=>$action,'reason'=>$reason,'actor'=>$actor,'at'=>gmdate('Y-m-d H:i:s',$now)]);
            $this->db->commit();return $job;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    /** Expire jobs fail-closed for public reads even when cron is unavailable. */
    public function expire(int $now,int $limit=500):int
    {
        if($limit<1||$limit>500)throw new InvalidArgumentException('Invalid limit');
        $sql='SELECT id,org_id FROM oj_partner_jobs WHERE status IN (\'published\',\'pending_review\') AND valid_through<=:now ORDER BY valid_through ASC LIMIT '.$limit.' FOR UPDATE';
        $this->db->beginTransaction();
        try{
            $rows=$this->fetch($sql,['now'=>gmdate('Y-m-d H:i:s',$now)]);
            $q=$this->db->prepare('UPDATE oj_partner_jobs SET status=\'expired\',revision=revision+1,updated_at=:now WHERE id=:id AND org_id=:org');
            $a=$this->db->prepare('INSERT INTO oj_partner_audit(org_id,job_id,action,occurred_at) VALUES (:org,:id,\'expired_by_system\',:now)');
            foreach($rows as $r){$args=['org'=>$r['org_id'],'id'=>$r['id'],'now'=>gmdate('Y-m-d H:i:s',$now)];$q->execute($args);$a->execute($args);}
            $this->db->commit();return count($rows);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function published(int $now,int $limit=60,?string $id=null):array
    {
        if($limit<1||$limit>100)throw new InvalidArgumentException('Invalid limit');
        $sql=self::COLS.self::JOINS.' WHERE j.status=\'published\' AND j.valid_through>:now';
        $params=['now'=>gmdate('Y-m-d H:i:s',$now)];
        if($id!==null){if(!preg_match('/^job_[a-f0-9]{24}$/D',$id))return [];$sql.=' AND j.id=:id';$params['id']=$id;}
        $sql.=' ORDER BY j.created_at DESC LIMIT '.$limit;
        $result=[];foreach($this->fetch($sql,$params) as $row){$job=EmployerReview::publicProjection(self::rowJob($row),self::rowOrg($row),$now);if($job!==null)$result[]=$job;}
        return $result;
    }
}
