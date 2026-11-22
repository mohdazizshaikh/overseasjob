<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/OwnerAuth.php';
$n=0;function checkO(string $label,bool $ok):void{global $n;if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n";$n++;}
$dir=sys_get_temp_dir().'/oj-owner-'.bin2hex(random_bytes(6));mkdir($dir,0700);
putenv('OWNER_PANEL_ENABLED=1');putenv('OWNER_RATE_SECRET='.str_repeat('a',40));
putenv('OWNER_PASSWORD_HASH='.password_hash('complex test secret',PASSWORD_DEFAULT));
checkO('Owner panel only with explicit configuration',OwnerAuth::configured());
checkO('Owner password hash verifies correct secret',OwnerAuth::verifyPassword('complex test secret'));
checkO('Owner password mismatch denied',!OwnerAuth::verifyPassword('bad'));
checkO('Owner rejects overlong submitted secret',!OwnerAuth::verifyPassword(str_repeat('X',1025)));
checkO('Owner requests HTTPS',OwnerAuth::secureRequest(['HTTPS'=>'on']));
checkO('Owner denies unencrypted HTTP',!OwnerAuth::secureRequest(['HTTPS'=>'off']));
checkO('Owner refuses spoofed forwarded-proto without TLS',!OwnerAuth::secureRequest(['HTTP_X_FORWARDED_PROTO'=>'https']));
checkO('Owner anonymous session not privileged',!OwnerAuth::active([],1000));
checkO('Owner session with valid expiry privileged',OwnerAuth::active(['owner_logged_in'=>true,'owner_until'=>1002],1000));
checkO('Owner expired session denied',!OwnerAuth::active(['owner_logged_in'=>true,'owner_until'=>999],1000));
checkO('Owner failure hash has no raw IP',!str_contains(OwnerAuth::fingerprint('127.0.0.1'),'127.0.0.1'));
checkO('Owner initial auth attempt allowed',OwnerAuth::mayAttempt($dir,'127.0.0.1',1000));
for($i=0;$i<5;$i++)OwnerAuth::failedAttempt($dir,'127.0.0.1',1000);
checkO('Owner 5 failed attempts lock requester',!OwnerAuth::mayAttempt($dir,'127.0.0.1',1010));
checkO('Owner different address independent',OwnerAuth::mayAttempt($dir,'127.0.0.2',1010));
checkO('Owner blocked IP recovered after window',OwnerAuth::mayAttempt($dir,'127.0.0.1',2000));
checkO('Owner rate file chmod private',(fileperms($dir.'/owner_rate.json')&0777)===0600);
checkO('Owner rate file lacks raw client IP',!str_contains((string)file_get_contents($dir.'/owner_rate.json'),'127.0.0.1'));
putenv('OWNER_PANEL_ENABLED=0');checkO('Owner global feature switch disables panel',!OwnerAuth::configured());
putenv('OWNER_PANEL_ENABLED=1');putenv('OWNER_RATE_SECRET=weak');checkO('Owner missing adequate rate secret fails closed',!OwnerAuth::configured());
foreach(glob($dir.'/*')?:[] as $f)unlink($f);rmdir($dir);
echo "\n$n M10 owner security checks passed.\n";
