<?php
declare(strict_types=1);
require_once __DIR__.'/Guides.php';
require_once __DIR__.'/SourceCoverage.php';
require_once __DIR__.'/RoleCoverage.php';
require_once __DIR__.'/SourcePermissionGate.php';

/** Public release is fail-closed: local documents are never external proof by themselves. */
final class Launch
{
    private const CANONICAL_VERSION='v1.4.1';
    private const CANONICAL_SHA256='3802d1e3b9090feeaaa5eed3fa780f9858299eaa96f1be4980edb9569983ecb9';
    private const EVIDENCE_KEYS=[
        'hosting_ssl_cron','live_provider_rights_and_sample','reviewed_original_guides',
        'privacy_and_legal','backup_restore','staging_e2e_security_accessibility'
    ];
    private static function jsonFile(string $file,int $maxBytes):?array
    {
        if(is_link($file)||!is_file($file))return null;
        $size=@filesize($file);
        if($size===false||$size<2||$size>$maxBytes)return null;
        $raw=@file_get_contents($file);
        $out=is_string($raw)?json_decode($raw,true,24):null;
        return is_array($out)?$out:null;
    }
    private static function recentUtc(mixed $value,int $now,int $days):bool
    {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$value))return false;
        $time=strtotime($value);
        return $time!==false&&gmdate('Y-m-d\TH:i:s\Z',$time)===$value
            &&$time<=$now+300&&$time>=$now-$days*86400;
    }
    public static function status(string $root,?int $now=null):array
    {
        $now??=time();
        $root=rtrim($root,'/');
        $blockers=[];
        if(getenv('RELEASE_READY')!=='1')$blockers[]='RELEASE_READY is not set';

        $manifestPath=$root.'/RELEASE_MANIFEST.json';
        $manifest=self::jsonFile($manifestPath,100_000);
        $modules=self::jsonFile($root.'/module_status.json',300_000);
        if(($manifest['source_of_truth']??null)!==self::CANONICAL_VERSION
            ||($modules['spec_version']??null)!==self::CANONICAL_VERSION)
            $blockers[]='Latest v1.4.1 source-of-truth and module ledger not reconciled';
        if(($modules['public_launch_ready']??null)!==true
            ||($modules['hostinger_verified']??null)!==true)
            $blockers[]='Module ledger public-launch and actual-Hostinger verification flags are not both approved';
        $zip=$root.'/SOURCE_OF_TRUTH_REFERENCE/OverseasJob_v1.4.1_Corrected_Canonical_Pack.zip';
        $zipSize=!is_link($zip)&&is_file($zip)?@filesize($zip):false;
        if($zipSize===false||$zipSize>2_000_000
            ||@hash_file('sha256',$zip)!==self::CANONICAL_SHA256)
            $blockers[]='Exact founder canonical v1.4.1 ZIP is absent or fails SHA-256';

        $registerPath=$root.'/config/release_evidence.json';
        $register=self::jsonFile($registerPath,64_000);
        if($register===null)$blockers[]='Release evidence register absent or invalid';
        foreach(self::EVIDENCE_KEYS as $key){
            $entry=$register['checks'][$key]??null;
            $location=is_array($entry)?($entry['evidence']??null):null;
            $expected=is_array($entry)?($entry['sha256']??null):null;
            if(!is_array($entry)||($entry['verified']??null)!==true
                ||!is_string($location)
                ||!preg_match('#^docs/evidence/[A-Za-z0-9._-]+\.md$#D',$location)
                ||!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected)
                ||!self::recentUtc($entry['reviewed_at']??null,$now,30)){
                $blockers[]=$key.': current evidence review and verified SHA-256 required';
                continue;
            }
            $file=$root.'/'.$location;
            $size=!is_link($file)&&is_file($file)?@filesize($file):false;
            if($size===false||$size<150||$size>64_000
                ||@hash_file('sha256',$file)!==$expected)
                $blockers[]=$key.': evidence file changed, linked, oversized or absent';
        }

        // Schema-valid sample days still do NOT independently prove licensed feed reality.
        $coverage=SourceCoverage::summarize($root.'/storage/coverage.ndjson',$now);
        if(!$coverage['log_valid']||!$coverage['14_distinct_calendar_days_measured'])
            $blockers[]='Fourteen distinct UTC days of source samples are missing';
        $roleCoverage=RoleCoverage::summarize($root.'/storage/role_coverage.ndjson',$now);
        if(!$roleCoverage['log_valid']||!$roleCoverage['14_days_for_2_to_3_role_cohorts_shape_valid'])
            $blockers[]='Fourteen UTC days of two to three role-cohort sampling metadata are missing';
        $policies=self::jsonFile($root.'/config/provider_policy.json',30_000);
        $providerReady=false;
        if(is_array($policies)){
            foreach(['jobicy','himalayas'] as $name){
                if(!is_array($policies[$name]??null)||($policies[$name]['enabled']??null)!==true)continue;
                $decision=SourcePermissionGate::readiness($root,$name,$policies[$name],
                    SourcePermissionGate::runtimeFlags(),$now);
                if($decision['ready']){$providerReady=true;break;}
            }
        }
        if(!$providerReady)
            $blockers[]='No permitted source has current private operator activation evidence';

        $guides=Guides::all($root.'/content/guides.json');
        if(count($guides)!==5||count(array_filter($guides,
            static fn($g)=>($g['editorial_status']??'')==='reviewed'))!==5)
            $blockers[]='Five original guides require actual editorial approval';

        $approvalPath=$root.'/storage/release_authorization.json';
        $approval=self::jsonFile($approvalPath,4096);
        $permissions=is_file($approvalPath)?@fileperms($approvalPath):false;
        $storageRoot=realpath($root.'/storage');
        $publicRoot=realpath($root.'/public');
        $private=$storageRoot!==false&&!is_link($root.'/storage')
            &&($publicRoot===false||($storageRoot!==$publicRoot
            &&!str_starts_with($storageRoot,$publicRoot.DIRECTORY_SEPARATOR)));
        if(!$private||$permissions===false||($permissions&0077)!==0
            ||($approval['schema_version']??null)!==1
            ||($approval['approved_for_public_launch']??null)!==true
            ||($approval['independent_external_checks_reviewed']??null)!==true
            ||($approval['reviewer_role']??null)!=='owner'
            ||!self::recentUtc($approval['reviewed_at']??null,$now,7)
            ||($approval['canonical_sha256']??null)!==self::CANONICAL_SHA256
            ||($approval['release_manifest_sha256']??null)!==(@hash_file('sha256',$manifestPath)?:null)
            ||($approval['evidence_register_sha256']??null)!==(@hash_file('sha256',$registerPath)?:null)
            ||!is_string($approval['review_notes']??null)
            ||strlen(trim($approval['review_notes']))<60
            ||strlen(trim($approval['review_notes']))>1400)
            $blockers[]='Private owner launch approval missing, too permissive, expired or not evidence-bound';

        return ['ready'=>count($blockers)===0,'blockers'=>$blockers,'documentation_only'=>false,
            'local_checks_do_not_independently_prove_external_claims'=>true];
    }
    public static function allowed(string $root):bool{return self::status($root)['ready'];}
}
