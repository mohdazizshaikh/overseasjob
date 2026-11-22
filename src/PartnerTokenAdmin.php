<?php
declare(strict_types=1);
require_once __DIR__.'/PartnerMysqlStore.php';

/** Trusted CLI-only token lifecycle. This cannot approve an employer or grant itself scopes. */
final class PartnerTokenAdmin
{
    public function __construct(private PDO $db,private string $serverSecret)
    {
        if(strlen($serverSecret)<32)throw new DomainException('HMAC secret missing');
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
        $db->exec("SET time_zone = '+00:00'");
    }
    public function issue(string $orgId,array $scopes,int $now,int $days=30):array
    {
        if(!preg_match('/^org_[A-Za-z0-9_-]{4,64}$/',$orgId)||$days<1||$days>90)throw new InvalidArgumentException('Invalid organisation or expiration');
        if($scopes===[]||count($scopes)>3||count(array_unique($scopes))!==count($scopes))throw new InvalidArgumentException('Invalid scopes');
        foreach($scopes as $scope)if(!in_array($scope,['jobs:read','jobs:write','jobs:close'],true))throw new InvalidArgumentException('Unsupported scope');
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('SELECT id,status,email_verified_at,domain_verified_at,approved_at,approved_by,evidence_reference,representative_verified_at,verified_domains_json FROM oj_employer_orgs WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$orgId]);$org=$stmt->fetch(PDO::FETCH_ASSOC);
            $domains=is_array($org)?json_decode((string)$org['verified_domains_json'],true):null;
            $approved=is_array($org)&&$org['status']==='approved'&&$org['email_verified_at']!==null&&$org['domain_verified_at']!==null
              &&$org['approved_at']!==null&&$org['representative_verified_at']!==null
              &&trim((string)$org['approved_by'])!==''&&trim((string)$org['evidence_reference'])!==''&&is_array($domains)&&count($domains)>0;
            if(!$approved)throw new DomainException('Organisation requires independent identity/email/domain approval');
            $token=EmployerContracts::newOpaqueToken();
            $fingerprint=EmployerContracts::tokenFingerprint($token,$this->serverSecret);
            $create=$this->db->prepare('INSERT INTO oj_partner_tokens(org_id,token_prefix,fingerprint,scopes_json,expires_at)
                VALUES(:org,:prefix,:fingerprint,:scopes,:expiry)');
            $create->execute(['org'=>$orgId,'prefix'=>substr($token,0,12),'fingerprint'=>$fingerprint,
                'scopes'=>json_encode($scopes,JSON_THROW_ON_ERROR),'expiry'=>gmdate('Y-m-d H:i:s',$now+$days*86400)]);
            $id=(int)$this->db->lastInsertId();
            $this->db->commit();
            return ['token'=>$token,'token_id'=>$id,'org_id'=>$orgId,'scopes'=>$scopes,'expires_at'=>gmdate('Y-m-d\TH:i:s\Z',$now+$days*86400)];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function revoke(int $tokenId):bool
    {
        if($tokenId<1)throw new InvalidArgumentException('Invalid token ID');
        $q=$this->db->prepare('UPDATE oj_partner_tokens SET revoked_at=UTC_TIMESTAMP() WHERE id=:id AND revoked_at IS NULL');
        $q->execute(['id'=>$tokenId]);return $q->rowCount()===1;
    }
}
