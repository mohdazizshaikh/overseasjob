<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/Analytics.php';
try{echo json_encode(['at'=>gmdate('c'),'analytics_enabled'=>(getenv('ANALYTICS_ENABLED')==='1'),
 'anonymous_daily_counts'=>Analytics::read(dirname(__DIR__).'/storage'),
 'note'=>'Opt-in counts only, no personal analytics, bot-proof revenue or unique-user claims.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";}
catch(Throwable $e){fwrite(STDERR,"Analytics report unavailable\n");exit(1);}
