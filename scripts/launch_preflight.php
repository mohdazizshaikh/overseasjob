<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require dirname(__DIR__).'/src/Launch.php';
$state=Launch::status(dirname(__DIR__));
echo json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($state['ready']?0:2);
