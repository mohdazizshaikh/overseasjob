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
$check=[
    'checked_at'=>gmdate('c'),
    'runtime'=>['php'=>PHP_VERSION,'php_8_2_plus'=>PHP_VERSION_ID>=80200,'json'=>extension_loaded('json'),
        'openssl'=>extension_loaded('openssl'),'curl_optional'=>extension_loaded('curl'),'cli'=>true],
    'paths'=>[
        'web_root'=>$base.'/public',
        'storage_writable'=>is_writable($base.'/storage'),
        'storage_outside_public'=>!str_starts_with(realpath($base.'/storage')?:'',(realpath($base.'/public')?:'__unknown__').'/'),
        'test_cache_exists'=>is_file($base.'/storage/fixture_jobs.json'),
        'production_cache_exists'=>is_file($base.'/storage/jobs.json')
    ],
    'providers'=>$providers,
    'external_hosting_plan_verified'=>false,
    'ssl_domain_and_cron_verified'=>false,
    'live_provider_requests_executed'=>false,
    'stage'=>'LOCAL_PREFLIGHT_ONLY'
];
echo json_encode($check,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
