<?php
declare(strict_types=1);
require_once __DIR__.'/EmployerReview.php';
require_once __DIR__.'/PartnerMysqlStore.php';
/** Invite-only browser employer account; no candidate sessions or social login. */
final class RecruiterPortal
{
    public static function enabled():bool
    {
        return getenv('RECRUITER_PORTAL_ENABLED')==='1' && getenv('RECRUITER_LITE_ENABLED')==='1'
           && getenv('PARTNER_PILOT_VERIFIED')==='1' && extension_loaded('pdo_mysql')
           && str_starts_with((string)getenv('PARTNER_DB_DSN'),'mysql:')
           && strlen((string)getenv('RECRUITER_AUTH_HMAC_SECRET'))>=32;
    }
    public static function validEmail(string $email):bool
    {
        return strlen($email)<=254&&strlen($email)>5&&$email===strtolower($email)
            &&filter_var($email,FILTER_VALIDATE_EMAIL)!==false;
    }
    public static function newInviteCode():string
    {
        return (string)random_int(10000000,99999999);
    }
    public static function codeHash(int $memberId,string $code,string $secret):string
    {
        if($memberId<1||!preg_match('/^[0-9]{8}$/D',$code)||strlen($secret)<32)throw new InvalidArgumentException('Invalid email challenge');
        return hash_hmac('sha256',$memberId.':'.$code,$secret);
    }
    public static function validateNewPassword(string $password):void
    {
        if(strlen($password)<12||strlen($password)>256||!preg_match('/[A-Z]/',$password)
          ||!preg_match('/[a-z]/',$password)||!preg_match('/[0-9]/',$password))throw new InvalidArgumentException('Use a strong 12–256-character password');
    }
    public static function mayManage(array $member):bool
    {
        return ($member['status']??'')==='active' &&($member['email_verified_at']??null)!==null
            &&in_array($member['role']??null,['admin','manager'],true)
            &&EmployerReview::validOrg($member['org']??[]);
    }
    public static function safeDashboardRow(array $job):array
    {
        return PartnerApi::publicJob($job);
    }
}
/** Staging adapter; uses no raw reusable API tokens inside the browser. */
final class RecruiterMysql
{
    private PDO $db;
    public function __construct(PDO $db)
    {
        $this->db=$db;
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
        $db->exec("SET time_zone = '+00:00'");
    }
    public static function fromEnvironment():self
    {
        if(!extension_loaded('pdo_mysql'))throw new RuntimeException('MySQL driver unavailable');
        $dsn=(string)getenv('PARTNER_DB_DSN');
        if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Employer DB unavailable');
        return new self(new PDO($dsn,(string)getenv('PARTNER_DB_USER'),(string)getenv('PARTNER_DB_PASSWORD'),
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]));
    }
    private function rows(string $sql,array $p):array
    {
        $stmt=$this->db->prepare($sql);$stmt->execute($p);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function memberByEmail(string $email,bool $lock=false):?array
    {
        $r=$this->rows('SELECT * FROM oj_employer_members WHERE email=:email'.($lock?' FOR UPDATE':''),['email'=>$email]);
        return $r[0]??null;
    }
    private function memberById(int $memberId):?array
    {
        $r=$this->rows('SELECT * FROM oj_employer_members WHERE id=:id',['id'=>$memberId]);return $r[0]??null;
    }
    private function org(string $orgId):?array
    {
        $r=$this->rows('SELECT * FROM oj_employer_orgs WHERE id=:id',['id'=>$orgId]);return $r[0]??null;
    }
    /** Trusted private operator only, needs independent organisation identity before invitation. */
    public function invite(string $orgId,string $email,callable $deliver,int $now):bool
    {
        if(!preg_match('/^org_[A-Za-z0-9_-]{4,64}$/D',$orgId)||!RecruiterPortal::validEmail($email))throw new InvalidArgumentException('Invalid organisation or email');
        $org=$this->org($orgId);if($org===null||!EmployerReview::validOrg($org))throw new DomainException('Employer not independently verified');
        $secret=(string)getenv('RECRUITER_AUTH_HMAC_SECRET');if(strlen($secret)<32)throw new RuntimeException('Auth secret unavailable');
        $this->db->beginTransaction();
        try{
            $member=$this->memberByEmail($email,true);
            if($member!==null &&($member['org_id']!==$orgId||$member['status']!=='invited'))throw new DomainException('Existing member cannot be reassigned');
            if($member===null){
                $q=$this->db->prepare("INSERT INTO oj_employer_members(org_id,email,role,status) VALUES(:org,:email,'manager','invited')");
                $q->execute(['org'=>$orgId,'email'=>$email]);$memberId=(int)$this->db->lastInsertId();
            }else $memberId=(int)$member['id'];
            $code=RecruiterPortal::newInviteCode();$digest=RecruiterPortal::codeHash($memberId,$code,$secret);
            $q=$this->db->prepare('INSERT INTO oj_employer_email_challenges(member_id,code_digest,expires_at,attempts) VALUES(:id,:digest,:expires,0) ON DUPLICATE KEY UPDATE code_digest=VALUES(code_digest),expires_at=VALUES(expires_at),attempts=0');
            $q->execute(['id'=>$memberId,'digest'=>$digest,'expires'=>gmdate('Y-m-d H:i:s',$now+900)]);
            // No challenge secret is ever returned by this method or written to logs.
            if($deliver($email,$code)!==true)throw new RuntimeException('Email delivery not confirmed by configured transport');
            $this->db->commit();return true;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    /** Confirm possession of mailbox and set password in one atomic, single-use transaction. */
    public function confirm(string $email,string $code,string $password,int $now):bool
    {
        if(!RecruiterPortal::validEmail($email)||!preg_match('/^[0-9]{8}$/D',$code))throw new InvalidArgumentException('Invalid confirmation');
        RecruiterPortal::validateNewPassword($password);
        $secret=(string)getenv('RECRUITER_AUTH_HMAC_SECRET');if(strlen($secret)<32)throw new RuntimeException('Auth secret unavailable');
        $this->db->beginTransaction();
        try{
            $member=$this->memberByEmail($email,true);
            if($member===null||$member['status']!=='invited'||$member['email_verified_at']!==null){$this->db->commit();return false;}
            $r=$this->rows('SELECT * FROM oj_employer_email_challenges WHERE member_id=:member FOR UPDATE',['member'=>$member['id']]);
            $challenge=$r[0]??null;
            if($challenge===null||strtotime($challenge['expires_at'].' UTC')<=$now||(int)$challenge['attempts']>=5){$this->db->commit();return false;}
            $valid=hash_equals($challenge['code_digest'],RecruiterPortal::codeHash((int)$member['id'],$code,$secret));
            if(!$valid){$q=$this->db->prepare('UPDATE oj_employer_email_challenges SET attempts=attempts+1 WHERE member_id=:id');$q->execute(['id'=>$member['id']]);$this->db->commit();return false;}
            $q=$this->db->prepare("UPDATE oj_employer_members SET password_hash=:hash,email_verified_at=:now,status='active' WHERE id=:id AND status='invited'");
            $q->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'now'=>gmdate('Y-m-d H:i:s',$now),'id'=>$member['id']]);
            $q=$this->db->prepare('DELETE FROM oj_employer_email_challenges WHERE member_id=:id');$q->execute(['id'=>$member['id']]);
            $this->db->commit();return true;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    /** Attempts are bounded, hashed (email+IP), and do not divulge whether an account exists. */
    public function authenticate(string $email,string $password,string $ip,int $now):?array
    {
        $secret=(string)getenv('RECRUITER_AUTH_HMAC_SECRET');
        if(strlen($secret)<32)throw new RuntimeException('Auth secret unavailable');
        $email=strtolower(trim($email));
        if(!RecruiterPortal::validEmail($email)||strlen($password)>512)return null;
        if(!filter_var($ip,FILTER_VALIDATE_IP))$ip='0.0.0.0';
        $identity=hash_hmac('sha256',$email.'|'.$ip,$secret);$bucket=gmdate('Y-m-d H:i:00',$now);
        $q=$this->db->prepare('INSERT INTO oj_recruiter_auth_rate(identity_hash,minute_bucket,attempts) VALUES(:key,:bucket,1) ON DUPLICATE KEY UPDATE attempts=attempts+1');
        $q->execute(['key'=>$identity,'bucket'=>$bucket]);
        $attempts=(int)$this->rows('SELECT attempts FROM oj_recruiter_auth_rate WHERE identity_hash=:key AND minute_bucket=:bucket',['key'=>$identity,'bucket'=>$bucket])[0]['attempts'];
        if($attempts>5)return null;
        $member=$this->memberByEmail($email);
        if($member===null||!is_string($member['password_hash'])||!password_verify($password,$member['password_hash']))return null;
        return $this->current((int)$member['id']);
    }
    /** Reload from trusted DB on EVERY request; session alone never proves affiliation. */
    public function current(int $memberId):?array
    {
        if($memberId<1)return null;
        $member=$this->memberById($memberId);
        if($member===null||$member['status']!=='active'||$member['email_verified_at']===null)return null;
        $org=$this->org($member['org_id']);if($org===null||!EmployerReview::validOrg($org))return null;
        return ['id'=>(int)$member['id'],'email'=>$member['email'],'status'=>$member['status'],
            'role'=>$member['role'],'email_verified_at'=>$member['email_verified_at'],
            'org'=>['id'=>$org['id'],'approved'=>true,'suspended'=>false,'email_verified'=>true,
              'domain_verified'=>true,'verified_domains'=>json_decode($org['verified_domains_json'],true),
              'company_name'=>$org['company_name'],'status'=>$org['status'],'approved_at'=>$org['approved_at'],
              'approved_by'=>$org['approved_by'],'email_verified_at'=>$org['email_verified_at'],
              'domain_verified_at'=>$org['domain_verified_at'],
              'representative_verified_at'=>$org['representative_verified_at'],
              'verified_domains_json'=>$org['verified_domains_json'],
              'evidence_reference'=>$org['evidence_reference']]];
    }
    public function jobs(array $member,int $limit=60):array
    {
        if(($member['status']??'')!=='active'||($member['email_verified_at']??null)===null||!EmployerReview::validOrg($member['org']??[]))throw new DomainException('Not authorised');
        if($limit<1||$limit>100)throw new InvalidArgumentException('Invalid limit');
        return (new PartnerMysqlStore($this->db))->listJobs($member['org']['id'],$limit,null,null,null)['items'];
    }
    public function create(array $member,array $body,int $now,string $terms):array
    {
        if(!RecruiterPortal::mayManage($member))throw new DomainException('Not authorised');
        $job=EmployerContracts::validateCreate($body,$member['org'],$now,$terms);
        $key='web-'.bin2hex(random_bytes(16));
        return (new PartnerMysqlStore($this->db))->create($member['org'],$job,$key,PartnerApi::canonicalHash($body),$now)['record'];
    }
    public function edit(array $member,string $jobId,array $patch,int $revision,int $now,string $terms):?array
    {
        if(!RecruiterPortal::mayManage($member))throw new DomainException('Not authorised');
        return (new PartnerMysqlStore($this->db))->patchJob($member['org'],$jobId,$patch,$revision,$now,$terms);
    }
    public function close(array $member,string $jobId,int $now):?array
    {
        if(!RecruiterPortal::mayManage($member))throw new DomainException('Not authorised');
        return (new PartnerMysqlStore($this->db))->closeJob($member['org']['id'],$jobId,$now);
    }
}
