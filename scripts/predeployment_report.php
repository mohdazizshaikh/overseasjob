<?php
/** Read-only, founder-facing local release gate summary; never authorises deployment. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__).'/src/Launch.php';
$root=dirname(__DIR__);
$now=time();
$gate=Launch::status($root,$now);
$ledgerPath=$root.'/module_status.json';
$ledgerRaw=(!is_link($ledgerPath) && is_file($ledgerPath) && @filesize($ledgerPath)<500000)
    ?@file_get_contents($ledgerPath):false;
$ledger=is_string($ledgerRaw)?json_decode($ledgerRaw,true):null;
$modules=[];$seen=[];$ledgerValid=is_array($ledger)&&is_array($ledger['modules']??null);
if($ledgerValid){
    foreach($ledger['modules'] as $row){
        if(!is_array($row)||!is_string($row['module_id']??null)
            ||!preg_match('/^M(?:0[0-9]|1[0-7])$/D',$row['module_id'])
            ||isset($seen[$row['module_id']])||!is_string($row['implementation_status']??null)){
            $ledgerValid=false;break;
        }
        $seen[$row['module_id']]=true;
        $modules[]=[
            'id'=>$row['module_id'],
            'self_reported_code_status'=>$row['implementation_status'],
            'has_external_or_implementation_blocker'=>trim((string)($row['blocker_reason']??''))!==''
        ];
    }
    if(count($seen)!==18)$ledgerValid=false;
}
if(!$ledgerValid)$modules=[];
usort($modules,static fn($a,$b)=>strcmp($a['id'],$b['id']));
$counts=[];foreach($modules as $m){$s=$m['self_reported_code_status'];$counts[$s]=($counts[$s]??0)+1;}
$coverage=SourceCoverage::summarize($root.'/storage/coverage.ndjson',$now);
$roles=RoleCoverage::summarize($root.'/storage/role_coverage.ndjson',$now);
$blockers=$gate['blockers'];
if(!$ledgerValid)$blockers[]='Module ledger missing, malformed or not exactly M00-M17';
$localReady=($gate['ready']??false)===true && $ledgerValid;
$report=[
    'report_schema'=>1,
    'as_of_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now),
    'canonical_target'=>'v1.4.1',
    'decision'=>$localReady?'LOCAL_CONDITIONS_MET_EXTERNAL_REVIEW_REQUIRED':'NO_GO',
    'automated_deployment_authorised'=>false,
    'live_hostinger_inspected_by_this_command'=>false,
    'provider_licences_independently_verified_by_this_command'=>false,
    'real_backup_restoration_verified_by_this_command'=>false,
    'private_approval_contents_exported'=>false,
    'local_gate'=>['ready'=>$localReady,'blocker_count'=>count($blockers),'blockers'=>$blockers],
    'module_register'=>[
        'valid_m00_to_m17'=>$ledgerValid,
        'source_of_truth_version'=>$ledger['source_of_truth_version']??null,
        'counts_by_self_reported_code_status'=>$counts,
        'modules'=>$modules,
        'hostinger_verified_self_reported'=>($ledger['hostinger_verified']??null)===true,
        'public_launch_ready_self_reported'=>($ledger['public_launch_ready']??null)===true
    ],
    'source_sampling_local_shape_only'=>[
        'provider_daily_log_valid'=>$coverage['log_valid'],
        'provider_distinct_utc_days'=>$coverage['distinct_actual_days'],
        'provider_14_day_shape_met'=>$coverage['14_distinct_calendar_days_measured'],
        'role_daily_log_valid'=>$roles['log_valid'],
        'role_distinct_utc_days'=>$roles['days_measured'],
        'two_or_three_role_14_day_shape_met'=>$roles['14_days_for_2_to_3_role_cohorts_shape_valid'],
        'independent_real_hosted_source_evidence_proved'=>false
    ],
    'independent_external_evidence_still_required'=>[
        'original_hostinger_account_php_ssl_cron_private_root_inspection',
        'original_public_html_and_mysql_backups_plus_independent_test_restore',
        'current_provider_terms_and_actual_permissions_before_any_live_sync',
        'fourteen_genuine_authorised_utc_days_and_two_or_three_role_supply_review',
        'human_guides_country_visa_legal_privacy_and_consent_review',
        'original_theme_search_indexing_mobile_accessibility_and_domain_checks',
        'owner_approved_reversible_cutover_and_incident_response'
    ],
    'warning'=>'This command reads local repository files only. Passing tests and flags do not independently prove external claims. Never deploy from this report alone.'
];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($localReady?0:2);
