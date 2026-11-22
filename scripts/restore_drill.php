<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(403);exit(1);}
require dirname(__DIR__).'/src/Backups.php';
try{
    $verified=Backups::isolatedRestoreDrill(dirname(__DIR__).'/storage');
    echo json_encode(['isolated_restore_drill'=>true,'verified_files'=>$verified,
        'production_host_disaster_recovery_proven'=>false,
        'note'=>'Hashes were verified by restoring copied files to an isolated local temporary directory; live production DB/host restore was NOT tested.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,'Isolated restore drill FAILED: '.$e->getMessage()."\n");exit(1);}
