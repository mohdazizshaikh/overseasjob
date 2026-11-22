<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/EmployerDomainMysql.php';
try{
    if(getenv('OWNER_DOMAIN_ADMIN_ENABLED')!=='1'||getenv('RECRUITER_LITE_ENABLED')!=='1'||!extension_loaded('pdo_mysql'))throw new RuntimeException('Owner domain checks disabled');
    if(count($argv)!==4||!in_array($argv[1],['issue','check'],true))throw new InvalidArgumentException('Usage: issue|check org_ID example.org');
    $dsn=(string)getenv('PARTNER_DB_DSN');
    if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Configured MySQL required');
    $db=new PDO($dsn,(string)getenv('PARTNER_DB_USER'),(string)getenv('PARTNER_DB_PASSWORD'),
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $service=new EmployerDomainMysql($db);
    if($argv[1]==='issue'){
        $result=$service->issue($argv[2],$argv[3],time());
        echo "Set DNS TXT record below and independently confirm company/representative identity. This DOES NOT approve employer.\n";
        echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    }else{
        $host=EmployerDomainProof::txtHost($argv[3]);
        $records=dns_get_record($host,DNS_TXT);
        if($records===false)throw new RuntimeException('DNS query unavailable; verification NOT granted');
        $result=$service->confirm($argv[2],$argv[3],$records,time());
        echo $result?"Domain control recorded; company approval STILL REQUIRES independent owner review.\n":"Domain proof missing, expired, incorrect or untrusted.\n";
        if(!$result)exit(2);
    }
}catch(Throwable $e){fwrite(STDERR,'Domain proof not verified: '.$e->getMessage()."\n");exit(2);}
