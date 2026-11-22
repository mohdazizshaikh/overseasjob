<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/PartnerApi.php';
$flags=[
 'partner_api_switch'=>getenv('PARTNER_POSTING_API_ENABLED')==='1',
 'recruiter_lite_switch'=>getenv('RECRUITER_LITE_ENABLED')==='1',
 'pilot_identity_evidence_switch'=>getenv('PARTNER_PILOT_VERIFIED')==='1',
 'mysql_pdo_driver'=>extension_loaded('pdo_mysql'),
 'mysql_dsn'=>str_starts_with((string)getenv('PARTNER_DB_DSN'),'mysql:'),
 'token_secret_present'=>strlen((string)getenv('PARTNER_TOKEN_HMAC_SECRET'))>=32,
 'publication_terms_version'=>strlen((string)getenv('PARTNER_TERMS_VERSION'))>=3
];
// Intentionally does not connect to DB or claim independent human verification.
$ready=count(array_filter($flags))===count($flags);
echo json_encode(['pilot_http_configured'=>$ready,'checks'=>$flags,
 'actual_employer_identity_verified'=>false,'actual_mysql_migration_tested_here'=>false,
 'public_launch_approval'=>false,'notes'=>'Environment flags alone are not independent identity/hosting/security evidence.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($ready?0:2);
