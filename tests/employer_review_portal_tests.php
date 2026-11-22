<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/RecruiterPortal.php';
require dirname(__DIR__).'/src/EmployerDomainMysql.php';
$n=0;
function proof(string $name,bool $condition):void{
    global $n;if(!$condition){fwrite(STDERR,"FAIL $name\n");exit(1);} $n++;echo "PASS $name\n";
}
function denies(callable $fn):bool{try{$fn();return false;}catch(DomainException|InvalidArgumentException|PartnerConflict){return true;}}
$now=strtotime('2026-11-23T12:00:00Z');
$org=['id'=>'org_example1','company_name'=>'Example Company','status'=>'approved','email_verified_at'=>'2026-11-20 00:00:00',
'domain_verified_at'=>'2026-11-21 00:00:00','representative_verified_at'=>'2026-11-22 00:00:00',
'approved_at'=>'2026-11-22 01:00:00','approved_by'=>'human_owner','evidence_reference'=>'private/case-001',
'verified_domains_json'=>'["example.org"]'];
$job=['id'=>'job_'.str_repeat('a',24),'external_id'=>'req-001','origin_type'=>'direct_employer','owner_org_id'=>'org_example1',
'source_policy_id'=>'first_party_employer_pending_review','publish_consent'=>true,'status'=>'pending_review','revision'=>1,
'apply_url'=>'https://careers.example.org/apply/001','title'=>'Technical Support Specialist',
'description'=>'Provide customer troubleshooting and support services with quality standards.',
'work_mode'=>'remote','work_country'=>null,'location_text'=>'Remote: India eligible location claim',
'candidate_eligible_countries'=>['IN'],'valid_through'=>gmdate('Y-m-d\TH:i:s\Z',$now+20*86400),
'visa_sponsorship'=>'unknown','salary_min'=>null];
proof('Independent organisation verification requires all evidence',EmployerReview::validOrg($org));
foreach(['status','email_verified_at','domain_verified_at','representative_verified_at','approved_at','approved_by','evidence_reference','verified_domains_json'] as $key){
  $broken=$org; $broken[$key]=$key==='status'?'unverified':($key==='verified_domains_json'?'[]':null);
  proof('Missing '.$key.' blocks approval',!EmployerReview::validOrg($broken));
}
$broken=$org;$broken['verified_domains_json']='["bad local","example.org"]';
proof('Invalid verified domain list rejected',!EmployerReview::validOrg($broken));
$published=EmployerReview::decision($job,$org,'publish',1,'Evidence and posting verified by owner',$now);
proof('Human publish changes status + revision',$published['status']==='published'&&$published['revision']===2);
proof('Human review rejects stale revision',denies(fn()=>EmployerReview::decision($job,$org,'publish',2,'Independent manual check',$now)));
proof('Job with provider origin blocked',denies(fn()=>EmployerReview::decision([...$job,'origin_type'=>'provider'],$org,'publish',1,'Independent manual check',$now)));
proof('Cross-company job blocked',denies(fn()=>EmployerReview::decision([...$job,'owner_org_id'=>'org_other2'],$org,'publish',1,'Independent manual check',$now)));
proof('Missing publication consent blocked',denies(fn()=>EmployerReview::decision([...$job,'publish_consent'=>false],$org,'publish',1,'Independent manual check',$now)));
proof('Unverified org cannot publish',denies(fn()=>EmployerReview::decision($job,[...$org,'representative_verified_at'=>null],'publish',1,'Independent manual check',$now)));
proof('Unverified org can be rejected safely',EmployerReview::decision($job,[...$org,'status'=>'suspended'],'reject',1,'Independent manual check',$now)['status']==='rejected');
proof('Unverified org can be suspended safely',EmployerReview::decision($job,[...$org,'status'=>'unverified'],'suspend',1,'Independent manual check',$now)['status']==='suspended');
proof('Expired job cannot be published',denies(fn()=>EmployerReview::decision([...$job,'valid_through'=>gmdate('Y-m-d\TH:i:s\Z',$now-1)],$org,'publish',1,'Independent manual check',$now)));
proof('Expired job may be rejected',EmployerReview::decision([...$job,'valid_through'=>gmdate('Y-m-d\TH:i:s\Z',$now-1)],$org,'reject',1,'Independent manual check',$now)['status']==='rejected');
proof('Unverified apply hostname blocks publication',denies(fn()=>EmployerReview::decision([...$job,'apply_url'=>'https://evil.example.com/form'],$org,'publish',1,'Independent manual check',$now)));
proof('Insecure apply link blocks publication',denies(fn()=>EmployerReview::decision([...$job,'apply_url'=>'http://careers.example.org/apply'],$org,'publish',1,'Independent manual check',$now)));
proof('Invalid source policy blocks publication',denies(fn()=>EmployerReview::decision([...$job,'source_policy_id'=>'feed_himalayas'],$org,'publish',1,'Independent manual check',$now)));
proof('Insufficient reason blocks publication',denies(fn()=>EmployerReview::decision($job,$org,'publish',1,'okay',$now)));
proof('No repeat approval on a published state',denies(fn()=>EmployerReview::decision($published,$org,'publish',2,'Second manual review',$now)));
proof('No API-style auto-approval action',denies(fn()=>EmployerReview::decision($job,$org,'approve_automatically',1,'Second manual review',$now)));
$published['review_audit_confirmed']=true;
$projected=EmployerReview::publicProjection($published,$org,$now);
proof('Unreviewed published status alone never grants public visibility',EmployerReview::publicProjection([...$published,'review_audit_confirmed'=>false],$org,$now)===null);
proof('Published first-party job gets safe projection',is_array($projected)&&$projected['origin_type']==='direct_employer'&&$projected['public_url']==='/jobs/direct/'.$job['id']);
proof('No private organisation evidence leaks into public projection',!isset($projected['evidence_reference'])&&!isset($projected['approved_by']));
foreach([['not published',[...$job]],['closed',[...$published,'status'=>'closed']],['expired',[...$published,'valid_through'=>gmdate('Y-m-d\TH:i:s\Z',$now-1)]],['held',[...$published,'display_status'=>'held']],['provider',[...$published,'origin_type'=>'provider']],['untrusted rights',[...$published,'source_policy_id'=>'feed_jobicy']],['host moved',[...$published,'apply_url'=>'https://steal.example.net/collect']],['no consent',[...$published,'publish_consent'=>false]]] as [$label,$candidate]){
  proof('Public projection denies '.$label,EmployerReview::publicProjection($candidate,$org,$now)===null);
}
proof('Suspended employer instantly hides published job',EmployerReview::publicProjection($published,[...$org,'status'=>'suspended'],$now)===null);
proof('Revoked employer representative hides published job',EmployerReview::publicProjection($published,[...$org,'representative_verified_at'=>null],$now)===null);
proof('Recruiter gate stays off until real hosting evidence',!RecruiterPortal::enabled());
proof('Owner publication gate stays off by default',!EmployerReview::publicEnabled());
proof('Recruiter portal accepts valid lowercase email',RecruiterPortal::validEmail('verified@example.org'));
proof('Recruiter portal rejects CRLF email injection',!RecruiterPortal::validEmail("victim@example.org\r\nBcc:spam@example.com"));
proof('Recruiter portal rejects uppercase email login',!RecruiterPortal::validEmail('Admin@Example.org'));
$code=RecruiterPortal::newInviteCode();
proof('Email OTP exactly eight digits',preg_match('/^[0-9]{8}$/',$code)===1);
$secret=str_repeat('x',48);
proof('Email OTP binds member ID',RecruiterPortal::codeHash(11,$code,$secret)!==RecruiterPortal::codeHash(12,$code,$secret));
proof('Email OTP rejects invalid secret',denies(fn()=>RecruiterPortal::codeHash(11,$code,'short')));
proof('Email OTP rejects malformed code',denies(fn()=>RecruiterPortal::codeHash(11,'1234',$secret)));
proof('Password requires mixed-case and numbers',denies(fn()=>RecruiterPortal::validateNewPassword('alllowercaseonly')));
proof('Strong password accepted',(function(){RecruiterPortal::validateNewPassword('Secure12CharacterPass');return true;})());
$member=['id'=>11,'email'=>'verified@example.org','status'=>'active','email_verified_at'=>'2026-11-20 00:00:00','role'=>'manager','org'=>$org];
proof('Verified member can manage own jobs',RecruiterPortal::mayManage($member));
proof('Viewer cannot publish listings',!RecruiterPortal::mayManage([...$member,'role'=>'viewer']));
proof('Unverified member cannot manage listings',!RecruiterPortal::mayManage([...$member,'email_verified_at'=>null]));
proof('Suspended employer cannot submit via browser',!RecruiterPortal::mayManage([...$member,'org'=>[...$org,'status'=>'suspended']]));
$schema=file_get_contents(dirname(__DIR__).'/database/002_employer_review.mysql.sql');
proof('Review audit schema requires reason and actor',str_contains($schema,'reason VARCHAR(500)')&&str_contains($schema,'actor VARCHAR(90)'));
proof('Review migration requires representative evidence',str_contains($schema,'representative_verified_at'));
$schema=file_get_contents(dirname(__DIR__).'/database/003_recruiter_accounts.mysql.sql');
proof('Employer members stored separately from applicant data',str_contains($schema,'oj_employer_members')&&!str_contains($schema,'passport'));
proof('Email OTP stored as digest only',str_contains($schema,'code_digest CHAR(64)')&&!str_contains($schema,'plaintext_code'));
proof('Login throttle persisted in DB',str_contains($schema,'oj_recruiter_auth_rate'));
proof('No anonymous auto-verify route',!str_contains(file_get_contents(dirname(__DIR__).'/public/index.php'),'auto_approve_employer'));
proof('DNS domain normalisation validates legitimate public hostname',EmployerDomainProof::validDomain('careers.example.org'));
proof('DNS challenge uses fixed purpose-specific TXT name',EmployerDomainProof::txtHost('careers.example.org')==='_overseasjob-verify.careers.example.org');
foreach(['127.0.0.1','localhost','example.local','example.internal','xn--deceptive.com','CAPS.example.com','sub..example.org','example.test'] as $badDomain)
    proof('DNS rejects unsafe hostname '.$badDomain,!EmployerDomainProof::validDomain($badDomain));
$proofToken=EmployerDomainProof::makeChallenge();$digest=EmployerDomainProof::digest($proofToken);
proof('Secure random DNS challenge is purpose-bound',strlen($proofToken)===73);
proof('Exact DNS TXT proof validates',EmployerDomainProof::matches($digest,[['txt'=>$proofToken]]));
proof('Partial DNS TXT match cannot prove domain',!EmployerDomainProof::matches($digest,[['txt'=>substr($proofToken,0,40)]]));
proof('Invalid DNS TXT host evidence fails',!EmployerDomainProof::matches($digest,[['txt'=>'overseasjob-domain-proof='.str_repeat('f',48)]]));
proof('Oversized DNS TXT answer rejected',!EmployerDomainProof::matches($digest,array_fill(0,101,['txt'=>$proofToken])));
proof('Domain check script is private CLI-only',str_contains(file_get_contents(dirname(__DIR__).'/scripts/employer_domain.php'),"PHP_SAPI!=='cli'"));
proof('Domain proof schema does not approve employer',!str_contains(file_get_contents(dirname(__DIR__).'/database/004_employer_domain_proof.mysql.sql'),'approved_at'));
echo "\n$n employer review, projection, invited recruiter policy checks passed (MySQL and email delivery require staging).\n";
