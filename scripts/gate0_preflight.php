<?php
/** Read-only, CLI-only environment inspection. Does not expose credentials or create costs. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(403);exit(1);}
$base=dirname(__DIR__);
$policy=json_decode((string)file_get_contents($base.'/config/provider_policy.json'),true);
$providers=[];
foreach(($policy?:[]) as $name=>$p){
    $checked=(string)($p['checked_at']??'');
    $age=$checked?floor((time()-(strtotime($checked)?:0))/86400):null;
    $providers[$name]=[
        'configured_enabled'=>($p['enabled']??false)===true,
        'policy_date'=>$checked?:null,
        'policy_review_due'=>$age===null||$age>30,
        'permissions_are_not_reverified_by_this_script'=>true
    ];
}
// Host facts observed here are local CLI observations, never self-attested release approval.
$privateRoot=realpath($base)?:'';
$webroot=realpath(dirname($base).'/public_html')?:null;
$openBase=(string)(ini_get('open_basedir')?:'');
$webserverBootstrap=$webroot!==null && is_file($webroot.'/index.php');
$privateOutsideWebroot=$webroot!==null
    && !str_starts_with($privateRoot,$webroot.DIRECTORY_SEPARATOR)
    && $privateRoot!==$webroot;
$check=[
    'checked_at'=>gmdate('c'),
    'runtime'=>['php'=>PHP_VERSION,'php_8_2_plus'=>PHP_VERSION_ID>=80200,'json'=>extension_loaded('json'),
        'openssl'=>extension_loaded('openssl'),'curl_optional'=>extension_loaded('curl'),'mbstring_optional'=>extension_loaded('mbstring'),
        'pdo_mysql_for_employer_pilot'=>extension_loaded('pdo_mysql'),
        'allow_url_fopen_for_provider_ingest'=>(bool)filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN),
        'cli'=>true],
    'paths'=>[
        'web_root'=>$base.'/public',
        'storage_writable'=>is_writable($base.'/storage'),
        'storage_outside_public'=>!str_starts_with(realpath($base.'/storage')?:'',(realpath($base.'/public')?:'__unknown__').'/'),
        'test_cache_exists'=>is_file($base.'/storage/fixture_jobs.json'),
        'production_cache_exists'=>is_file($base.'/storage/jobs.json'),
        'private_root_sibling_to_public_html'=>$privateOutsideWebroot,
        'public_html_bootstrap_present'=>$webserverBootstrap,
        'open_basedir_restrictions_configured'=>$openBase!=='',
        'php_runtime_can_read_private_root'=>is_readable($privateRoot.'/src/Jobs.php'),
        'private_storage_can_create_files'=>is_writable($base.'/storage')
    ],
    'providers'=>$providers,
    'external_hosting_plan_verified'=>false,
    'ssl_domain_and_cron_verified'=>false,
    'live_provider_requests_executed'=>false,
    'stage'=>'LOCAL_PREFLIGHT_ONLY'
];
echo json_encode($check,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
