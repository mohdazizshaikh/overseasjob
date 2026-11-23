<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(403);exit(1);}
require dirname(__DIR__).'/src/Backups.php';
try{
    $items=Backups::create(dirname(__DIR__).'/storage');
    echo json_encode(['local_backup_verified'=>true,'files'=>array_column($items,'source'),
        'hosting_backup_encryption_still_unverified'=>true,
        'only_private_storage_file_hash_copies'=>true,
        'original_hostinger_site_and_mysql_included'=>false,
        'offsite_encrypted_restore_verified'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,'Local backup FAILED: '.$e->getMessage()."\n");exit(1);}
