<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/EmployerReview.php';
try{
    if(getenv('EMPLOYER_REVIEW_ENABLED')!=='1'||getenv('RECRUITER_LITE_ENABLED')!=='1')throw new RuntimeException('Employer review disabled');
    $action=$argv[1]??'';
    $store=EmployerReviewMysql::fromEnvironment();
    if($action==='pending'){
        foreach($store->pending(50) as $item){
            echo json_encode(['job_id'=>$item['job']['id'],'title'=>$item['job']['title'],
              'org_id'=>$item['org']['id'],'company'=>$item['org']['company_name'],
              'review_evidence_present'=>EmployerReview::validOrg($item['org']),
              'revision'=>$item['job']['revision']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        }
    }elseif(in_array($action,['publish','reject','suspend'],true)){
        if(count($argv)!==6||!ctype_digit($argv[3]))throw new InvalidArgumentException('Usage: publish|reject|suspend job_ID REVISION ACTOR REASON (reason >=12 characters)');
        $result=$store->review($argv[2],(int)$argv[3],$action,$argv[5],$argv[4],time());
        echo json_encode(['job_id'=>$result['id'],'status'=>$result['status'],'revision'=>$result['revision']],JSON_THROW_ON_ERROR)."\n";
    }elseif($action==='expire'){
        if(count($argv)!==2)throw new InvalidArgumentException('Usage: expire');
        echo 'Expired '.$store->expire(time())." employer records\n";
    }else throw new InvalidArgumentException('Usage: pending | publish/reject/suspend job_ID revision actor reason | expire');
}catch(Throwable $error){fwrite(STDERR,'Employer review refused: '.$error->getMessage()."\n");exit(2);}
