<?php
/** The exact ZIP's provider cases are supplied via private stdin; no network access. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__).'/src/Jobs.php';
try {
    $cases=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($cases)||count($cases)!==18||!array_is_list($cases))
        throw new InvalidArgumentException('Expected eighteen canonical provider fixtures');
    foreach($cases as $index=>$case){
        if(!is_array($case)||($case['source']??null)!=='Himalayas'
            ||!in_array($case['expected_india']??null,['allowed','unknown','not_allowed'],true)
            ||!array_key_exists('country_restrictions',$case)
            ||!array_key_exists('timezone_restrictions',$case))
            throw new InvalidArgumentException('Unexpected canonical fixture shape at '.$index);
        $result=Jobs::himalayasIndia([
            'locationRestrictions'=>$case['country_restrictions'],
            'timezoneRestrictions'=>$case['timezone_restrictions']
        ],true);
        if(($result['eligibility']??null)!==$case['expected_india'])
            throw new RuntimeException('Source-to-code Himalayas eligibility mismatch at case '.$index);
    }
    echo "PASS canonical ZIP 18 strict Himalayas India eligibility input/output cases\n";
} catch(Throwable $e){
    fwrite(STDERR,'FAIL canonical provider golden bridge: '.$e->getMessage()."\n");
    exit(1);
}
