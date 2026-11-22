<?php
declare(strict_types=1);
require_once __DIR__.'/EmployerDomainProof.php';
/** Trusted private CLI only; DNS control alone never switches employer approval to true. */
final class EmployerDomainMysql
{
    public function __construct(private PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
        $db->exec("SET time_zone = '+00:00'");
    }
    private static function id(string $orgId):void{
        if(!preg_match('/^org_[A-Za-z0-9_-]{4,64}$/D',$orgId))throw new InvalidArgumentException('Invalid organisation ID');
    }
    public function issue(string $orgId,string $domain,int $now):array
    {
        self::id($orgId);$host=EmployerDomainProof::txtHost($domain);
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT status FROM oj_employer_orgs WHERE id=:id FOR UPDATE');$q->execute(['id'=>$orgId]);
            $status=$q->fetchColumn();
            if($status===false||!in_array($status,['unverified','pending_review','approved'],true))throw new DomainException('Organisation inactive');
            $token=EmployerDomainProof::makeChallenge();
            $q=$this->db->prepare('INSERT INTO oj_employer_domain_challenges(org_id,domain,digest,created_at,expires_at,verified_at) VALUES (:org,:domain,:digest,:at,:expiry,NULL)
               ON DUPLICATE KEY UPDATE digest=VALUES(digest),created_at=VALUES(created_at),expires_at=VALUES(expires_at),verified_at=NULL');
            $q->execute(['org'=>$orgId,'domain'=>$domain,'digest'=>EmployerDomainProof::digest($token),
              'at'=>gmdate('Y-m-d H:i:s',$now),'expiry'=>gmdate('Y-m-d H:i:s',$now+7*86400)]);
            $this->db->commit();return ['dns_txt_host'=>$host,'dns_txt_value'=>$token,'expires_at'=>gmdate('Y-m-d\TH:i:s\Z',$now+7*86400)];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function confirm(string $orgId,string $domain,array $records,int $now):bool
    {
        self::id($orgId);EmployerDomainProof::txtHost($domain);
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT o.status,o.verified_domains_json,c.digest,c.expires_at,c.verified_at FROM oj_employer_orgs o
               INNER JOIN oj_employer_domain_challenges c ON c.org_id=o.id AND c.domain=:domain WHERE o.id=:org FOR UPDATE');
            $q->execute(['org'=>$orgId,'domain'=>$domain]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||!in_array($row['status'],['unverified','pending_review','approved'],true)
             ||strtotime($row['expires_at'].' UTC')<=$now||!EmployerDomainProof::matches($row['digest'],$records)){
                $this->db->commit();return false;
            }
            if($row['verified_at']!==null){$this->db->commit();return true;}
            $domains=json_decode((string)$row['verified_domains_json'],true);
            if(!is_array($domains)||!array_is_list($domains))throw new RuntimeException('Org domain evidence corrupt');
            if(!in_array($domain,$domains,true))$domains[]=$domain;
            $q=$this->db->prepare('UPDATE oj_employer_orgs SET verified_domains_json=:domains,domain_verified_at=:at WHERE id=:org');
            $q->execute(['domains'=>json_encode($domains,JSON_THROW_ON_ERROR),'at'=>gmdate('Y-m-d H:i:s',$now),'org'=>$orgId]);
            $q=$this->db->prepare('UPDATE oj_employer_domain_challenges SET verified_at=:at WHERE org_id=:org AND domain=:domain');
            $q->execute(['at'=>gmdate('Y-m-d H:i:s',$now),'org'=>$orgId,'domain'=>$domain]);
            $this->db->commit();return true;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
