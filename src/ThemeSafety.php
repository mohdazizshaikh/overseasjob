<?php
declare(strict_types=1);
/** Read-only collision checker for an existing static theme and a separate PHP app. */
final class ThemeSafety
{
    /** @return array<string,string> keyed by case-sensitive relative path */
    private static function catalogue(string $directory):array
    {
        $base=realpath($directory);
        if($base===false||!is_dir($base))throw new InvalidArgumentException('A readable directory is required');
        $files=[];$bytes=0;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){
            if($file->isLink())throw new RuntimeException('Symbolic links are not supported in theme safety checks');
            if(!$file->isFile())continue;
            $path=$file->getPathname();
            $relative=substr($path,strlen($base)+1);
            if(strlen($relative)>400||str_contains($relative,"\0"))throw new RuntimeException('Unsafe path in theme');
            if(count($files)>=2500||$bytes+$file->getSize()>200_000_000)throw new RuntimeException('Theme audit size limit exceeded');
            $bytes+=$file->getSize();$digest=hash_file('sha256',$path);
            if($digest===false)throw new RuntimeException('Cannot inspect a file');
            $files[str_replace(DIRECTORY_SEPARATOR,'/',$relative)]=$digest;
        }
        ksort($files,SORT_STRING);
        return $files;
    }
    public static function inspect(string $liveStaticRoot,string $newPublicRoot):array
    {
        $live=realpath($liveStaticRoot);$candidate=realpath($newPublicRoot);
        if($live===false||$candidate===false||!is_dir($live)||!is_dir($candidate))
            throw new InvalidArgumentException('Both current site backup and new public directory are required');
        if($live===$candidate||str_starts_with($live,$candidate.DIRECTORY_SEPARATOR)
          ||str_starts_with($candidate,$live.DIRECTORY_SEPARATOR))
            throw new InvalidArgumentException('Audit roots must be independent; never point at a live webroot');
        $existing=self::catalogue($live);$proposed=self::catalogue($candidate);
        if(!isset($existing['index.html'])&&!isset($existing['index.php']))
            throw new InvalidArgumentException('Current static site backup must contain a recognised entrypoint');
        if(!isset($proposed['index.php']))throw new InvalidArgumentException('Candidate PHP frontend missing');
        $same=[];$changed=[];
        foreach($proposed as $path=>$hash){
            if(!isset($existing[$path]))continue;
            if(hash_equals($existing[$path],$hash))$same[]=$path;
            else $changed[]=$path;
        }
        $themeEntrypointConflict=isset($existing['index.html'])&&isset($proposed['index.php']);
        return [
            'checked'=>'read_only','live_files'=>count($existing),'candidate_files'=>count($proposed),
            'same_path_identical'=>count($same),'same_path_different'=>count($changed),
            'paths_that_would_change'=>array_slice($changed,0,40),
            'html_vs_php_entrypoint_conflict'=>$themeEntrypointConflict,
            'safe_to_replace_current_theme'=>false,
            'reason'=>'Existing live theme has not been migrated or visually accepted; new app must use isolated staging.',
            'next_step'=>'Use a separate staging host and review current-theme screenshots and mobile routes; do not overwrite public_html.'
        ];
    }
}
