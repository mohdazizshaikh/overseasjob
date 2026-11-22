<?php
declare(strict_types=1);
// A verified shared-host setup may keep private app files beside public_html and
// load this controller through a thin webroot bootstrap. Never derive this from user input.
if(!defined('OJ_PROJECT_ROOT'))define('OJ_PROJECT_ROOT',dirname(__DIR__));
$verifiedRoot=realpath((string)OJ_PROJECT_ROOT);
if($verifiedRoot===false||!is_file($verifiedRoot.'/src/Jobs.php')||!is_dir($verifiedRoot.'/storage')){
    http_response_code(503);exit('Site temporarily unavailable');
}
require OJ_PROJECT_ROOT.'/src/Jobs.php';
require OJ_PROJECT_ROOT.'/src/Guides.php';
require OJ_PROJECT_ROOT.'/src/Reports.php';
require OJ_PROJECT_ROOT.'/src/Launch.php';
require OJ_PROJECT_ROOT.'/src/Analytics.php';
require OJ_PROJECT_ROOT.'/src/OwnerAuth.php';
require OJ_PROJECT_ROOT.'/src/PartnerMysqlStore.php';
require OJ_PROJECT_ROOT.'/src/EmployerReview.php';
require OJ_PROJECT_ROOT.'/src/RecruiterPortal.php';
require OJ_PROJECT_ROOT.'/src/RecruiterMailer.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

