<?php
declare(strict_types=1);
$pass=0;
function reportCheck(string $description,bool $ok):void{
    global $pass;
    if(!$ok){fwrite(STDERR,"FAIL predeployment report: $description\n");exit(1);}
    $pass++;echo "PASS predeployment report: $description\n";
}
$root=dirname(__DIR__);
$script=$root.'/scripts/predeployment_report.php';
$cmd='RELEASE_READY=0 '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1';
$out=[];$exit=0;
exec($cmd,$out,$exit);
$report=json_decode(implode("\n",$out),true);
reportCheck('Read-only report is valid JSON',is_array($report));
reportCheck('Default-off release gate exits 2, never green', $exit===2);
reportCheck('Explicit NO_GO and no automated deployment authority',
    ($report['decision']??null)==='NO_GO'
    &&($report['automated_deployment_authorised']??null)===false);
reportCheck('Canonical v1.4.1 only',($report['canonical_target']??null)==='v1.4.1');
reportCheck('No claims of live hosting, provider licence or original restore',
    ($report['live_hostinger_inspected_by_this_command']??null)===false
    &&($report['provider_licences_independently_verified_by_this_command']??null)===false
    &&($report['real_backup_restoration_verified_by_this_command']??null)===false);
reportCheck('No private owner-approval content exported',
    ($report['private_approval_contents_exported']??null)===false
    &&!str_contains(implode("\n",$out),'release_authorization.json'));
$modules=$report['module_register']['modules']??[];
reportCheck('All eighteen M00-M17 listed exactly once',
    count($modules)===18
    &&array_column($modules,'id')===array_map(
        static fn($i)=>'M'.str_pad((string)$i,2,'0',STR_PAD_LEFT),range(0,17)));
reportCheck('Self-reported module status is not independent readiness',
    ($report['module_register']['hostinger_verified_self_reported']??null)===false
    &&($report['module_register']['public_launch_ready_self_reported']??null)===false);
reportCheck('No synthetic 14-day provider or role data asserted',
    ($report['source_sampling_local_shape_only']['provider_14_day_shape_met']??null)===false
    &&($report['source_sampling_local_shape_only']['two_or_three_role_14_day_shape_met']??null)===false);
reportCheck('Release off switch appears as a real blocker',
    in_array('RELEASE_READY is not set',$report['local_gate']['blockers']??[],true));
reportCheck('External evidence checklist still names independent restore and legal rights',
    in_array('original_public_html_and_mysql_backups_plus_independent_test_restore',
        $report['independent_external_evidence_still_required']??[],true)
    &&in_array('current_provider_terms_and_actual_permissions_before_any_live_sync',
        $report['independent_external_evidence_still_required']??[],true));
reportCheck('No local program can turn this report into deployment approval',
    ($report['automated_deployment_authorised']??true)===false
    &&str_contains($report['warning']??'','Never deploy'));
echo "\n$pass founder-readable predeployment report safety checks passed.\n";
