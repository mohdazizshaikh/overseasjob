<?php
declare(strict_types=1);
require_once __DIR__.'/Jobs.php';
/** Optional Gate 2B/3B core validation only. No public employer onboarding/API until external identity, database, TLS and moderation gates pass. */
final class EmployerContracts
{
    private const CREATE_FIELDS=[
        'external_id','title','description','employment_type','work_mode','city','work_country','employer_country',
        'location_text','candidate_eligible_countries','eligibility_statement','apply_url','valid_through',
        'salary_min','salary_max','salary_currency','salary_period','visa_sponsorship','relocation_support',
        'consent_to_publish','distribution_consent'
    ];
    private const PATCH_FIELDS=[
        'title','description','employment_type','work_mode','city','work_country','employer_country',
        'location_text','candidate_eligible_countries','eligibility_statement','apply_url','valid_through',
        'salary_min','salary_max','salary_currency','salary_period','visa_sponsorship','relocation_support'
    ];
    public static function permittedForPilot(array $org):bool
    {
        // These flags must originate in server-controlled verified state, not submitted request JSON.
        return is_string($org['id']??null)&&preg_match('/^org_[a-zA-Z0-9_-]{4,64}$/',$org['id'])===1
            &&($org['approved']??false)===true&&($org['email_verified']??false)===true
            &&($org['domain_verified']??false)===true&&($org['suspended']??false)===false
            &&is_array($org['verified_domains']??null)&&count($org['verified_domains'])>0;
    }
    private static function trimField(mixed $value,int $min,int $max,string $field):string
    {
        if(!is_string($value))throw new InvalidArgumentException($field.' must be text');
        $value=trim($value);
        if(strlen($value)<$min||strlen($value)>$max)throw new InvalidArgumentException($field.' exceeds length limits');
        return $value;
    }
    private static function country(mixed $value,string $field,bool $nullable=false):?string
    {
        if($nullable&&$value===null)return null;
        if(!is_string($value)||!preg_match('/^[A-Z]{2}$/',$value)||Countries::alpha2($value)!==$value)throw new InvalidArgumentException('Invalid '.$field);
        return $value;
    }
    private static function fixedDate(mixed $value,int $now):string
    {
        if(!is_string($value)||strlen($value)>40||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/',$value))throw new InvalidArgumentException('valid_through requires an RFC3339 date-time');
        try{$date=new DateTimeImmutable($value);}catch(Throwable){throw new InvalidArgumentException('Invalid expiry date');}
        // PHP DateTime silently normalises impossible calendar days (e.g. Nov 31 -> Dec 1).
        // A supplied expiry is employer-signed evidence: never rewrite its calendar.
        if($date->format('Y-m-d\TH:i:s')!==substr($value,0,19))
            throw new InvalidArgumentException('Invalid expiry calendar date');
        $ts=$date->getTimestamp();
        if($ts<=$now+86400||$ts>$now+60*86400)throw new InvalidArgumentException('Expiry must be more than one day and at most 60 days ahead');
        return gmdate('Y-m-d\TH:i:s\Z',$ts);
    }
    private static function validateUrl(mixed $value,array $verifiedDomains):string
    {
        $url=Jobs::safeUrl($value);
        if($url===null)throw new InvalidArgumentException('Apply URL must be safe HTTPS');
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));
        if(in_array($host,['bit.ly','tinyurl.com','t.co','lnkd.in','is.gd'],true))throw new InvalidArgumentException('URL shorteners not permitted');
        foreach($verifiedDomains as $domain){
            if(!is_string($domain)||!preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/',$domain))continue;
            $domain=strtolower($domain);
            if($host===$domain||str_ends_with($host,'.'.$domain))return $url;
        }
        throw new InvalidArgumentException('Apply hostname not controlled by verified organisation / allowlisted ATS');
    }
    public static function validateCreate(array $body,array $verifiedOrg,int $now,string $termsVersion):array
    {
        if(!self::permittedForPilot($verifiedOrg))throw new DomainException('Unverified employer cannot post');
        if(strlen($termsVersion)<3||strlen($termsVersion)>40)throw new DomainException('Versioned publication terms required');
        foreach(array_keys($body) as $key)if(!in_array($key,self::CREATE_FIELDS,true))throw new InvalidArgumentException('Unexpected job field: '.$key);
        $external=self::trimField($body['external_id']??null,1,160,'external_id');
        if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]*$/',$external))throw new InvalidArgumentException('Invalid external_id');
        $title=self::trimField($body['title']??null,5,250,'title');
        $description=self::trimField($body['description']??null,30,16000,'description');
        if(!in_array($body['employment_type']??null,['full_time','part_time','contract','temporary','internship'],true))throw new InvalidArgumentException('Invalid employment type');
        $workMode=$body['work_mode']??null;
        if(!in_array($workMode,['remote','hybrid','onsite'],true))throw new InvalidArgumentException('Invalid work mode');
        $location=self::trimField($body['location_text']??null,2,255,'location_text');
        $country=array_key_exists('work_country',$body)?self::country($body['work_country'],'work_country',true):null;
        if(in_array($workMode,['onsite','hybrid'],true)&&$country===null)throw new InvalidArgumentException('Physical work country required');
        $employerCountry=array_key_exists('employer_country',$body)?self::country($body['employer_country'],'employer_country',true):null;
        if(($body['consent_to_publish']??null)!==true)throw new DomainException('Explicit publication consent required');
        if(isset($body['distribution_consent'])&&!is_bool($body['distribution_consent']))throw new InvalidArgumentException('Invalid redistribution preference');
        $eligible=$body['candidate_eligible_countries']??[];
        if(!is_array($eligible)||!array_is_list($eligible)||count($eligible)>100)throw new InvalidArgumentException('Invalid eligible-country list');
        $unique=[];
        foreach($eligible as $c){$c=self::country($c,'candidate_eligible_countries');if(in_array($c,$unique,true))throw new InvalidArgumentException('Duplicate eligible country');$unique[]=$c;}
        $sponsorship=$body['visa_sponsorship']??'unknown';$relocation=$body['relocation_support']??'unknown';
        if(!in_array($sponsorship,['yes','no','unknown'],true)||!in_array($relocation,['yes','no','unknown'],true))throw new InvalidArgumentException('Visa and relocation values must be explicit enums');
        $salaryMin=$body['salary_min']??null;$salaryMax=$body['salary_max']??null;
        foreach(['salary_min'=>$salaryMin,'salary_max'=>$salaryMax] as $key=>$value)if($value!==null&&(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>10000000000))throw new InvalidArgumentException('Invalid '.$key);
        if($salaryMin!==null&&$salaryMax!==null&&(float)$salaryMax<(float)$salaryMin)throw new InvalidArgumentException('Salary maximum below minimum');
        $currency=$body['salary_currency']??null;$period=$body['salary_period']??null;
        if(($salaryMin!==null||$salaryMax!==null)&&($currency===null||$period===null))throw new InvalidArgumentException('Salary requires currency and period');
        if(($currency!==null||$period!==null)&&($salaryMin===null&&$salaryMax===null))throw new InvalidArgumentException('Do not invent salary without an amount');
        if($currency!==null&&(!is_string($currency)||!preg_match('/^[A-Z]{3}$/',$currency)))throw new InvalidArgumentException('Invalid currency code');
        if($period!==null&&!in_array($period,['hour','day','week','month','year'],true))throw new InvalidArgumentException('Invalid salary period');
        $city=array_key_exists('city',$body)?($body['city']===null?null:self::trimField($body['city'],0,120,'city')):null;
        $statement=isset($body['eligibility_statement'])?self::trimField($body['eligibility_statement'],0,1000,'eligibility_statement'):null;
        return [
          'origin_type'=>'direct_employer','owner_org_id'=>$verifiedOrg['id'],'external_id'=>$external,
          'title'=>$title,'description'=>Jobs::text($description,16000),'employment_type'=>$body['employment_type'],
          'work_mode'=>$workMode,'work_country'=>$country,'employer_country'=>$employerCountry,'city'=>$city,
          'location_text'=>$location,'candidate_eligible_countries'=>$unique,'eligibility_statement'=>$statement,
          'apply_url'=>self::validateUrl($body['apply_url']??null,$verifiedOrg['verified_domains']),
          'valid_through'=>self::fixedDate($body['valid_through']??null,$now),
          'salary_min'=>$salaryMin===null?null:(float)$salaryMin,'salary_max'=>$salaryMax===null?null:(float)$salaryMax,
          'salary_currency'=>$currency,'salary_period'=>$period,'visa_sponsorship'=>$sponsorship,
          'relocation_support'=>$relocation,'publish_consent'=>true,'distribution_consent'=>$body['distribution_consent']??false,
          'consent_terms_version'=>$termsVersion,'consent_at'=>gmdate('Y-m-d\TH:i:s\Z',$now),
          'status'=>'pending_review','revision'=>1,'source_policy_id'=>'first_party_employer_pending_review'
        ];
    }
    public static function validatePatch(array $existing,array $patch,array $verifiedOrg,int $now,string $termsVersion,int $ifMatch):array
    {
        self::assertOwner($existing,$verifiedOrg);
        // A PATCH cannot manufacture or revive publication consent from a
        // corrupted/restored legacy record: preserve only proven server state.
        $consentAt=$existing['consent_at']??null;
        $consentVersion=$existing['consent_terms_version']??null;
        if(($existing['publish_consent']??null)!==true
          ||!is_string($consentVersion)||strlen(trim($consentVersion))<3
          ||!is_string($consentAt)
          ||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$consentAt)
          ||strtotime($consentAt)===false
          ||gmdate('Y-m-d\TH:i:s\Z',(int)strtotime($consentAt))!==$consentAt)
            throw new DomainException('Original employer publication consent evidence is missing or invalid');
        if(!in_array($existing['status']??null,['draft','pending_review','published','rejected'],true))throw new DomainException('Job is not editable');
        if($ifMatch!==(int)($existing['revision']??0))throw new RuntimeException('Revision mismatch: 409');
        if($patch===[])throw new InvalidArgumentException('Empty patch');
        foreach(array_keys($patch) as $key)if(!in_array($key,self::PATCH_FIELDS,true))throw new InvalidArgumentException('Protected or unexpected patch field');
        $body=array_intersect_key($existing,array_flip(self::CREATE_FIELDS));
        foreach($patch as $k=>$v)$body[$k]=$v;
        $body['consent_to_publish']=true; // inherited genuine consent must have been recorded in trusted storage
        $validated=self::validateCreate($body,$verifiedOrg,$now,$termsVersion);
        $validated['revision']=$ifMatch+1;
        $validated['external_id']=$existing['external_id'];
        $validated['status']='pending_review'; // never autopublish a changed live listing
        $validated['consent_at']=$existing['consent_at'];
        $validated['consent_terms_version']=$existing['consent_terms_version'];
        return $validated;
    }
    public static function assertOwner(array $job,array $verifiedOrg):void
    {
        if(!self::permittedForPilot($verifiedOrg)||($job['origin_type']??null)!=='direct_employer'||($job['owner_org_id']??null)!==$verifiedOrg['id'])throw new DomainException('Job does not belong to approved employer');
    }
    public static function visibleForPartner(array $jobs,array $verifiedOrg):array
    {
        if(!self::permittedForPilot($verifiedOrg))throw new DomainException('Account not approved');
        return array_values(array_filter($jobs,static fn($j)=>($j['origin_type']??null)==='direct_employer' && ($j['owner_org_id']??null)===$verifiedOrg['id']));
    }
    public static function validateIdempotencyKey(mixed $key):string
    {
        if(!is_string($key)||!preg_match('/^[A-Za-z0-9._:-]{8,128}$/',$key))throw new InvalidArgumentException('Invalid Idempotency-Key');
        return $key;
    }
    public static function newOpaqueToken():string{return bin2hex(random_bytes(32));}
    public static function tokenFingerprint(string $token,string $serverSecret):string
    {
        if(strlen($serverSecret)<32)throw new DomainException('Token signing secret missing');
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Invalid API token');
        return hash_hmac('sha256',$token,$serverSecret);
    }
}
