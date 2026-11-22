<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/src/OpsSnapshot.php';
try{echo json_encode(OpsSnapshot::collect(dirname(__DIR__),time()),
    JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
catch(Throwable){fwrite(STDERR,"Private operational snapshot unavailable.\n");exit(2);}
