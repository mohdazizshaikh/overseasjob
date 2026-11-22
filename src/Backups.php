<?php
declare(strict_types=1);
/** Local storage backup and isolated-restore verification (NOT a production disaster-recovery certification).
 * Backup directories MUST remain outside the public document root. Host-level encryption is still a release gate.
 */
final class Backups
{
    private const SOURCES=['jobs.json','coverage.ndjson','reports.json','quarantine.json','moderation_audit.ndjson','report_rate.json','analytics_daily.json','owner_rate.json'];
    public static function create(string $storage):array
    {
        if (!is_dir($storage) || !is_writable($storage))throw new RuntimeException('Writable private storage required');
        $dir=$storage.'/backups';
        if (!is_dir($dir) && !mkdir($dir,0700,true))throw new RuntimeException('Cannot create private backup directory');
        chmod($dir,0700);
        $records=[];
        foreach(self::SOURCES as $name){
            $original=$storage.'/'.$name;
            if(!is_file($original))continue;
            if(is_link($original))throw new RuntimeException('Refuse symlink backup: '.$name);
            $id=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
            $destination=$dir.'/'.$name.'.'.$id.'.bak';
            $tmp=tempnam($dir,'creating-');
            if($tmp===false)throw new RuntimeException('Backup cannot allocate private temporary file');
            try {
                if(!copy($original,$tmp))throw new RuntimeException('Backup copy failed');
                chmod($tmp,0600);
                $hash=hash_file('sha256',$original);
                if($hash===false||hash_file('sha256',$tmp)!==$hash)throw new RuntimeException('Backup hash mismatch');
                if(!rename($tmp,$destination))throw new RuntimeException('Cannot publish backup');
                $meta=['source'=>$name,'file'=>basename($destination),'sha256'=>$hash,'created_at'=>gmdate('c')];
                $metadata=$destination.'.json';
                if(file_put_contents($metadata,json_encode($meta,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)
                    throw new RuntimeException('Failed to record backup hash');
                chmod($metadata,0600);
                $records[]=$meta;
            } finally {if(is_file($tmp))unlink($tmp);}
            $saved=glob($dir.'/'.$name.'.*.bak')?:[];
            rsort($saved,SORT_STRING);
            foreach(array_slice($saved,2) as $old){unlink($old);if(is_file($old.'.json'))unlink($old.'.json');}
        }
        if(!$records)throw new RuntimeException('No files to back up');
        return $records;
    }
    public static function isolatedRestoreDrill(string $storage):array
    {
        $dir=$storage.'/backups';if(!is_dir($dir))throw new RuntimeException('No private backups available');
        $testBase=sys_get_temp_dir().'/oj-restore-'.bin2hex(random_bytes(8));
        if(!mkdir($testBase,0700))throw new RuntimeException('Cannot allocate isolated restore workspace');
        $verified=[];
        try {
            foreach(self::SOURCES as $source){
                $candidates=glob($dir.'/'.$source.'.*.bak')?:[];
                rsort($candidates,SORT_STRING);
                if(!$candidates)continue;
                $candidate=$candidates[0];$metafile=$candidate.'.json';
                if(is_link($candidate)||!is_file($metafile))throw new RuntimeException('Backup hash metadata absent');
                $meta=json_decode((string)file_get_contents($metafile),true);
                if(!is_array($meta)||($meta['source']??'')!==$source||($meta['file']??'')!==basename($candidate)||
                  !preg_match('/^[a-f0-9]{64}$/',(string)($meta['sha256']??'')))throw new RuntimeException('Backup metadata invalid');
                $restored=$testBase.'/'.$source;
                if(!copy($candidate,$restored))throw new RuntimeException('Cannot restore in isolated workspace');
                if(hash_file('sha256',$candidate)!==$meta['sha256']||hash_file('sha256',$restored)!==$meta['sha256'])
                    throw new RuntimeException('Backup integrity validation failed: '.$source);
                $verified[]=$source;
            }
            if(!$verified)throw new RuntimeException('No backups to drill');
            return $verified;
        }finally{
            foreach(glob($testBase.'/*')?:[] as $f)unlink($f);
            rmdir($testBase);
        }
    }
}
