<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$fixture=sys_get_temp_dir().'/oj-host-layout-'.bin2hex(random_bytes(5));
mkdir($fixture,0700);mkdir($fixture.'/public_html',0700);
$checks=0;
try{
 if(!symlink($root,$fixture.'/overseasjob_private'))throw new RuntimeException('Unable to simulate separate private directory');
 if(!copy($root.'/deploy/shared_host/index.php.template',$fixture.'/public_html/index.php'))throw new RuntimeException('Cannot create host wrapper');
 $file=$fixture.'/public_html/index.php';
 $code='$_SERVER["REQUEST_URI"]="/tools/relocation-budget";require '.var_export($file,true).';';
 exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>&1',$out,$exit);
 $html=implode("\n",$out);
 if($exit!==0||!str_contains($html,'id="relocation-form"'))throw new RuntimeException('Host wrapper failed to resolve private PHP controller');
 echo "PASS private sibling bootstraps T04 route\n";$checks++;
 if(!str_contains($html,'noindex,nofollow'))throw new RuntimeException('Public release unexpectedly indexed');
 echo "PASS unverified release wrapper still noindex\n";$checks++;
 if(is_dir($fixture.'/public_html/src')||is_file($fixture.'/public_html/storage/jobs.json'))throw new RuntimeException('Private source leaked into public_html');
 echo "PASS no private source/data files copied into webroot\n";$checks++;
}finally{
 @unlink($fixture.'/public_html/index.php');@rmdir($fixture.'/public_html');@unlink($fixture.'/overseasjob_private');@rmdir($fixture);
}
echo "\n$checks shared-host layout checks passed (simulated local filesystem only).\n";
