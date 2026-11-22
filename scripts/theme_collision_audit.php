<?php
declare(strict_types=1);
// This is READ ONLY; no copying, deletion, deployment, DNS or file modification.
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/src/ThemeSafety.php';
try{
    if(count($argv)!==3)throw new InvalidArgumentException('Usage: php scripts/theme_collision_audit.php /path/to/current-static-backup /path/to/new-project/public');
    $result=ThemeSafety::inspect($argv[1],$argv[2]);
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit(2); // No read-only comparison can authorise overwriting a live site.
}catch(Throwable $e){
    fwrite(STDERR,"Theme audit could not complete safely: ".$e->getMessage()."\n");exit(2);
}
