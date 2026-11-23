<?php
declare(strict_types=1);

/**
 * Strict fail-closed operator-signed-on-host boundary for outbound LIVE provider requests.
 *
 * A passing local attestation only proves that an operator deliberately recorded a
 * decision. It NEVER independently proves upstream copyright, platform terms,
 * resyndication rights, real inventory or other external/legal verification.
 */
final class SourcePermissionGate
{
    private const PROVIDERS=['jobicy','himalayas'];
    private const MAX_ATTESTATION_BYTES=4096;

    private static function exactDate(mixed $text):?string
    {
        if(!is_string($text)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$text))return null;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$text,new DateTimeZone('UTC'));
        return $date!==false && $date->format('Y-m-d')===$text?$text:null;
    }

    /**
     * @param array<string,mixed> $policy
     * @param array<string,string> $flags explicit host env values, NOT inferred from policy.
     * @return array{ready:bool,blockers:list<string>,operator_attestation_not_external_verification:bool}
     */
    public static function readiness(string $root,string $provider,array $policy,array $flags,int $now):array
    {
        $blockers=[];
        if(!in_array($provider,self::PROVIDERS,true))
            return ['ready'=>false,'blockers'=>['unknown_provider'],
                'operator_attestation_not_external_verification'=>true];

        if(($policy['enabled']??null)!==true)$blockers[]='provider_policy_disabled';
        if(($policy['canonical_source_required']??null)!==true
            ||($policy['allow_resyndication']??null)!==false
            ||($policy['allow_jobposting_schema']??null)!==false)
            $blockers[]='source_policy_must_remain_link_only';
        $checked=self::exactDate($policy['checked_at']??null);
        $today=gmdate('Y-m-d',$now);
        $age=$checked!==null
            ?(int)floor((strtotime($today.' UTC')-strtotime($checked.' UTC'))/86400):9999;
        if($checked===null||$age<0||$age>30)$blockers[]='source_policy_review_missing_or_outdated';
        $terms=$policy['terms_url']??null;
        if(!is_string($terms)||!preg_match('#^https://(?:www\.)?(?:jobicy\.com|himalayas\.app)/[A-Za-z0-9/_-]+$#D',$terms))
            $blockers[]='official_provider_terms_reference_invalid';

        if(($flags['SOURCE_LIVE_SYNC_ENABLED']??'0')!=='1')
            $blockers[]='global_live_sync_opt_in_disabled';
        $flag='SOURCE_'.strtoupper($provider).'_RIGHTS_REVIEWED';
        if(($flags[$flag]??'0')!=='1')
            $blockers[]='provider_operator_approval_disabled';

        $file=rtrim($root,'/').'/storage/provider_rights/'.$provider.'.json';
        $safe=is_file($file)&&!is_link($file)&&filesize($file)!==false
            &&filesize($file)>30&&filesize($file)<=self::MAX_ATTESTATION_BYTES;
        // The attestations are PRIVATE runtime files, deliberately absent from Git and
        // never placed in public_html or included in distributable source archives.
        $approval=null;
        if($safe){
            $raw=@file_get_contents($file);
            if(is_string($raw))$approval=json_decode($raw,true,16);
        }
        if(!is_array($approval)
          ||($approval['schema_version']??null)!==1
          ||($approval['provider']??null)!==$provider
          ||($approval['reviewed_at']??null)!==$checked
          ||($approval['terms_url']??null)!==$terms
          ||($approval['reviewer_role']??null)!=='owner'
          ||($approval['scope']??null)!=='source_linked_listing_only'
          ||($approval['approved_for_single_source_display']??null)!==true
          ||!is_string($approval['review_notes']??null)
          ||strlen(trim($approval['review_notes']))<40
          ||strlen(trim($approval['review_notes']))>1200)
            $blockers[]='private_operator_attestation_missing_or_invalid';

        return ['ready'=>!$blockers,'blockers'=>$blockers,
            'operator_attestation_not_external_verification'=>true];
    }

    public static function runtimeFlags():array
    {
        return [
            'SOURCE_LIVE_SYNC_ENABLED'=>(string)getenv('SOURCE_LIVE_SYNC_ENABLED'),
            'SOURCE_JOBICY_RIGHTS_REVIEWED'=>(string)getenv('SOURCE_JOBICY_RIGHTS_REVIEWED'),
            'SOURCE_HIMALAYAS_RIGHTS_REVIEWED'=>(string)getenv('SOURCE_HIMALAYAS_RIGHTS_REVIEWED')
        ];
    }
}