$root=OJ_PROJECT_ROOT;
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
if(str_contains($path,"\0")||str_contains($path,'..')){http_response_code(400);exit('Bad request');}
function h(mixed $str):string{return htmlspecialchars((string)$str,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function fmtdate(mixed $value):string{
    if(!$value)return 'Not disclosed';
    $t=strtotime((string)$value);
    return $t?gmdate('d M Y, H:i', $t).' UTC':'Not disclosed';
}
function route(string $p):string{return h($p);}
function frameStart(string $pageTitle, bool $noindex=false):void{
    $canonicalTitle=$pageTitle==='Home'?'OverseasJob.in — Global career decisions':h($pageTitle).' | OverseasJob.in';
    $robots=($noindex || !Launch::allowed(OJ_PROJECT_ROOT))?'noindex,nofollow':'index,follow';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="robots" content="'.$robots.'"><meta name="description" content="Compare overseas career opportunities with honest eligibility labels and free practical tools.">';
    echo '<title>'.$canonicalTitle.'</title><link rel="icon" href="/assets/overseasjob-logo.png"><link rel="stylesheet" href="/assets/style.css"></head><body data-analytics-enabled="'.(getenv('ANALYTICS_ENABLED')==='1' && Launch::allowed(OJ_PROJECT_ROOT)?'1':'0').'">';
    echo '<a class="skip-link" href="#main">Skip to content</a>';
    if(!Launch::allowed(OJ_PROJECT_ROOT))echo '<div class="preview-banner">LOCAL RELEASE CANDIDATE — not a deployed or verified job service</div>';
    echo '<header class="site-header"><div class="container header-inner"><a class="brand" href="/" aria-label="OverseasJob home"><img src="/assets/overseasjob-logo.png" alt="OverseasJob"></a>';
    echo '<nav aria-label="Main navigation"><a href="/jobs">Find jobs</a><a href="/tools">Free tools</a><a href="/my-career">My career</a><a href="/daily">Daily mission</a><a href="/guides">Guides</a><a href="/safety">Safety</a></nav><a class="nav-action" href="/jobs?path=remote_from_india">Explore opportunities</a></div></header>';
    echo '<main id="main">';
}
function frameEnd():void{
    echo '</main><footer class="footer"><div class="container footer-grid"><div><strong>OverseasJob.in</strong><p>Discover, compare, prepare. No guaranteed jobs or visas.</p></div><div><a href="/sources">Job sources</a><a href="/privacy">Privacy & data controls</a><a href="/terms">Terms</a><a href="/report-job">Report a job</a></div><div><a href="/about">About</a><a href="/contact">Contact</a><a href="/safety">Safety</a><a href="/daily">Daily mission</a></div></div><div class="container tiny">Development preview. Current live source availability, hosting and legal compliance are not certified. © 2026 OverseasJob.in</div></footer>';
    echo '<script type="module" src="/assets/app.js"></script><script type="module" src="/assets/privacy_ui.mjs"></script></body></html>';
}
function heading(string $eyebrow,string $title,string $text=''):void{
    echo '<section class="page-head"><div class="container"><span class="eyebrow">'.h($eyebrow).'</span><h1>'.h($title).'</h1>';
    if($text!=='')echo '<p class="lead">'.h($text).'</p>';
    echo '</div></section>';
}
function jobCard(array $j,bool $short=false):void{
    $id=(string)$j['id'];
    $key=(string)($j['provider_route_key']??$j['provider_job_id']);
    $slug=preg_replace('/[^a-z0-9]+/','-',strtolower((string)$j['title']));
    $url='/jobs/'.rawurlencode($j['provider']).'/'.rawurlencode($key).'/'.$slug;
    $state=$j['eligible_from_in'];
    $geo=$j['location_eligibility']??'unknown';
    $tz=$j['time_zone_eligibility']??'unknown';
    if(Jobs::indiaFullyEligible($j)) {$tag='India location & timezone allowed by source';$tagClass='tag-allowed';}
    elseif($state==='not_allowed') {$tag='India location/timezone restricted';$tagClass='tag-restricted';}
    elseif($geo==='allowed') {$tag='India location allowed; timezone not confirmed';$tagClass='tag-unknown';}
    else {$tag='India eligibility not confirmed';$tagClass='tag-unknown';}
    echo '<article class="job-card"><div class="card-top"><div><div class="eyebrow">'.h($j['source_name']).' · Source-linked listing</div><h3><a href="'.h($url).'">'.h($j['title']).'</a></h3><p class="muted">'.h($j['company_name']?:'Employer not disclosed').'</p></div><span class="tag '.h($tagClass).'">'.h($tag).'</span></div>';
    if(!$short&&($j['description']??''))echo '<p class="job-excerpt">'.h($j['description']).'</p>';
    if(($salary=Jobs::salaryLabel($j))!==null)echo '<p class="muted">Disclosed salary: '.h($salary).'</p>';
    echo '<div class="card-meta"><span>Remote</span><span>Seen in feed: '.h(fmtdate($j['last_seen_at'])).'</span><span>Visa: not confirmed</span></div><div class="card-actions"><a class="button button-primary" href="'.h($url).'">Review decision card</a><button class="button button-secondary" data-job-id="'.h($id).'" data-job-title="'.h($j['title']).'" data-job-company="'.h($j['company_name']?:'Company not disclosed').'" data-job-url="'.h($j['source_url']).'" type="button">Save job</button></div></article>';
}
$jobs=Jobs::listPublic($root.'/storage/jobs.json');
$config=json_decode((string)file_get_contents($root.'/config/provider_policy.json'),true)?:[];
$jobs=array_values(array_filter($jobs,static fn($j)=>($config[$j['provider']]['enabled']??false)===true));
$directJobs=[];
if(EmployerReview::publicEnabled() && Launch::allowed($root)){
    try{$directJobs=EmployerReviewMysql::fromEnvironment()->published(time(),60);}
    catch(Throwable){$directJobs=[];}// Fail closed, never leak unreviewed records on database failure.
}
function directJobCard(array $j):void{
    echo '<article class="job-card"><div class="eyebrow">Direct employer submission · owner-reviewed</div>';
    echo '<h3><a href="'.h($j['public_url']).'">'.h($j['title']).'</a></h3><p class="muted">'.h($j['company_name']).'</p>';
    echo '<p>'.h(substr((string)$j['description'],0,260)).'</p>';
    echo '<p class="muted">'.h($j['location_text']).' · Sponsorship: '.h($j['visa_sponsorship']).'</p>';
    echo '<p class="tiny">Expiry '.h(fmtdate($j['valid_through'])).' · Company was checked for this pilot; this is not government certification.</p>';
    echo '<a class="button button-secondary" href="'.h($j['public_url']).'">Review employer listing</a></article>';
}


if($path==='/api/v1/partner'||str_starts_with($path,'/api/v1/partner/')){
    header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
    if(!PartnerApi::gateReady()){http_response_code(404);header('Content-Type: application/json');echo '{"error":{"code":"disabled","message":"Partner programme not enabled"}}';exit;}
    try{
        $headers=[];
        foreach(getallheaders() ?: [] as $k=>$v)if(is_string($v))$headers[strtolower($k)]=$v;
        // Avoid unlimited upload memory; cap at the documented 32 KiB request maximum.
        $raw=file_get_contents('php://input',false,null,0,PartnerApi::MAX_JSON_BYTES+1);
        $store=PartnerMysqlStore::fromEnvironment();
        $result=PartnerApi::dispatch($_SERVER['REQUEST_METHOD']??'GET',$path,$headers,(string)$raw,$_GET,$_SERVER,
            $store,time(),(string)getenv('PARTNER_TOKEN_HMAC_SECRET'),(string)getenv('PARTNER_TERMS_VERSION'));
        http_response_code($result['status']);
        foreach($result['headers'] as $key=>$value)header($key.': '.$value);
        echo json_encode($result['body'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }catch(Throwable){http_response_code(503);header('Content-Type: application/json');echo '{"error":{"code":"unavailable","message":"Partner service unavailable"}}';}
    exit;
}
if($path==='/owner'){
  header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');
  if(!OwnerAuth::configured()||!OwnerAuth::secureRequest($_SERVER)){
      http_response_code(404);exit('Not found');
  }
  session_name('oj_owner');ini_set('session.use_strict_mode','1');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/owner','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
  session_start();
  if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
  $error='';$notice='';$now=time();
  if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
      $token=(string)($_POST['csrf']??'');
      if(!hash_equals((string)$_SESSION['csrf'],$token)){$error='Invalid session token. Reload the page.';http_response_code(400);}
      else{
          $action=(string)($_POST['action']??'');
          if($action==='login'&&!OwnerAuth::active($_SESSION,$now)){
              $pass=(string)($_POST['password']??'');
              try{
                  if(!OwnerAuth::mayAttempt($root.'/storage',(string)($_SERVER['REMOTE_ADDR']??''),$now)){$error='Too many attempts; try again later.';http_response_code(429);}
                  elseif(!OwnerAuth::verifyPassword($pass)){
                      OwnerAuth::failedAttempt($root.'/storage',(string)($_SERVER['REMOTE_ADDR']??''),$now);
                      $error='Sign-in unsuccessful.';http_response_code(401);
                  }else{
                      session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(24));
                      $_SESSION['owner_logged_in']=true;$_SESSION['owner_until']=$now+1200;$notice='Authenticated owner session.';
                  }
              }catch(Throwable){$error='Owner login temporarily unavailable.';http_response_code(503);}
          }elseif($action==='logout'&&OwnerAuth::active($_SESSION,$now)){
              $_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(24));$notice='Signed out.';
          }elseif(OwnerAuth::active($_SESSION,$now)){
              $note=trim((string)($_POST['note']??''));
              if(strlen($note)>280)$error='Review reason exceeds 280 characters.';
              else{
                  try{
                      if($action==='decide'){
                         $decision=(string)($_POST['decision']??'');
                         Reports::decide($root.'/storage',(string)($_POST['report_id']??''),$decision,$note);
                         $notice='Review action recorded with audit trail.';
                      }elseif($action==='reopen'){
                         Reports::reopen($root.'/storage',(string)($_POST['job_id']??''),$note);
                         $notice='Quarantine removed with audit trail.';
                      }elseif($action==='employer_review' && EmployerReview::reviewEnabled()){
                         if(!ctype_digit((string)($_POST['revision']??'')))throw new InvalidArgumentException('Revision required');
                         $review=EmployerReviewMysql::fromEnvironment();
                         $result=$review->review((string)($_POST['job_id']??''),(int)$_POST['revision'],
                            (string)($_POST['decision']??''),$note,'platform_owner',time());
                         $notice='Employer job '.h($result['status']).' with permanent review audit.';
                      }else{$error='Unsupported action.';http_response_code(400);}
                  }catch(Throwable){$error='Review action could not be completed. Check the private audit and queue.';http_response_code(422);}
              }
          }else{$error='Session expired. Sign in again.';http_response_code(401);}
      }
  }
  frameStart('Owner moderation',true);heading('Private owner access','Moderation and health','HTTPS-only administrator panel. Applicant information, job posts and provider terms are never verified automatically.');
  echo '<section class="section section-tight"><div class="container reading surface">';
  if($error)echo '<p role="alert" class="notice error">'.h($error).'</p>';
  if($notice)echo '<p role="status" class="notice success">'.h($notice).'</p>';
  if(!OwnerAuth::active($_SESSION,$now)){
     echo '<form method="post" action="/owner"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="action" value="login"><label>Owner password<input type="password" name="password" autocomplete="current-password" required maxlength="1024"></label><button class="button button-primary" type="submit">Sign in</button></form>';
  }else{
     $_SESSION['owner_until']=$now+1200;
     $pending=[];$holds=[];
     try{$pending=array_values(array_filter(Reports::read($root.'/storage'),static fn($r)=>($r['state']??'')==='pending_review'));$holds=Reports::quarantine($root.'/storage');}
     catch(Throwable){echo '<p class="notice error">Moderation store unavailable; do not assume reports have disappeared.</p>';}
     echo '<h2>Private operational snapshot</h2><p>Published-cache eligible listings: '.count($jobs).'. Pending reports: '.count($pending).'. Quarantined listings: '.count($holds).'.</p><p class="tiny">Real provider coverage, employer verification, SSL evidence, backup restore and legal approval still require separate checks. This is not a public job inventory or launch approval.</p>';
     echo '<h2>Pending job reports</h2>';
     if(!$pending)echo '<p>No pending reports in the private queue.</p>';
     foreach(array_slice($pending,0,200) as $r){
         echo '<article class="owner-report"><p><strong>'.h($r['job_id']??'Unknown').'</strong> · '.h($r['reason']??'').'<br><small>'.h($r['at']??'').'</small></p><p>'.h($r['details']??'').'</p>';
         echo '<form method="post" action="/owner"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="action" value="decide"><input type="hidden" name="report_id" value="'.h($r['id']??'').'"><label>Decision<select name="decision"><option value="quarantine">Quarantine listing</option><option value="dismiss">Dismiss report</option><option value="resolve">Resolve</option></select></label><label>Review reason<input name="note" maxlength="280" required></label><button type="submit" class="button button-secondary">Record decision</button></form></article>';
     }
     if(EmployerReview::reviewEnabled()){
         echo '<h2>Pending direct-employer job review</h2>';
         try{
             $directPending=EmployerReviewMysql::fromEnvironment()->pending(40);
             if(!$directPending)echo '<p>No employer submissions pending review.</p>';
             foreach($directPending as $entry){
                 $dj=$entry['job'];$org=$entry['org'];$evidence=EmployerReview::validOrg($org);
                 echo '<article class="owner-report"><p><strong>'.h($dj['title']).'</strong> · '.h($org['company_name']).' · Revision '.h($dj['revision']).'</p>';
                 echo '<p>Eligibility: '.($evidence?'Independent verification fields recorded':'Verification evidence incomplete — cannot publish').'.</p>';
                 echo '<p>Apply: '.h($dj['apply_url']??'unknown').' · Expires '.h($dj['valid_through']??'unknown').'</p>';
                 echo '<p>'.h(substr((string)($dj['description']??''),0,750)).'</p>';
                 echo '<form method="post" action="/owner"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'">';
                 echo '<input type="hidden" name="action" value="employer_review"><input type="hidden" name="job_id" value="'.h($dj['id']).'">';
                 echo '<input type="hidden" name="revision" value="'.h($dj['revision']).'">';
                 echo '<label>Decision<select name="decision"><option value="reject">Reject</option><option value="suspend">Suspend</option>';
                 if($evidence)echo '<option value="publish">Publish after review</option>';
                 echo '</select></label><label>Independent review reason (12–280 characters)<input name="note" minlength="12" maxlength="280" required></label>';
                 echo '<button class="button button-secondary" type="submit">Record reviewed decision</button></form></article>';
             }
         }catch(Throwable){echo '<p class="notice error">Employer review store unavailable; publication remains blocked.</p>';}
     }
     echo '<h2>Held job listings</h2>';
     if(!$holds)echo '<p>No listings currently held.</p>';
     foreach(array_slice($holds,0,200,true) as $jobId=>$hold){
         echo '<article class="owner-report"><strong>'.h($jobId).'</strong><p>'.h($hold['reason']??'').'</p><form method="post" action="/owner"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="action" value="reopen"><input type="hidden" name="job_id" value="'.h($jobId).'"><label>Reason for reinstatement<input name="note" maxlength="280" required></label><button type="submit" class="button button-secondary">Reopen after review</button></form></article>';
     }
     echo '<form method="post" action="/owner"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="action" value="logout"><button type="submit" class="button button-text">Sign out</button></form>';
  }
  echo '</div></section>';frameEnd();exit;
}
if($path==='/'){
 frameStart('Home');
 echo '<section class="hero"><div class="container hero-grid"><div><span class="eyebrow">Your global career, made clearer</span><h1>Find opportunities.<br><span>Make better decisions.</span></h1><p class="lead">Source-linked remote jobs, honest India eligibility labels and free salary, offer and timezone tools—without a candidate account.</p><div class="hero-actions"><a class="button button-primary button-lg" href="/jobs?path=remote_from_india">Find India-eligible jobs</a><a class="button button-light button-lg" href="/tools">Explore free tools</a></div><div class="trust-notes"><span>✓ No candidate login</span><span>✓ Real source links only</span><span>✓ No invented salaries</span></div></div><div class="hero-panel"><div class="panel-label">HOW OVERSEASJOB HELPS</div><h2>Know what matters before applying</h2><div class="step"><b>01</b><span>Choose a role and remote-work preference</span></div><div class="step"><b>02</b><span>Check eligibility evidence and source freshness</span></div><div class="step"><b>03</b><span>Estimate salary, savings and next steps</span></div><div class="panel-foot">Clear evidence over misleading match scores</div></div></div></section>';
 echo '<section class="section"><div class="container"><div class="section-heading"><div><span class="eyebrow">Practical tools</span><h2>Useful before your next move</h2></div><a class="inline-link" href="/tools">See all available tools</a></div><div class="feature-grid">';
 foreach([['01','Salary & savings','Compare salary against expenses without made-up exchange rates.','/tools/salary-savings'],['02','Compare offers','Understand guaranteed compensation and one-time moving costs.','/tools/offer-compare'],['03','Time-zone overlap','Find workable meeting hours with daylight saving changes.','/tools/time-zone-overlap']] as $f)
 echo '<a class="feature" href="'.$f[3].'"><span class="feature-icon">'.$f[0].'</span><h3>'.$f[1].'</h3><p>'.$f[2].'</p><span class="feature-more">Open tool →</span></a>';
 echo '</div></div></section>';
 echo '<section class="section slate"><div class="container"><div class="section-heading"><div><span class="eyebrow">Real job feed</span><h2>Recently seen opportunities</h2></div><a class="inline-link" href="/jobs">Browse jobs</a></div>';
 if(!$jobs)echo '<div class="empty-state"><strong>No verified-source jobs in the local cache yet.</strong><p>Jobs appear only after an authorised backend sync has succeeded. We never fill gaps with fictional listings. Try the calculators meanwhile.</p><a class="button button-secondary" href="/tools">Use career tools</a></div>';
 else{echo '<div class="job-grid">';foreach(array_slice($jobs,0,4) as $j)jobCard($j,true);echo '</div>';}
 echo '</div></section>';frameEnd();exit;
}
if($path==='/jobs'){
 $query=trim(substr((string)($_GET['q']??''),0,100));
 $workPath=(string)($_GET['path']??'all');
 if(!in_array($workPath,['all','remote_from_india','remote_other','onsite_abroad'],true))$workPath='all';
 $filtered=Jobs::search($jobs,$query,$workPath);
 frameStart('Browse source-linked jobs',isset($_GET['q'])||isset($_GET['path']));
 heading('Job discovery','Find your next opportunity','Search real imported jobs. Eligibility is displayed only when the source gives evidence.');
 echo '<section class="section section-tight"><div class="container"><form class="filter-form" action="/jobs" method="get"><label>Role or company<input type="search" name="q" maxlength="100" value="'.h($query).'" placeholder="e.g. IT Support"></label><label>Work path<select name="path">';
 foreach(['all'=>'All source-linked jobs','remote_from_india'=>'Remote: location + timezone confirmed','remote_other'=>'Remote: restricted / unknown','onsite_abroad'=>'On-site overseas (evidence required)'] as $k=>$v)echo '<option value="'.$k.'"'.($k===$workPath?' selected':'').'>'.$v.'</option>';
 echo '</select></label><button class="button button-primary" type="submit">Search jobs</button></form>';
 $directFiltered=array_values(array_filter($directJobs,static function(array $j)use($query,$workPath):bool{
     if($query!==''&&!str_contains(strtolower($j['title'].' '.$j['company_name'].' '.$j['description']),strtolower($query)))return false;
     return match($workPath){
         'remote_from_india'=>false, // A country field alone does NOT prove time-zone approval.
         'remote_other'=>$j['work_mode']==='remote',
         'onsite_abroad'=>($j['work_mode']==='onsite'||$j['work_mode']==='hybrid')&&($j['work_country']??null)!=='IN',
         default=>true
     };
 }));
 echo '<p class="result-count">'.(count($filtered)+count($directFiltered)).' visible results ('.count($filtered).' source-linked, '.count($directFiltered).' independently reviewed direct submissions).</p>';
 if(!$filtered&&!$directFiltered)echo '<div class="empty-state"><strong>No confirmed matches for those filters.</strong><p>Some APIs provide mostly remote jobs and cannot guarantee relocation or India eligibility. Broaden your filters, or use our tools to prepare while new jobs are imported.</p><a class="button button-secondary" href="/jobs">Clear filters</a><a class="button button-text" href="/tools">Explore free tools →</a></div>';
 if($filtered){echo '<div class="job-grid">';foreach(array_slice($filtered,0,60) as $j)jobCard($j);echo '</div>';}
 if($directFiltered){echo '<h2>Direct employer submissions (separately reviewed)</h2><div class="job-grid">';foreach($directFiltered as $j)directJobCard($j);echo '</div>';}
 echo '</div></section>';frameEnd();exit;
}
if(preg_match('#^/jobs/direct/(job_[a-f0-9]{24})$#',$path,$m)){
    $record=null;
    foreach($directJobs as $item){if($item['id']===$m[1]){$record=$item;break;}}
    if($record===null){http_response_code(404);frameStart('Employer listing unavailable',true);heading('Not available','Listing closed or pending','This direct employer job is not approved or has expired.');frameEnd();exit;}
    frameStart($record['title'],true);
    heading('Direct-employer Job Decision Card',$record['title'],$record['company_name'].' · Independently reviewed submission (not a government-verified employer badge)');
    echo '<section class="section section-tight"><div class="container detail-grid"><article class="surface">';
    echo '<h2>Employer-submitted facts</h2><dl class="facts">';
    foreach([['Work mode',$record['work_mode']],['Location',$record['location_text']],
       ['Work country',$record['work_country']??'Not disclosed'],
       ['Applicant country criteria',implode(', ',$record['candidate_eligible_countries'])?:'Not disclosed'],
       ['Visa sponsorship',$record['visa_sponsorship']],['Job expires',fmtdate($record['valid_through'])]] as $fact)
       echo '<div><dt>'.h($fact[0]).'</dt><dd>'.h($fact[1]).'</dd></div>';
    echo '</dl><h3>Role description</h3><p>'.nl2br(h($record['description']),false).'</p>';
    echo '<p class="tiny">Employer statement, reviewed for publication. Location criteria do not guarantee work authorisation, visa or employment.</p></article>';
    echo '<aside class="surface apply-box"><h2>Apply at the employer website</h2><a class="button button-primary full" href="'.h($record['apply_url']).'" target="_blank" rel="noopener noreferrer nofollow">Visit employer application</a>';
    echo '<p class="tiny">Check the destination domain and terms before sharing personal data. No application is submitted from this site.</p></aside></div></section>';
    frameEnd();exit;
}
if(preg_match('#^/jobs/(jobicy|himalayas)/([^/]+)(?:/[^/]*)?$#',$path,$m)){
 $match=null;foreach($jobs as $j){if($j['provider']===$m[1]&&(string)($j['provider_route_key']??$j['provider_job_id'])===$m[2]){$match=$j;break;}}
 if(!$match){http_response_code(404);frameStart('Job not available',true);heading('Source status','This listing is not currently available','It may be stale, closed, restricted or not yet imported.');echo '<section class="section"><div class="container"><a href="/jobs" class="button button-secondary">Back to jobs</a></div></section>';frameEnd();exit;}
 frameStart($match['title'],true);
 heading('Source-grounded Job Decision Card',$match['title'],($match['company_name']?:'Company not disclosed').' · Sourced from '.$match['source_name']);
 echo '<section class="section section-tight"><div class="container detail-grid"><article class="surface"><h2>What we can verify from the feed</h2><dl class="facts">';
 $facts=[['Work arrangement','Remote'],['India applicant eligibility',match($match['eligible_from_in']){'allowed'=>($match['time_zone_eligibility']==='allowed'?'Explicit India location and timezone allowed':'India location allowed; timezone not disclosed'),'not_allowed'=>'Explicitly not allowed','unknown'=>'Not confirmed'}],['Visa sponsorship','Not disclosed / unknown'],['Disclosed salary',Jobs::salaryLabel($match)??'Not disclosed'],['Source-reported publication date',fmtdate($match['source_published_at'])],['Last source update',fmtdate($match['source_updated_at'])],['Last seen in source',fmtdate($match['last_seen_at'])],['Source URL',parse_url($match['source_url'],PHP_URL_HOST)]];
 foreach($facts as $f)echo '<div><dt>'.h($f[0]).'</dt><dd>'.h($f[1]).'</dd></div>';
 echo '</dl><h3>Why it may match</h3><p>It is a remote role in a currently accepted source record. Eligibility is derived only from the provider’s recorded location/timezone data. This is not employment authorisation or a guaranteed opening.</p><h3>Source evidence</h3><ul>';
 foreach($match['eligibility_evidence'] as $e)echo '<li>'.h($e['provider_field']).': '.h($e['fact']).'</li>';
 echo '</ul><h3>Role summary</h3><p>'.h($match['description']?:'Please read full details at the original source.').'</p></article><aside class="surface apply-box"><div class="eyebrow">YOUR NEXT ACTION</div><h3>Apply only on the source website</h3><p>Review the full posting and hiring location restrictions before sharing personal data.</p><a class="button button-primary full" target="_blank" rel="noopener noreferrer nofollow" data-offsite-apply="true" href="'.h($match['apply_url']).'">Open '.h($match['source_name']).' apply link ↗</a><p class="tiny">External destination: '.h(parse_url($match['apply_url'],PHP_URL_HOST)).'</p><a class="button button-secondary full" href="/tools/salary-savings">Estimate potential savings</a><a class="button button-secondary full" href="/report-job?id='.rawurlencode($match['id']).'">Report suspicious listing</a><p class="tiny">Clicking Apply does not mean an application was submitted. No CV is collected here.</p></aside></div></section>';frameEnd();exit;
}
if($path==='/tools'){
 frameStart('Free career tools');heading('Free, private, practical','Useful career tools','Calculations use your own inputs. No paid AI, invented live taxes or forced accounts.');
 echo '<section class="section section-tight"><div class="container feature-grid">';
 foreach([['01','Salary & savings','Estimate take-home pay and annual savings.','/tools/salary-savings'],['02','Offer comparison','Compare two jobs with honest assumptions.','/tools/offer-compare'],['05','Time-zone overlap','Find meeting windows across time zones and DST.','/tools/time-zone-overlap'],['03','Resume & cover letter kit','Three original ATS-readable layouts; downloadable HTML and text.','/tools/resume-kit'],['04','Relocation budget','Plan pre-departure expenses, reserve and contingency.','/tools/relocation-budget'],['06','Application tracker','Keep application follow-ups privately on this device.','/tools/application-tracker'],['07','Interview practice','Original text-only questions with self-review rubric.','/tools/interview-practice'],['08','Country checklist','Generic preparation tasks and official government links.','/tools/country-checklist'],['09','WorkProof portfolio','Export your honest self-reported project evidence.','/tools/workproof'],['10','Career roadmap','Create a deterministic 30/60/90-day practice plan.','/tools/career-roadmap']] as $f)echo '<a class="feature" href="'.$f[3].'"><span class="feature-icon">'.$f[0].'</span><h3>'.$f[1].'</h3><p>'.$f[2].'</p><span class="feature-more">Use free tool →</span></a>';
 echo '</div><p class="tiny">All ten tool interfaces are available in this LOCAL development release; original content assets, live-browser usability and editorial review still require independent verification before public launch.</p></div></section>';frameEnd();exit;
}
if($path==='/tools/salary-savings'){
 frameStart('Salary and savings calculator');heading('Free calculator','Salary vs living costs','See what you might save after your own estimated deductions and living costs.');
 echo '<section class="section section-tight"><div class="container tool-layout"><form id="salary-form" class="surface form-card" method="dialog" data-local-only="true"><h2>Enter your assumptions</h2><div class="form-grid"><label>Salary amount<input name="salary" type="number" min="0" step="any" required value="120000"></label><label>Salary period<select name="period"><option value="monthly">Monthly</option><option value="annual">Annual</option></select></label><label>Salary basis<select name="basis"><option value="net">Net / after tax</option><option value="gross" selected>Gross / before deductions</option></select></label><label>Currency code<input name="currency" maxlength="3" pattern="[A-Za-z]{3}" value="INR" required></label><label>Fixed monthly deductions<input name="deduction" type="number" min="0" step="any" value="0"></label><label>Additional deduction rate (%)<input name="rate" type="number" min="0" max="100" step="any" value="10"></label>';
 foreach(['rent'=>'Monthly rent','food'=>'Monthly food','transport'=>'Transport','utilities'=>'Utilities','insurance'=>'Insurance','other'=>'Other monthly expenses','oneTime'=>'One-time moving costs'] as $k=>$label)echo '<label>'.h($label).'<input name="'.h($k).'" type="number" min="0" step="any" value="0"></label>';
 echo '</div><button class="button button-primary" type="submit">Calculate savings</button></form><div class="surface result-card"><span class="eyebrow">Your results</span><div id="salary-result" hidden><h2 id="salary-total"></h2><p>Estimated monthly savings</p><dl class="facts"><div><dt>Estimated take-home</dt><dd id="salary-take-home"></dd></div><div><dt>Monthly expenses</dt><dd id="salary-expenses"></dd></div><div><dt>Savings share</dt><dd id="salary-rate"></dd></div><div><dt>First-year after moving costs</dt><dd id="salary-first-year"></dd></div></dl></div><p id="salary-feedback" role="status" aria-live="polite">Enter values and calculate. No inputs are sent to our server.</p><p class="tiny">Not legal, tax or financial advice. FX rates and tax estimates must come from you; sample inputs are illustrative.</p></div></div></section>';frameEnd();exit;
}
if($path==='/tools/offer-compare'){
 frameStart('Offer comparison');heading('Free calculator','Compare two job offers','Compare financial scenarios—without confusing guaranteed pay and conditional bonuses.');
 echo '<section class="section section-tight"><div class="container"><form id="compare-form" class="surface form-card" method="dialog" data-local-only="true"><div class="compare-grid">';
 foreach(['a'=>'Offer A','b'=>'Offer B'] as $p=>$label){
  echo '<fieldset><legend>'.h($label).'</legend><label>Base salary<input type="number" name="'.$p.'base" min="0" step="any" required value="0"></label><label>Pay period<select name="'.$p.'period"><option value="monthly">Monthly</option><option value="annual">Annual</option><option value="hourly">Hourly (hours/week below)</option></select></label><label>Currency<input name="'.$p.'currency" maxlength="3" value="INR" pattern="[A-Za-z]{3}" required></label><label>Hours per week (hourly only)<input name="'.$p.'hoursWeek" type="number" min="0" step="any" value="40"></label><label>Weeks per year<input name="'.$p.'weeksYear" type="number" min="0" max="53" value="52"></label><label>Guaranteed cash extra per month<input name="'.$p.'guaranteedCash" type="number" min="0" step="any" value="0"></label><label>Estimated monthly deductions<input name="'.$p.'deductions" type="number" min="0" step="any" value="0"></label><label>Estimated monthly living costs<input name="'.$p.'costs" type="number" min="0" step="any" value="0"></label><label>One-time moving costs<input name="'.$p.'move" type="number" min="0" step="any" value="0"></label><label>Conditional bonus (NOT guaranteed)<input name="'.$p.'conditionalBonus" type="number" min="0" step="any" value="0"></label>';
  if($p==='b')echo '<label>User-supplied FX: one B currency unit in A currency units<input name="bfxToA" type="number" min="0" step="any" placeholder="Only if currencies differ"></label>';
  echo '</fieldset>';
 }
 echo '</div><button class="button button-primary" type="submit">Compare scenarios</button></form><div class="surface comparison-output"><div id="compare-result" hidden><div class="compare-grid"><div><span class="eyebrow">Offer A first-year estimate</span><h2 id="compare-a"></h2></div><div><span class="eyebrow">Offer B first-year estimate</span><h2 id="compare-b"></h2></div></div><p id="compare-diff"></p></div><p id="compare-feedback" role="status" aria-live="polite">Enter both offers and calculate. No data leaves your browser.</p><p class="tiny">Higher savings are not proof of a better job or a genuine employer. Conditional bonuses are excluded from guaranteed savings.</p></div></div></section>';frameEnd();exit;
}
if($path==='/tools/time-zone-overlap'){
 frameStart('Time-zone overlap calculator');heading('Free scheduling tool','Find overlapping work hours','Uses real time-zone rules and handles daylight saving transitions.');
 echo '<section class="section section-tight"><div class="container tool-layout"><form id="tz-form" class="surface form-card" method="dialog" data-local-only="true"><h2>Meeting preferences</h2><div class="form-grid"><label>Date<input name="date" type="date" required value="'.gmdate('Y-m-d').'"></label><label>Meeting duration<select name="minutes"><option value="30">30 minutes</option><option value="60">60 minutes</option><option value="90">90 minutes</option></select></label><label>Time zone A<select name="zoneA"><option value="Asia/Kolkata">India — Asia/Kolkata</option><option value="Europe/London">UK — Europe/London</option><option value="America/New_York">US — New York</option><option value="Asia/Dubai">UAE — Dubai</option></select></label><label>Time zone B<select name="zoneB"><option value="Europe/London">UK — Europe/London</option><option value="America/New_York">US — New York</option><option value="Asia/Kolkata">India — Asia/Kolkata</option><option value="Asia/Dubai">UAE — Dubai</option></select></label><label>Zone A working start<input type="time" name="startA" value="09:00"></label><label>Zone A working end<input type="time" name="endA" value="18:00"></label><label>Zone B working start<input type="time" name="startB" value="09:00"></label><label>Zone B working end<input type="time" name="endB" value="18:00"></label></div><button class="button button-primary" type="submit">Find meeting times</button></form><div class="surface result-card"><div id="tz-result" hidden><h2>Suggested slots</h2><p id="tz-total"></p><div id="tz-slots" class="slot-list"></div></div><p id="tz-feedback" role="status" aria-live="polite">Choose working hours to see suggested meeting slots.</p><p class="tiny">Times use IANA time-zone rules from your browser. Actual business working days and holidays are not inferred.</p></div></div></section>';frameEnd();exit;
}
if($path==='/tools/resume-kit'){
 frameStart('Resume and cover letter kit',true);
 heading('Private career tool','Resume & cover letter kit','Create honest, readable career documents using your own experience. No paid AI, uploads, accounts or fabricated ATS score.');
 echo <<<'HTML'
<section class="section section-tight"><div class="container tool-layout">
<form id="resume-form" class="surface form-card" autocomplete="off" method="dialog" data-local-only="true"><h2>Build your resume</h2>
<label>Resume structure<select name="template"><option value="chronological">Reverse chronological — experience first</option><option value="skills">Skills based — transferable evidence first</option><option value="fresher">Fresher — education and projects first</option></select></label>
<div class="form-grid"><label>Full name *<input name="name" maxlength="120" required autocomplete="name" placeholder="Your actual name"></label><label>Target role *<input name="role" maxlength="140" required placeholder="e.g. IT Support Specialist"></label><label>Contact email (optional)<input type="email" name="email" maxlength="180"></label><label>Phone (optional)<input name="phone" maxlength="80"></label><label>Location (optional)<input name="location" maxlength="140"></label><label>Evidence or portfolio link (HTTPS)<input type="url" name="evidence" maxlength="1024" placeholder="https://example.org/my-work"></label></div>
<label>Professional summary (your own words)<textarea name="summary" rows="3" maxlength="1500" placeholder="Your actual experience or career goal"></textarea></label>
<label>Experience and achievements (if any)<textarea name="experience" rows="5" maxlength="5000" placeholder="Describe real work, dates, responsibilities and measurable outcomes"></textarea></label>
<label>Projects and evidence<textarea name="projects" rows="4" maxlength="3000" placeholder="Describe projects you actually completed"></textarea></label>
<label>Education<textarea name="education" rows="3" maxlength="2500"></textarea></label>
<label>Skills<textarea name="skills" rows="3" maxlength="1800" placeholder="Skills you can genuinely demonstrate"></textarea></label>
<h3>Optional cover letter</h3>
<div class="form-grid"><label>Organisation<input name="employer" maxlength="180" placeholder="Company you are applying to"></label><label>Recipient (if known)<input name="recipient" maxlength="180" placeholder="Hiring team"></label></div>
<label>Evidence paragraph 1<textarea name="achievement1" rows="3" maxlength="1400" placeholder="Describe a real achievement relevant to this role"></textarea></label>
<label>Evidence paragraph 2<textarea name="achievement2" rows="3" maxlength="1400" placeholder="Explain another relevant real example or project"></textarea></label>
<div class="small-controls"><button type="button" id="resume-preview-button" class="button button-primary">Preview resume</button><button type="button" id="resume-export-html" class="button button-secondary">Download HTML</button><button type="button" id="resume-export-text" class="button button-secondary">Download text</button><button type="button" id="resume-export-cover" class="button button-secondary">Cover letter text</button><button type="button" id="resume-print" class="button button-secondary">Print / Save PDF</button><button type="button" id="resume-reset" class="button button-text">Reset</button></div>
<p id="resume-feedback" role="status" aria-live="polite">Nothing is sent to our server or saved automatically. Review your details before sharing.</p></form>
<aside class="surface result-card"><h2>Your local document preview</h2><div class="resume-paper"><pre id="resume-preview" aria-live="polite">Fill out the form and select Preview resume.</pre></div><p class="tiny">Three layout options reorder your own details; no ATS compatibility percentage is claimed. Text/HTML downloads and browser print are local exports. Avoid including passport or sensitive ID numbers.</p></aside>
</div></section><script type="module" src="/assets/resume_ui.mjs"></script>
HTML;
 frameEnd();exit;
}
if($path==='/tools/relocation-budget'){
 frameStart('Relocation budget planner');
 heading('Free scenario calculator','Relocation budget planner','Build an adjustable pre-departure and emergency-fund plan using your own numbers. No live visa costs or exchange rates are assumed.');
 echo <<<'HTML'
<section class="section section-tight"><div class="container tool-layout">
<form id="relocation-form" class="surface form-card" method="dialog" data-local-only="true"><h2>Enter your cost estimates</h2>
<label>Currency code (one currency for all figures)<input name="currency" pattern="[A-Za-z]{3}" value="INR" maxlength="3" required></label>
<h3>One-time costs</h3><div class="form-grid">
<label>Flight / travel<input name="flight" type="number" min="0" step="any" value="0"></label><label>Visa/permit estimate (not official)<input name="visa" type="number" min="0" step="any" value="0"></label>
<label>Documents/translation<input name="documents" type="number" min="0" step="any" value="0"></label><label>Housing deposit<input name="deposit" type="number" min="0" step="any" value="0"></label>
<label>Setup/purchases<input name="setup" type="number" min="0" step="any" value="0"></label><label>Arrival transport<input name="arrival" type="number" min="0" step="any" value="0"></label></div>
<h3>Recurring monthly costs</h3><div class="form-grid"><label>Rent<input name="rent" type="number" min="0" step="any" value="0"></label><label>Food<input name="food" type="number" min="0" step="any" value="0"></label>
<label>Utilities<input name="utilities" type="number" min="0" step="any" value="0"></label><label>Transport<input name="transport" type="number" min="0" step="any" value="0"></label>
<label>Insurance<input name="insurance" type="number" min="0" step="any" value="0"></label><label>Other living costs<input name="other" type="number" min="0" step="any" value="0"></label></div>
<div class="form-grid"><label>Emergency reserve (months)<input name="reserveMonths" type="number" min="0" max="36" step="0.25" value="3"></label><label>Contingency (%)<input name="contingencyPct" type="number" min="0" max="100" step="any" value="10"></label></div>
<div class="small-controls"><button type="submit" class="button button-primary">Calculate target cash</button><button type="button" id="relocation-download" class="button button-secondary">Export JSON</button><button type="button" id="relocation-print" class="button button-secondary">Print result</button><button type="button" id="relocation-reset" class="button button-text">Reset</button></div>
</form><aside class="surface result-card"><div id="relocation-result" hidden><span class="eyebrow">Your budget target</span><h2 id="relocation-total"></h2><dl class="facts"><div><dt>One-time costs</dt><dd id="relocation-once"></dd></div><div><dt>Monthly expenses</dt><dd id="relocation-monthly"></dd></div><div><dt>Emergency reserve</dt><dd id="relocation-reserve"></dd></div><div><dt>Contingency</dt><dd id="relocation-contingency"></dd></div></dl></div>
<p id="relocation-feedback" role="status" aria-live="polite">All amounts are your own estimates. Nothing leaves the browser.</p><p class="tiny">One-time costs plus months of monthly reserves plus percentage contingency; no category is counted twice. Verify official fees directly with relevant authorities. Not immigration or financial advice.</p></aside>
</div></section><script type="module" src="/assets/relocation_ui.mjs"></script>
HTML;
 frameEnd();exit;
}
if($path==='/tools/application-tracker'){
 frameStart('Application tracker — on-device',true);
 heading('Privacy-first career tool','Application & follow-up tracker','Record applications and follow-up dates yourself. No account, automatic applications, CV uploads or backend notifications.');
 echo '<section class="section section-tight"><div class="container tool-layout"><form class="surface form-card" id="tracker-form" method="dialog" data-local-only="true"><h2>Your application</h2><input name="id" type="hidden"><div class="form-grid"><label>Company<input name="company" maxlength="120" required></label><label>Role<input name="role" maxlength="140" required></label><label>Source link (HTTPS)<input name="url" type="url" maxlength="2048" placeholder="https://example.com/job"></label><label>Work location (optional)<input name="work_location" maxlength="120"></label><label>Status<select name="status"><option value="saved">Saved</option><option value="applied">Applied (manual)</option><option value="interview">Interview</option><option value="offer">Offer</option><option value="rejected">Rejected</option><option value="closed">Closed</option></select></label><label>Date applied<input name="applied_at" type="date"></label><label>Next follow-up date<input name="followup_date" type="date"></label></div><label>Notes<textarea name="notes" rows="3" maxlength="800" placeholder="Questions and next steps (do not enter passwords/passport numbers)"></textarea></label><div class="small-controls"><button type="submit" id="tracker-submit" class="button button-primary">Save application</button><button type="button" id="tracker-reset" class="button button-secondary">Clear form</button></div><p id="tracker-status" role="status" aria-live="polite"></p></form><div class="surface result-card"><h2>Your saved applications</h2><div class="small-controls"><button type="button" class="button button-secondary" id="tracker-json">Export JSON</button><button type="button" class="button button-secondary" id="tracker-csv">Export CSV</button><label class="file-upload">Import JSON/CSV<input type="file" id="tracker-import" accept=".json,.csv,application/json,text/csv"></label><button type="button" class="button button-text" id="tracker-clear">Delete all applications</button></div><p class="tiny">Information is stored only in this browser. Clearing browser data or switching devices can delete it; export backups regularly. Calendar files are reminders you import yourself, not automatic email alerts. Imported files replace current application records after confirmation.</p><div id="tracker-results" aria-live="polite"></div></div></div></section>';
 echo '<script type="module" src="/assets/tracker_ui.mjs"></script>';
 frameEnd();exit;
}
if($path==='/tools/interview-practice'){
 frameStart('Text-only interview practice',true);
 heading('Original practice exercises','Interview & communication practice','Practice genuine role-specific questions with a transparent self-review rubric. No microphone, paid AI, hiring prediction or answer upload.');
 $bank=json_decode((string)file_get_contents($root.'/content/interview_questions.json'),true)?:[];
 echo '<section class="section section-tight"><div class="container tool-layout"><noscript><p class="notice error">Interactive practice requires browser JavaScript. No interview answer can be submitted to this site.</p></noscript><form id="interview-form" class="surface form-card" method="dialog" data-local-only="true"><h2>Choose a role</h2><label>Practice track<select name="role"><option value="it_support">IT Support</option><option value="customer_support">Customer Support</option><option value="administration">Administration</option></select></label><div class="small-controls"><button type="button" id="interview-next" class="button button-secondary">Next question</button><button type="button" id="interview-export" class="button button-secondary">Export your answer</button></div><div id="interview-question" class="practice-question"></div><label>Your practice answer (not stored)<textarea id="interview-answer" rows="7" maxlength="4000" placeholder="Write a truthful example or structured approach"></textarea></label><h3>Self-review prompts</h3><div id="interview-rubric" class="practice-checklist"></div><button class="button button-primary" id="interview-review" type="button">Review against prompts</button></form><div class="surface result-card"><h2>Practice timer</h2><p id="interview-timer" aria-live="off">Practice timer: 00:00</p><div class="small-controls"><button type="button" class="button button-secondary" id="interview-timer-start">Start</button><button type="button" class="button button-secondary" id="interview-timer-pause">Pause</button><button type="button" class="button button-secondary" id="interview-timer-reset">Reset</button></div><p id="interview-message" role="status" aria-live="polite"></p><p class="tiny">These are original learning prompts, not real employer interviews or accredited assessments. Your response is session-only unless you explicitly download it.</p></div></div></section>';
 echo '<script type="application/json" id="interview-data">'.json_encode($bank,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script type="module" src="/assets/advanced_tools_ui.mjs"></script>';
 frameEnd();exit;
}
if($path==='/tools/country-checklist'){
 frameStart('Country preparation checklist',true);
 heading('Official-reference preparation','Country readiness checklist','Keep a private personal planning list. Official links are starting points, not an automated visa or work-permit eligibility assessment.');
 $data=json_decode((string)file_get_contents($root.'/content/country_checklists.json'),true)?:[];
 echo '<section class="section section-tight"><div class="container tool-layout"><noscript><p class="notice error">Local checklist tick storage needs browser JavaScript; use the official links for reference.</p></noscript><form id="checklist-form" class="surface form-card" method="dialog" data-local-only="true"><h2>Choose destination</h2><label>Country<select name="country"><option value="UAE">United Arab Emirates</option><option value="Germany">Germany</option></select></label><p id="checklist-warning" class="notice" role="status"></p><div id="checklist-items" class="practice-checklist"></div><div class="small-controls"><button type="button" class="button button-secondary" id="checklist-export">Export progress JSON</button><label class="file-upload">Import progress JSON<input type="file" id="checklist-import" accept=".json,application/json"></label><button type="button" class="button button-text" id="checklist-delete">Delete local ticks</button></div><p id="checklist-message" role="status" aria-live="polite"></p></form><aside class="surface result-card"><h2>Official starting points</h2><ul id="checklist-official"></ul><p class="tiny">Only generic preparation tasks are tickable here. Documents required for a visa depend on the real permit category, nationality and current official rules. Never upload passport scans or other identity documents to this tool. Local progress can disappear if browser storage is cleared.</p></aside></div></section>';
 echo '<script type="application/json" id="checklist-data">'.json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script type="module" src="/assets/advanced_tools_ui.mjs"></script>';
 frameEnd();exit;
}
if($path==='/tools/workproof'){
 frameStart('Self-reported WorkProof portfolio',true);
 heading('Private document maker','WorkProof portfolio generator','Create a transparent, printable record of your own projects and experience. No hosting, external verification or uploads.');
 echo <<<'HTML'
<section class="section section-tight"><div class="container tool-layout"><noscript><p class="notice error">Portfolio generation requires JavaScript. No personal project data is transmitted.</p></noscript><form id="portfolio-form" class="surface form-card" autocomplete="off" method="dialog" data-local-only="true"><h2>Your self-reported portfolio</h2><div class="form-grid"><label>Your real name *<input name="name" required maxlength="120"></label><label>Target role *<input name="role" required maxlength="140"></label></div><label>Professional summary (optional)<textarea name="summary" rows="3" maxlength="1500"></textarea></label><div id="portfolio-projects"></div><div class="small-controls"><button type="button" id="portfolio-add" class="button button-secondary">Add project (max 10)</button><button type="button" id="portfolio-remove" class="button button-secondary">Remove last project</button><button type="submit" class="button button-primary">Preview locally</button><button type="button" id="portfolio-export" class="button button-secondary">Download HTML portfolio</button><button type="button" id="portfolio-reset" class="button button-text">Reset</button></div><p id="portfolio-message" role="status" aria-live="polite"></p></form><div class="surface result-card"><h2>Preview</h2><div id="portfolio-preview" class="resume-paper"></div><p class="tiny">No invented certifications, employer verification or hosted public profile. Downloaded HTML is printable to PDF using your own browser. Review all claims before sharing.</p></div></div></section>
HTML;
 echo '<script type="module" src="/assets/advanced_tools_ui.mjs"></script>';
 frameEnd();exit;
}
if($path==='/tools/career-roadmap'){
 frameStart('Career practice roadmap',true);
 heading('Deterministic skills plan','Career roadmap builder','Plan a realistic 30/60/90-day learning journey without paid AI or promises of certification or employment.');
 $data=json_decode((string)file_get_contents($root.'/content/roadmap_tracks.json'),true)?:[];
 echo '<section class="section section-tight"><div class="container tool-layout"><noscript><p class="notice error">Roadmap generation requires JavaScript; no form data is sent to the server.</p></noscript><form id="roadmap-form" class="surface form-card" method="dialog" data-local-only="true"><h2>Choose your learning path</h2><label>Career track<select name="track"><option value="it_support">IT Support</option><option value="customer_support">Customer Support</option><option value="junior_web_dev">Junior Web Developer</option></select></label><label>Available hours per week<input name="hours" type="number" min="1" max="40" step="1" required value="3"></label><div class="small-controls"><button type="submit" class="button button-primary">Build roadmap</button><button type="button" id="roadmap-export" class="button button-secondary">Export JSON</button><button type="button" id="roadmap-print" class="button button-secondary">Print plan</button><button type="button" id="roadmap-reset" class="button button-text">Reset</button></div><p id="roadmap-message" role="status" aria-live="polite">Use your available hours to generate three practice milestones. Nothing is sent to our server.</p></form><div class="surface result-card"><div id="roadmap-result" class="roadmap-preview"></div><p class="tiny">Stage budgets assume four weeks each; actual calendar months differ. Course availability and requirements may change. No hiring or accreditation guarantee.</p></div></div></section>';
 echo '<script type="application/json" id="roadmap-data">'.json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><script type="module" src="/assets/advanced_tools_ui.mjs"></script>';
 frameEnd();exit;
}

if($path==='/my-career'){
 frameStart('My career — on-device',true);heading('Private career workspace','Your Career Radar','Save role preferences and compare listing details between your visits on this device. No sign-up or background monitoring.');
 $payload=[];
 foreach(array_slice($jobs,0,80) as $j){
    // Fingerprint is our cache comparison, not a provider-published update timestamp.
    $fingerprint=substr(hash('sha256',json_encode([$j['title'],$j['company_name'],$j['description'],$j['source_updated_at'],$j['eligible_from_in'],$j['location_eligibility'],$j['time_zone_eligibility'],$j['salary_min'],$j['salary_max']],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)),0,24);
    $payload[]=['id'=>$j['id'],'title'=>$j['title'],'version'=>$fingerprint,'eligibility'=>Jobs::indiaFullyEligible($j)?'allowed':($j['eligible_from_in']==='not_allowed'?'not_allowed':'unknown'),'workMode'=>$j['work_mode'],'url'=>'/jobs/'.$j['provider'].'/'.rawurlencode($j['provider_job_id']).'/view'];
   }
 echo '<section class="section section-tight"><div class="container tool-layout"><form class="surface form-card" id="career-form" method="dialog" data-local-only="true"><h2>Saved preferences</h2><label>Target role<input name="role" maxlength="100" placeholder="e.g. Software Engineer"></label><label>Work path<select name="workPath"><option value="remote_from_india">India location + timezone confirmed</option><option value="remote_other">Remote: unknown/restricted</option><option value="onsite_abroad">On-site abroad (verified evidence)</option></select></label><button class="button button-primary" type="submit">Save & check matches</button><div class="small-controls"><button type="button" class="button button-secondary" id="profile-export">Export JSON</button><label class="file-upload">Import JSON<input type="file" id="profile-import" accept="application/json,.json"></label><button type="button" class="button button-text" id="profile-delete">Delete all local data</button></div><p id="career-message" role="status" aria-live="polite"></p></form><div class="surface result-card"><h2>Radar results</h2><div id="career-results" aria-live="polite"></div><p class="tiny">“New to your Radar” means not seen on this device; “details changed” is a local listing comparison, not a confirmed employer update. Clearing browser data can erase saved preferences. No background email alerts or automatic application-status tracking is active. <a href="/tools/application-tracker">Open manual application tracker</a>.</p></div></div></section><script type="application/json" id="career-data">'.json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
 frameEnd();exit;
}
if($path==='/daily'){
    $daily=json_decode((string)file_get_contents($root.'/content/daily_missions.json'),true);
    if(!is_array($daily)||!is_array($daily['missions']??null)){http_response_code(503);exit('Daily practice unavailable');}
    frameStart('Daily Career Mission',true);
    heading('Private daily practice','Daily Career Mission','A short original offline exercise for your chosen career track. Practice is self-reported and stays on this device.');
    echo '<section class="section section-tight"><div class="container tool-layout"><form id="daily-form" class="surface form-card" data-local-only="true" method="dialog"><h2>Today’s practice</h2><label>Career track<select name="role"><option value="it_support">IT Support</option><option value="customer_support">Customer Support</option><option value="web_development">Junior Web Development</option></select></label><p id="daily-date" class="tiny"></p><h3 id="daily-prompt">Select a role to read today’s question.</h3><ul id="daily-steps"></ul><p id="daily-deliverable"></p><button class="button button-primary" id="daily-complete" type="submit">Mark today completed</button><noscript><p class="notice error">Saving completion and streaks requires browser JavaScript; this example still works as a writing exercise:</p><p>Explain how you would safely troubleshoot a user&#39;s internet connection without collecting passwords. Write a short six-step plan on paper.</p></noscript></form><aside class="surface result-card"><span class="eyebrow">Your device only</span><h2 id="daily-streak">0 day streak</h2><p id="daily-feedback" role="status" aria-live="polite">Progress is not synced across devices. Export a backup if it matters to you.</p><div class="small-controls"><button id="daily-export" type="button" class="button button-secondary">Export progress</button><label class="file-upload">Import backup<input id="daily-import" type="file" accept="application/json,.json"></label><button id="daily-delete" type="button" class="button button-text">Delete progress</button></div><p class="tiny">Original question bank is editorial draft. No employer certification, hiring probability, reminders or paid AI.</p></aside></div></section>';
    echo '<script type="application/json" id="daily-data">'.json_encode($daily,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE).'</script><script type="module" src="/assets/daily_ui.mjs"></script>';
    frameEnd();exit;
}
if($path==='/api/privacy-event'){
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    if(getenv('ANALYTICS_ENABLED')!=='1'||!Launch::allowed($root)){http_response_code(404);echo '{"error":"disabled"}';exit;}
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/json')!==0){http_response_code(405);echo '{"error":"method"}';exit;}
    // Explicit same-origin requests only. The app does not collect or persist IPs or cookies.
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    $originHost=strtolower((string)(parse_url($origin,PHP_URL_HOST)??''));
    if(!$origin||!$originHost||$originHost!==preg_replace('/:\d+$/','',$host)||strtolower((string)parse_url($origin,PHP_URL_SCHEME))!=='https'){
        http_response_code(403);echo '{"error":"origin"}';exit;
    }
    $bytes=(int)($_SERVER['CONTENT_LENGTH']??0);
    if($bytes<1||$bytes>256){http_response_code(413);echo '{"error":"size"}';exit;}
    $raw=file_get_contents('php://input',false,null,0,257);
    $item=json_decode((string)$raw,true);
    if(!is_array($item)||array_is_list($item)||count($item)!==1||!Analytics::validEvent($item['event']??null)){
       http_response_code(422);echo '{"error":"event"}';exit;
    }
    try{Analytics::record($root.'/storage',$item['event']);http_response_code(204);}catch(Throwable){http_response_code(503);echo '{"error":"unavailable"}';}
    exit;
}
if($path==='/contact'){
    frameStart('Contact and support',true);heading('Support and safety','Contact OverseasJob.in','This development preview does not operate a live support desk or employment placement service.');
    echo '<section class="section"><div class="container reading"><p>For a suspicious imported job, use the <a href="/report-job">private report form</a> with the listing ID. Do not submit passports, CVs, passwords or payment details.</p><p>For immediate suspected overseas recruitment fraud, consult appropriate law enforcement and official government reporting resources. This site cannot respond to emergencies.</p><p>Support mailbox verification and a staffed contact process are required before public launch; no response-time guarantee is provided in this preview.</p></div></section>';frameEnd();exit;
}
if(RecruiterPortal::enabled() && ($path==='/employers'||str_starts_with($path,'/employers/'))){
  header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');
  if(!OwnerAuth::secureRequest($_SERVER)){http_response_code(503);exit('Employer portal requires HTTPS');}
  session_name('oj_recruiter');ini_set('session.use_strict_mode','1');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/employers','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
  session_start();
  if(!isset($_SESSION['recruiter_csrf']))$_SESSION['recruiter_csrf']=bin2hex(random_bytes(24));
  $svc=null;$member=null;$err='';$notice='';$now=time();
  try{$svc=RecruiterMysql::fromEnvironment();
      if(is_int($_SESSION['recruiter_member']??null) && ($_SESSION['recruiter_until']??0)>$now){
          $member=$svc->current($_SESSION['recruiter_member']);
          if(!RecruiterPortal::sessionIsCurrent($_SESSION,$member,$now)){
              $member=null;unset($_SESSION['recruiter_member'],$_SESSION['recruiter_until'],$_SESSION['recruiter_auth_version']);
          }
      }
  }catch(Throwable){http_response_code(503);exit('Employer portal temporarily unavailable');}
  if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
      if(!hash_equals((string)$_SESSION['recruiter_csrf'],(string)($_POST['csrf']??''))){$err='Invalid CSRF token';http_response_code(403);}
      else{
          try{
              if($path==='/employers/verify'){
                  $verified=$svc->confirm(strtolower(trim((string)($_POST['email']??''))),
                      (string)($_POST['code']??''),(string)($_POST['password']??''),$now);
                  $notice=$verified?'Email verified; sign in with your password.':'Verification failed or expired. Contact the pilot owner.';
              }elseif($path==='/employers/reset'){
                  if(($_POST['mode']??'')==='request'){
                      // Never disclose whether this address exists, is active or is rate-limited.
                      try{$svc->requestPasswordReset((string)($_POST['email']??''),
                          (string)($_SERVER['REMOTE_ADDR']??''),
                          static fn(string $email,string $code):bool=>RecruiterMailer::send($email,$code,'reset'),$now);
                      }catch(Throwable){}
                      $notice='If an eligible employer account exists, a reset code may have been sent. Check your mailbox.';
                  }elseif(($_POST['mode']??'')==='confirm'){
                      $reset=$svc->confirmPasswordReset((string)($_POST['email']??''),
                          (string)($_POST['code']??''),(string)($_POST['password']??''),$now);
                      if($reset){
                          $_SESSION=[];session_regenerate_id(true);
                          $_SESSION['recruiter_csrf']=bin2hex(random_bytes(24));
                      }
                      $notice=$reset?'Password updated. All earlier employer sessions are revoked. Please sign in.':
                          'Reset could not be completed. Request a fresh code if needed.';
                  }else{http_response_code(400);$err='Unsupported reset action';}
              }elseif($path==='/employers/login'){
                  $authenticated=$svc->authenticate((string)($_POST['email']??''),(string)($_POST['password']??''),
                    (string)($_SERVER['REMOTE_ADDR']??''),$now);
                  if(!$authenticated){$err='Sign-in failed. Check credentials or verification status.';http_response_code(401);}
                  else{session_regenerate_id(true);$_SESSION['recruiter_csrf']=bin2hex(random_bytes(24));
                      $_SESSION['recruiter_member']=$authenticated['id'];
                      $_SESSION['recruiter_auth_version']=$authenticated['auth_version'];
                      $_SESSION['recruiter_until']=$now+1200;
                      header('Location: /employers/dashboard',true,303);exit;}
              }elseif($path==='/employers/logout'){
                  $_SESSION=[];session_regenerate_id(true);$_SESSION['recruiter_csrf']=bin2hex(random_bytes(24));
                  header('Location: /employers/login',true,303);exit;
              }elseif(!$member){http_response_code(401);$err='Employer session expired. Sign in again.';}
              elseif($path==='/employers/jobs/new'){
                  if(!RecruiterPortal::mayManage($member))throw new DomainException('Cannot manage jobs');
                  $eligible=array_values(array_filter(array_map('trim',explode(',',strtoupper((string)($_POST['eligible_countries']??''))))));
                  $fields=['external_id'=>(string)($_POST['external_id']??''),'title'=>(string)($_POST['title']??''),
                      'description'=>(string)($_POST['description']??''),'employment_type'=>(string)($_POST['employment_type']??''),
                      'work_mode'=>(string)($_POST['work_mode']??''),'work_country'=>trim((string)($_POST['work_country']??''))?:null,
                      'location_text'=>(string)($_POST['location_text']??''),'candidate_eligible_countries'=>$eligible,
                      'apply_url'=>(string)($_POST['apply_url']??''),'valid_through'=>(string)($_POST['valid_through']??'').'T23:59:59Z',
                      'visa_sponsorship'=>(string)($_POST['visa_sponsorship']??'unknown'),
                      'consent_to_publish'=>($_POST['consent_to_publish']??'')==='1'];
                  $svc->create($member,$fields,$now,(string)getenv('PARTNER_TERMS_VERSION'));
                  header('Location: /employers/dashboard',true,303);exit;
              }elseif(preg_match('#^/employers/jobs/(job_[a-f0-9]{24})/edit$#',$path,$r)){
                  if(!RecruiterPortal::mayManage($member))throw new DomainException('Cannot manage jobs');
                  if(!ctype_digit((string)($_POST['revision']??'')))throw new InvalidArgumentException('Missing revision');
                  $patch=['title'=>(string)($_POST['title']??''),'description'=>(string)($_POST['description']??''),
                      'location_text'=>(string)($_POST['location_text']??''),'apply_url'=>(string)($_POST['apply_url']??'')];
                  $svc->edit($member,$r[1],$patch,(int)$_POST['revision'],$now,(string)getenv('PARTNER_TERMS_VERSION'))
                      ?:throw new DomainException('Job unavailable');
                  header('Location: /employers/dashboard',true,303);exit;
              }elseif(preg_match('#^/employers/jobs/(job_[a-f0-9]{24})/close$#',$path,$r)){
                  if(!RecruiterPortal::mayManage($member))throw new DomainException('Cannot manage jobs');
                  $svc->close($member,$r[1],$now)?:throw new DomainException('Job unavailable');
                  header('Location: /employers/dashboard',true,303);exit;
              }else{$err='Unsupported employer form';http_response_code(405);}
          }catch(Throwable){$err='Action rejected by employer security/validation rules. Review data and retry.';http_response_code(422);}
      }
  }
  frameStart('Employer pilot',true);
  if($path==='/employers'){
      heading('Invite-only employer pilot','Employer submissions','Only independently approved organisations with verified member emails may create pending-review jobs.');
      echo '<section class="section"><div class="container reading"><p>No guarantee of publication, applicants, visas or approval. <a href="/employers/login">Employer member sign-in</a> · <a href="/employers/verify">Confirm email invitation</a> · <a href="/employers/reset">Reset password</a>.</p></div></section>';
  }elseif($path==='/employers/reset'){
      heading('Employer account recovery','Reset your password','For independently approved employer accounts only. This is not a candidate login.');
      echo '<section class="section"><div class="container reading surface">';
      if($err)echo '<p role="alert" class="notice error">'.h($err).'</p>';
      if($notice)echo '<p role="status">'.h($notice).'</p>';
      echo '<h2>Request a private reset code</h2><form method="post" action="/employers/reset">';
      echo '<input type="hidden" name="csrf" value="'.h($_SESSION['recruiter_csrf']).'">';
      echo '<input type="hidden" name="mode" value="request">';
      echo '<label>Invited employer email<input type="email" name="email" maxlength="254" required autocomplete="email"></label>';
      echo '<button class="button button-secondary" type="submit">Request reset code</button></form>';
      echo '<h2>Confirm code and choose new password</h2><form method="post" action="/employers/reset">';
      echo '<input type="hidden" name="csrf" value="'.h($_SESSION['recruiter_csrf']).'">';
      echo '<input type="hidden" name="mode" value="confirm">';
      echo '<label>Employer email<input type="email" name="email" maxlength="254" required autocomplete="username"></label>';
      echo '<label>8-digit code<input name="code" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required></label>';
      echo '<label>New strong password<input name="password" type="password" minlength="12" maxlength="256" required autocomplete="new-password"></label>';
      echo '<button class="button button-primary" type="submit">Reset password</button></form>';
      echo '<p class="tiny">Codes expire in 15 minutes. Multiple failed attempts lock the code. Password reset invalidates earlier employer sessions. Mailbox delivery must be checked in staging.</p>';
      echo '</div></section>';
  }elseif($path==='/employers/login'||$path==='/employers/verify'){
      heading('Secure employer sign-in',$path==='/employers/login'?'Member login':'Confirm invited email','Employer access only after genuine email and organisation verification.');
      echo '<section class="section"><div class="container reading surface">';
      if($err)echo '<p role="alert" class="notice error">'.h($err).'</p>';
      if($notice)echo '<p role="status">'.h($notice).'</p>';
      echo '<form method="post" action="'.h($path).'"><input type="hidden" name="csrf" value="'.h($_SESSION['recruiter_csrf']).'">';
      echo '<label>Invited business email<input type="email" name="email" maxlength="254" required autocomplete="username"></label>';
      if($path==='/employers/verify')echo '<label>8-digit email code<input name="code" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required></label>';
      echo '<label>Strong password<input type="password" name="password" maxlength="256" minlength="12" required autocomplete="'.($path==='/employers/login'?'current-password':'new-password').'"></label>';
      echo '<button class="button button-primary" type="submit">'.($path==='/employers/login'?'Sign in':'Confirm email and set password').'</button></form>';
      if($path==='/employers/login')echo '<p><a href="/employers/reset">Forgot employer password?</a></p>';
      echo '</div></section>';
  }elseif($path==='/employers/dashboard'||$path==='/employers/jobs/new'||preg_match('#^/employers/jobs/(job_[a-f0-9]{24})/edit$#',$path)){
      if(!$member){http_response_code(401);heading('Sign-in required','No active verified member session');echo '<section class="section"><div class="container"><a href="/employers/login">Sign in</a></div></section>';}
      else{
          $_SESSION['recruiter_until']=$now+1200;
          if($path==='/employers/dashboard'){
              heading('Employer pilot dashboard',($member['org']['company_name']??'Employer').' jobs','Only your company’s first-party jobs. All new or changed jobs require independent review.');
              echo '<section class="section"><div class="container reading surface">';
              if(RecruiterPortal::mayManage($member))echo '<a class="button button-primary" href="/employers/jobs/new">Submit new listing</a>';
              echo '<h2>Organisation listings</h2>';
              try{$ownJobs=$svc->jobs($member,60);
                  if(!$ownJobs)echo '<p>No submitted jobs yet.</p>';
                  foreach($ownJobs as $j){echo '<article class="owner-report"><strong>'.h($j['title']).'</strong> · '.h($j['status']).' · expires '.h($j['valid_through']).'<p>'.h($j['location_text']).'</p>';
                      if(RecruiterPortal::mayManage($member)&&in_array($j['status'],['pending_review','published','rejected','draft'],true))echo '<a href="/employers/jobs/'.h($j['id']).'/edit">Edit (returns to review)</a>';
                      if(RecruiterPortal::mayManage($member)&&$j['status']!=='closed')echo '<form method="post" action="/employers/jobs/'.h($j['id']).'/close"><input type="hidden" name="csrf" value="'.h($_SESSION['recruiter_csrf']).'"><button class="button button-secondary" type="submit">Close listing</button></form>';
                      echo '</article>';
                  }
              }catch(Throwable){echo '<p class="notice error">Employer listing store unavailable; no data released.</p>';}
              echo '<form method="post" action="/employers/logout"><input type="hidden" name="csrf" value="'.h($_SESSION['recruiter_csrf']).'"><button class="button button-text" type="submit">Sign out</button></form></div></section>';
          }elseif($path==='/employers/jobs/new'&&RecruiterPortal::mayManage($member)){
              heading('Post to review','New employer job','Submission always begins in pending review, not public search.');
              echo '<section class="section"><div class="container reading surface">';
              if($err)echo '<p role="alert">'.h($err).'</p>';
              echo '<form method="post" action="/employers/jobs/new"><input type="hidden" name="csrf" value="'.h($_SESSION['recruiter_csrf']).'">';
              foreach(['external_id'=>'Your stable internal job ID','title'=>'Job title','location_text'=>'Location / work arrangement','apply_url'=>'HTTPS employer application URL'] as $key=>$label)
                 echo '<label>'.h($label).'<input name="'.h($key).'" maxlength="250" required></label>';
              echo '<label>Role description<textarea name="description" minlength="30" maxlength="16000" rows="6" required></textarea></label>';
              echo '<label>Employment type<select name="employment_type"><option value="full_time">Full time</option><option value="part_time">Part time</option><option value="contract">Contract</option><option value="internship">Internship</option></select></label>';
              echo '<label>Work arrangement<select name="work_mode"><option value="remote">Remote</option><option value="hybrid">Hybrid</option><option value="onsite">On site</option></select></label>';
              echo '<label>Physical work country (ISO2; required for hybrid/on-site)<input name="work_country" maxlength="2" placeholder="e.g. DE"></label>';
              echo '<label>Eligible applicant countries, comma separated ISO2 (leave blank if unknown)<input name="eligible_countries" maxlength="400" placeholder="IN, AE"></label>';
              echo '<label>Visa sponsorship<select name="visa_sponsorship"><option value="unknown">Unknown</option><option value="yes">Yes, employer attests</option><option value="no">No</option></select></label>';
              echo '<label>Job valid through (up to 60 days)<input name="valid_through" type="date" required></label>';
              echo '<label><input type="checkbox" name="consent_to_publish" value="1" required> I attest this job is real and authorised for publication; I accept the versioned terms.</label>';
              echo '<button class="button button-primary" type="submit">Submit for human review</button></form></div></section>';
          }elseif(preg_match('#^/employers/jobs/(job_[a-f0-9]{24})/edit$#',$path,$m)&&RecruiterPortal::mayManage($member)){
              $target=null;foreach($svc->jobs($member,100) as $j)if($j['id']===$m[1]){$target=$j;break;}
              if(!$target||!in_array($target['status'],['draft','pending_review','published','rejected'],true)){
                  http_response_code(404);heading('Not available','Job cannot be edited');
              }else{
                  heading('Edit employer listing',$target['title'],'Changed details return to pending review.');
                  echo '<section class="section"><div class="container reading surface">';
                  if($err)echo '<p role="alert">'.h($err).'</p>';
                  echo '<form method="post" action="'.h($path).'"><input type="hidden" name="csrf" value="'.h($_SESSION['recruiter_csrf']).'">';
                  echo '<input type="hidden" name="revision" value="'.h($target['revision']).'">';
                  echo '<label>Title<input name="title" maxlength="250" value="'.h($target['title']).'" required></label>';
                  echo '<label>Description<textarea name="description" maxlength="16000" required>'.h($target['description']).'</textarea></label>';
                  echo '<label>Location<input name="location_text" maxlength="255" value="'.h($target['location_text']).'" required></label>';
                  echo '<label>Verified HTTPS apply URL<input name="apply_url" value="'.h($target['apply_url']).'" required></label>';
                  echo '<button class="button button-primary" type="submit">Save and resubmit for review</button></form></div></section>';
              }
          }else{http_response_code(403);heading('Not authorised','Employer account lacks this permission');}
      }
  }else{http_response_code(404);heading('Page unavailable','Employer pilot route unavailable');}
  frameEnd();exit;
}
if($path==='/employers'||str_starts_with($path,'/employers/')){
    http_response_code(503);frameStart('Employer features unavailable',true);heading('Invite-only future feature','Employer posting is not open','Employer accounts, identity checks and API posting remain disabled until verified hosting, security, email confirmation and owner review are operating.');
    echo '<section class="section"><div class="container reading"><p>This site does not currently accept paid placements, recruitment agency registrations or direct job submissions. <a href="/jobs">Browse approved-source listings</a>.</p></div></section>';frameEnd();exit;
}
if($path==='/developers'){
    frameStart('Developer API (gated)',true);heading('Developer programme','Inbound employer API is not available','Partner job posting is a future invite-only integration and cannot be used without verified employer accounts and a technical security review.');
    echo '<section class="section"><div class="container reading"><p>There is no anonymous job feed API, and restricted third-party provider listings are not offered for redistribution.</p></div></section>';frameEnd();exit;
}
if($path==='/sources'){
 frameStart('Sources and attribution');heading('Transparency','Where listings come from','Every imported listing retains its original provider attribution and approved Apply link.');
 echo '<section class="section"><div class="container reading"><h2>Jobicy</h2><p>Public remote-job feed. We show the canonical Jobicy URL and never imply affiliate earnings from job clicks. <a href="https://jobicy.com/jobs-rss-feed" rel="noopener noreferrer" target="_blank">Official API rules</a>.</p><h2>Himalayas</h2><p>Optional source. The feed must use the unmodified applicationLink, visible Himalayas credit, and must not be redistributed to other boards or Google Jobs. <a href="https://himalayas.app/docs/remote-jobs-api" rel="noopener noreferrer" target="_blank">Official API reference</a>.</p><p>Provider permissions can change. A job is visible here only if a permitted and recently validated source refresh succeeds.</p></div></section>';frameEnd();exit;
}
if($path==='/safety'){
 frameStart('Stay safe');heading('Job seeker safety','Avoid common overseas job scams','Never pay a recruiter or send identity documents solely because a listing appears on any portal.');
 echo '<section class="section"><div class="container reading"><h2>Before you apply</h2><ol><li>Verify the hiring company and its official domain independently.</li><li>Do not assume “remote” means India eligible or “relocation” means sponsorship.</li><li>Question requests for deposits, urgent transfers or original passports.</li><li>Read the actual employer application link carefully.</li><li>Check immigration and employment requirements with official authorities, not random job advertisements.</li></ol><p>Official starting points: <a href="https://www.mea.gov.in/oe-faq.htm" target="_blank" rel="noopener noreferrer">India MEA overseas employment FAQ</a> and <a href="https://emigrate.gov.in/" target="_blank" rel="noopener noreferrer">eMigrate</a>.</p><p>We do not verify every employer and cannot promise a job or visa.</p></div></section>';frameEnd();exit;
}
// Guides are original editorial drafts until individually reviewed, never automatically published as official immigration advice.
$guideMap=Guides::all($root.'/content/guides.json');
if($path==='/guides'){
 frameStart('Practical career guides',true);heading('Career decision guides','Make better choices before applying','Original decision-making explainers. These are editorial drafts, not legally reviewed country requirements.');
 echo '<section class="section"><div class="container guide-grid">';
 foreach($guideMap as $g)echo '<article class="feature"><span class="eyebrow">'.($g['editorial_status']==='reviewed'?'Reviewed guide':'Editorial draft · review pending').'</span><h2>'.h($g['title']).'</h2><p>'.h($g['summary']).'</p><a class="inline-link" href="/guides/'.h($g['slug']).'">Read guide →</a></article>';
 echo '</div><div class="container"><p class="tiny">Source reference research date: 22 November 2026. Do not treat these pages as current visa, tax or recruitment advice. Content requires independent review before public indexing.</p></div></section>';frameEnd();exit;
}
if(preg_match('#^/guides/([a-z0-9-]+)$#',$path,$gm)){
 $g=$guideMap[$gm[1]]??null;
 if(!$g){http_response_code(404);frameStart('Guide not found',true);heading('404','Guide unavailable');frameEnd();exit;}
 $draft=($g['editorial_status']!=='reviewed');
 frameStart($g['title'],$draft);heading('Career decision guide',$g['title'],$g['summary']);
 echo '<section class="section section-tight"><div class="container reading surface"><p class="tiny">'.($draft?'EDITORIAL DRAFT — human review still required':'Reviewed on '.h($g['reviewed_at'])).' · Sources researched: '.h($g['research_date']).'. No employment, visa or salary outcomes are guaranteed.</p>';
 foreach($g['sections'] as $section){echo '<h2>'.h($section['heading']).'</h2>';foreach($section['paragraphs'] as $paragraph)echo '<p>'.h($paragraph).'</p>';echo '<ul>';foreach($section['checklist'] as $item)echo '<li>'.h($item).'</li>';echo '</ul>';}
 echo '<h2>Official and reference starting points</h2><ul>';
 foreach($g['sources'] as $reference)echo '<li><a target="_blank" rel="noopener noreferrer nofollow" href="'.h($reference['url']).'">'.h($reference['title']).'</a></li>';
 echo '</ul><p>These links are starting points, not endorsements or a guarantee that requirements have stayed unchanged. Recheck current official information for your own circumstances.</p><a class="button button-secondary" href="'.h($g['related_tool']).'">Open related career tool →</a></div></section>';
 frameEnd();exit;
}
if($path==='/report-job'){
 if(session_status()!==PHP_SESSION_ACTIVE){ini_set('session.use_strict_mode','1');session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')]);session_start();}
 if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(20));
 $saved=false;$error='';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $csrf=(string)($_POST['csrf']??'');$started=(int)($_SESSION['report_count']??0);
  if(!hash_equals($_SESSION['csrf'],$csrf)){$error='Security token invalid. Reload the page.';}
  elseif($started>=5){$error='Report limit reached for this session.';}
  elseif(trim((string)($_POST['website']??''))!==''){$error='Report rejected.';}
  else{
   $id=trim(substr((string)($_POST['job_id']??''),0,170));
   $reason=(string)($_POST['reason']??'');$details=Jobs::text($_POST['details']??'',600);
   try{Reports::submit($root.'/storage',$id,$reason,$details,(string)($_SERVER['REMOTE_ADDR']??''),(string)getenv('REPORT_RATE_SECRET'));$saved=true;$_SESSION['report_count']=$started+1;}
   catch(Throwable){$error='Unable to accept report right now. Please try later.';http_response_code(503);}
  }
 }
 frameStart('Report a job',true);heading('Trust and safety','Report an issue','Flag a suspicious or outdated listing. Reports are held for manual review and do not automatically verify wrongdoing.');
 echo '<section class="section section-tight"><div class="container reading surface">';
 if($saved)echo '<p role="status" class="notice success">Your report was stored for review. This is not an emergency reporting channel.</p>';
 elseif($error)echo '<p role="alert" class="notice error">'.h($error).'</p>';
 echo '<form method="post" class="report-form"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><label>Job ID<input name="job_id" maxlength="160" required value="'.h($_GET['id']??'').'"></label><label>Reason<select name="reason"><option value="scam">Possible scam or fee demand</option><option value="wrong_eligibility">Eligibility/location incorrect</option><option value="broken_apply">Apply link broken</option><option value="expired">Expired or removed</option><option value="other">Other issue</option></select></label><label>Brief details (optional, no personal documents)<textarea name="details" maxlength="600" rows="4"></textarea></label><label class="hidden-honeypot">Leave blank<input name="website" autocomplete="off"></label><button type="submit" class="button button-primary">Submit report</button></form><p class="tiny">No email or CV is required. A technical administrator must review and act on reports before production launch.</p></div></section>';frameEnd();exit;
}
if($path==='/privacy'){
 frameStart('Privacy and device data',true);heading('Privacy and consent','Your data stays under your control','Ten career tools work without a candidate login. Optional anonymous usage counts are off unless you choose to allow them.');
 echo '<section class="section section-tight"><div class="container reading surface"><h2>What is stored?</h2><p>Career preferences, applications, country checklist progress, practice completions, portfolio drafts and roadmap edits may be stored in your own browser. Use export controls in the individual tools for backups. Clearing browser site data deletes locally saved progress.</p><p>Job reports are separately retained in a private moderation queue without requiring an email. Do not submit personal documents or sensitive details. The public server may still process standard operational access logs; hosting-specific retention policies require confirmation before release.</p><div id="privacy-controls"><h2>Optional anonymous site counts</h2><label class="checklist-item"><input type="checkbox" id="privacy-count-optin"> Allow anonymous aggregate page/tool usage counts</label><p class="tiny">No third-party analytics, advertising cookies, cross-site identifiers, profile matching or individual visitor tracking. Counts are only submitted if production analytics are separately enabled after launch review. Browser GPC/Do Not Track disables this option.</p><p id="privacy-feedback" role="status" aria-live="polite"></p><button id="privacy-delete-all" type="button" class="button button-secondary">Delete all OverseasJob local data</button></div><p class="tiny">Deleting browser data does not retract earlier aggregate counts, files you downloaded, server audit records or submitted job reports. An approved production privacy notice and report-deletion procedure are still pending.</p></div></section>';frameEnd();exit;
}
if($path==='/about'||$path==='/terms'){
 $data=[
  '/about'=>['About OverseasJob','An independent information and career utility project, not a licensed recruitment or visa placement agency. This is an unfinished local release candidate. Our goal is to help people evaluate global careers with honest source labels, financial tools and safety information.'],
  '/privacy'=>['Privacy and local storage','Our calculators run in your browser. My Career preferences and saved job IDs are stored locally in your device browser and can be exported or deleted. This local release candidate has no advertising analytics enabled. A report form stores job IDs and issue descriptions server-side for review; avoid submitting personal information. Production privacy/cookie policy, retention and lawful-basis review are pending.'],
  '/terms'=>['Use and limitations','This local release candidate is not an operating employment agency or guarantee of current job availability. External links and third-party data can change. Financial calculations are hypothetical estimates from user-entered numbers; country requirements must be verified with official sources. No paid recruitment services or payments are offered here.']
 ];
 frameStart($data[$path][0]);heading('Platform information',$data[$path][0],$data[$path][1]);echo '<section class="section"><div class="container reading"><p><a href="/safety">Review our safety advice</a> and <a href="/sources">source attribution rules</a>.</p></div></section>';frameEnd();exit;
}
if($path==='/robots.txt'){header('Content-Type: text/plain');if(!Launch::allowed(OJ_PROJECT_ROOT)){echo "User-agent: *\nDisallow: /\n";}else{echo "User-agent: *\nDisallow: /my-career\nDisallow: /report-job\nDisallow: /daily\nDisallow: /employers\nDisallow: /jobs/\n";}exit;}
if($path==='/sitemap.xml'){if(!Launch::allowed(OJ_PROJECT_ROOT)){http_response_code(404);exit('Sitemap unavailable until release readiness');}header('Content-Type: application/xml');echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach(['/','/jobs','/tools','/tools/salary-savings','/tools/offer-compare','/tools/time-zone-overlap','/tools/resume-kit','/tools/relocation-budget','/tools/application-tracker','/tools/interview-practice','/tools/country-checklist','/tools/workproof','/tools/career-roadmap','/daily','/guides','/safety','/sources','/about'] as $p)echo '<url><loc>https://overseasjob.in'.h($p).'</loc></url>';echo '</urlset>';exit;}
http_response_code(404);frameStart('Page not found',true);heading('404','Page not found','This page does not exist or the feature is not available yet.');echo '<section class="section"><div class="container"><a class="button button-primary" href="/">Return home</a></div></section>';frameEnd();
