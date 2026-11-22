<?php
declare(strict_types=1);
/** Run ONLY against disposable, isolated, empty MySQL service / backed-up staging copy. */
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/src/RecruiterPortal.php';
require dirname(__DIR__).'/src/PartnerTokenAdmin.php';
require dirname(__DIR__).'/src/EmployerDomainMysql.php';
if(getenv('OJ_MYSQL_INTEGRATION')!=='1'){
    fwrite(STDERR,"Run only with OJ_MYSQL_INTEGRATION=1 and a disposable database.\n");exit(2);
}
if(!extension_loaded('pdo_mysql')){fwrite(STDERR,"MySQL PDO driver missing.\n");exit(2);}
$dsn=(string)getenv('PARTNER_DB_DSN');
if(!str_starts_with($dsn,'mysql:') || !preg_match('/(?:^|;)dbname=oj_test(?:;|$)/D',$dsn)
  || !preg_match('/(?:^|;)host=(?:127\.0\.0\.1|localhost)(?:;|$)/',$dsn)){
    fwrite(STDERR,"Refusing to run migrations outside disposable oj_test database.\n");exit(2);
}
$pdo=new PDO($dsn,(string)getenv('PARTNER_DB_USER'),(string)getenv('PARTNER_DB_PASSWORD'),[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
$pdo->exec("SET time_zone='+00:00'");
$n=0;
function checkDb(bool $v,string $message):void{global $n;if(!$v)throw new RuntimeException('FAIL '.$message);$n++;echo 'PASS '.$message."\n";}
foreach(['001_partner_pilot.mysql.sql','002_employer_review.mysql.sql','003_recruiter_accounts.mysql.sql','004_employer_domain_proof.mysql.sql'] as $name){
    $raw=(string)file_get_contents(dirname(__DIR__).'/database/'.$name);
    // The checked-in migrations contain no semicolons in string literals or stored procedures.
    foreach(explode(';',$raw) as $piece){
        $piece=trim(preg_replace('/^\s*--[^\n]*(?:\n|$)/m','',$piece));
        if($piece!=='')$pdo->exec($piece);
    }
    checkDb(true,'Migration '.$name.' executed on disposable MySQL');
}
$now=time();$expires=gmdate('Y-m-d\TH:i:s\Z',$now+20*86400);

$pdo->prepare("INSERT INTO oj_employer_orgs(id,company_name,status,verified_domains_json) VALUES (:id,'Pending Proof Company','pending_review',:domains)")
 ->execute(['id'=>'org_pending1','domains'=>'[]']);
$dns=new EmployerDomainMysql($pdo);
$challenge=$dns->issue('org_pending1','candidate.example.org',time());
checkDb($challenge['dns_txt_host']==='_overseasjob-verify.candidate.example.org','DNS token challenge issued for unverified company');
checkDb($dns->confirm('org_pending1','candidate.example.org',[['txt'=>'fake-token']],time())===false,'Incorrect DNS TXT cannot verify domain');
checkDb($dns->confirm('org_pending1','candidate.example.org',[['txt'=>$challenge['dns_txt_value']]],time())===true,'Matching DNS proof records domain control');
$unapproved=$pdo->query("SELECT status,domain_verified_at,approved_at,representative_verified_at FROM oj_employer_orgs WHERE id='org_pending1'")->fetch(PDO::FETCH_ASSOC);
checkDb($unapproved['status']==='pending_review'&&$unapproved['domain_verified_at']!==null&&$unapproved['approved_at']===null&&$unapproved['representative_verified_at']===null,'DNS domain control alone cannot approve company');
$expiredChallenge=$dns->issue('org_pending1','expired.example.org',$now-8*86400);
checkDb($dns->confirm('org_pending1','expired.example.org',[['txt'=>$expiredChallenge['dns_txt_value']]],$now)===false,'Expired DNS TXT challenge cannot verify domain');

$pdo->prepare('INSERT INTO oj_employer_orgs(id,company_name,status,email_verified_at,domain_verified_at,representative_verified_at,approved_at,approved_by,evidence_reference,verified_domains_json)
 VALUES (:id,\'Verified Example Co\',\'approved\',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),\'human_owner\',\'private/case-001\',:domains)')
 ->execute(['id'=>'org_example1','domains'=>'["example.org"]']);
checkDb(true,'Independently reviewed TEST organisation created in disposable DB');
$portal=new RecruiterMysql($pdo);$captured=null;
checkDb($portal->invite('org_example1','manager@example.org',static function($email,$code)use (&$captured){$captured=[$email,$code];return true;},$now),
 'Invitation queued through test-only injected mailer');
checkDb(is_array($captured)&&$captured[0]==='manager@example.org'&&preg_match('/^[0-9]{8}$/',$captured[1])===1,'Invite code only observed by injected test mailer');
checkDb($portal->confirm('manager@example.org','00000000','CorrectPassword123',$now)===false,'Incorrect code rejected');
checkDb($portal->confirm('manager@example.org',$captured[1],'CorrectPassword123',$now)===true,'Email confirmation consumes one-time code');
checkDb($portal->confirm('manager@example.org',$captured[1],'CorrectPassword123',$now)===false,'Verification code cannot be replayed');
checkDb($portal->authenticate('manager@example.org','WrongPassword12','203.0.113.11',$now)===null,'Wrong password rejected');
$member=$portal->authenticate('manager@example.org','CorrectPassword123','203.0.113.11',$now);
checkDb(is_array($member)&&RecruiterPortal::mayManage($member),'Verified member signs in after email confirmation');
checkDb($portal->jobs($member)===[],'New employer dashboard starts empty');
$form=['external_id'=>'ref-1001','title'=>'Customer Support Specialist',
 'description'=>'Support users with troubleshooting across official employer support channels.','employment_type'=>'full_time',
 'work_mode'=>'remote','location_text'=>'Remote from India','candidate_eligible_countries'=>['IN'],
 'apply_url'=>'https://careers.example.org/apply/1001','valid_through'=>$expires,'consent_to_publish'=>true];
$created=$portal->create($member,$form,$now,'pilot-terms-v1');$id=$created['id'];
checkDb(($created['status']??'')==='pending_review','Browser submission remains pending human review');
checkDb(count($portal->jobs($member))===1,'Recruiter dashboard reads only its org jobs');
$review=new EmployerReviewMysql($pdo);
checkDb(count($review->pending())===1,'Owner sees one pending employer job');
checkDb($review->published($now)===[],'Unreviewed listing never appears in public projection');
$decision=$review->review($id,1,'publish','Independent employer and job evidence validated','human_owner',$now);
checkDb($decision['status']==='published'&&$decision['revision']===2,'Human approval explicitly publishes and versions job');
checkDb(count($review->published($now))===1,'Only owner-reviewed job appears in direct employer projection');
checkDb((int)$pdo->query('SELECT COUNT(*) FROM oj_partner_review_audit')->fetchColumn()===1,'Human review decision and reason audited');
$changed=$portal->edit($member,$id,['title'=>'Senior Customer Support Specialist'],2,$now+1,'pilot-terms-v1');
checkDb($changed!==null&&$changed['status']==='pending_review','Employer edit immediately returns published job to review');
checkDb($review->published($now+1)===[],'Changed listing disappears from public projection pending re-review');
$second=$review->review($id,3,'publish','Reviewed revised scope and correct employer rights','human_owner',$now+2);
checkDb($second['status']==='published','Owner approves revised role only after new review');
$closed=$portal->close($member,$id,$now+3);
checkDb($closed!==null&&$closed['status']==='closed','Employer withdraws a published listing');
checkDb($review->published($now+3)===[],'Closed direct employer listing is immediately hidden');
$tokenSecret=(string)getenv('PARTNER_TOKEN_HMAC_SECRET');
$tokenAdmin=new PartnerTokenAdmin($pdo,$tokenSecret);
$issued=$tokenAdmin->issue('org_example1',['jobs:read','jobs:write','jobs:close'],$now);
checkDb(strlen($issued['token'])===64,'API token issued once for trusted org');
$headers=['authorization'=>'Bearer '.$issued['token'],'content-type'=>'application/json','idempotency-key'=>'safe-job-1002'];
$apiForm=[...$form,'external_id'=>'ref-1002'];
$r=PartnerApi::dispatch('POST','/api/v1/partner/jobs',$headers,json_encode($apiForm,JSON_THROW_ON_ERROR),[],['HTTPS'=>'on'],
 new PartnerMysqlStore($pdo),$now,$tokenSecret,'pilot-terms-v1');
checkDb($r['status']===201&&$r['body']['status']==='pending_review','Live MySQL-backed partner create is pending review');
$replay=PartnerApi::dispatch('POST','/api/v1/partner/jobs',$headers,json_encode($apiForm,JSON_THROW_ON_ERROR),[],['HTTPS'=>'on'],
 new PartnerMysqlStore($pdo),$now,$tokenSecret,'pilot-terms-v1');
checkDb($replay['status']===200&&$replay['body']['id']===$r['body']['id'],'MySQL transaction idempotency replay correct');
checkDb($tokenAdmin->revoke($issued['token_id'])===true,'Trusted CLI revokes API token');
$revoked=PartnerApi::dispatch('GET','/api/v1/partner/jobs',$headers,'',[],['HTTPS'=>'on'],new PartnerMysqlStore($pdo),$now,$tokenSecret,'pilot-terms-v1');
checkDb($revoked['status']===401,'Revoked API key rejected immediately');
$pdo->exec("UPDATE oj_employer_orgs SET status='suspended' WHERE id='org_example1'");
checkDb($portal->current($member['id'])===null,'Suspended org loses recruiter browser access immediately');
checkDb($review->published($now)===[],'Suspended org has no public listing');
echo "\n$n MySQL 8 staging-contract integration assertions passed on disposable oj_test. NOT Hostinger production.\n";
