<?php
declare(strict_types=1);
/** Admin-only private CLI, not a route or public_html endpoint. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/PartnerTokenAdmin.php';
try{
    if(getenv('PARTNER_TOKEN_ADMIN_ENABLED')!=='1')throw new RuntimeException('Token admin disabled');
    $dsn=getenv('PARTNER_DB_DSN');$secret=getenv('PARTNER_TOKEN_HMAC_SECRET');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')||!is_string($secret)||strlen($secret)<32)throw new RuntimeException('Admin environment not configured');
    $pdo=new PDO($dsn,(string)getenv('PARTNER_DB_USER'),(string)getenv('PARTNER_DB_PASSWORD'),[
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $admin=new PartnerTokenAdmin($pdo,$secret);
    $action=$argv[1]??'';
    if($action==='issue'){
        if(count($argv)!==4)throw new InvalidArgumentException('Usage: issue org_ID jobs:read,jobs:write');
        $result=$admin->issue($argv[2],explode(',',$argv[3]),time());
        fwrite(STDERR,"IMPORTANT: Bearer token printed once. Do not paste into chats, logs or browser code.\n");
        fwrite(STDOUT,json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
    }elseif($action==='revoke'){
        if(count($argv)!==3||!ctype_digit($argv[2]))throw new InvalidArgumentException('Usage: revoke token_numeric_id');
        echo $admin->revoke((int)$argv[2])?"Token revoked\n":"Token already revoked or missing\n";
    }else throw new InvalidArgumentException('Usage: issue org_ID scopes | revoke token_numeric_id');
}catch(Throwable $e){fwrite(STDERR,'Token administration failed: '.$e->getMessage()."\n");exit(2);}
