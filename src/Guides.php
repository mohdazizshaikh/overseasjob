<?php
declare(strict_types=1);
require_once __DIR__.'/Jobs.php';
/** Read-only original editorial content; all fields escaped by the renderer. */
final class Guides
{
    public static function all(string $path): array
    {
        if (!is_file($path)) return [];
        $raw=file_get_contents($path);
        if($raw===false||strlen($raw)>250000) return [];
        $data=json_decode($raw,true);
        if(!is_array($data)) return [];
        $out=[];
        foreach($data as $guide){
            if(!self::valid($guide)) continue;
            $out[$guide['slug']]=$guide;
        }
        return $out;
    }
    public static function valid(mixed $g):bool
    {
        if(!is_array($g)||!preg_match('/^[a-z0-9-]{6,80}$/',(string)($g['slug']??'')))return false;
        if(!is_string($g['title']??null)||trim($g['title'])==='')return false;
        if(!is_string($g['summary']??null)||trim($g['summary'])==='')return false;
        if(!in_array($g['editorial_status']??null,['draft_requires_editorial_review','reviewed'],true))return false;
        if(($g['editorial_status']==='reviewed')&&(!is_string($g['reviewed_at']??null)||strtotime($g['reviewed_at'])===false))return false;
        if(!is_array($g['sections']??null)||count($g['sections'])<2)return false;
        if(!is_array($g['sources']??null)||count($g['sources'])<1)return false;
        foreach($g['sections'] as $s)if(!is_string($s['heading']??null)||!is_array($s['paragraphs']??null)||!is_array($s['checklist']??null)||count($s['paragraphs'])===0)return false;
        foreach($g['sources'] as $s)if(!is_string($s['title']??null)||Jobs::safeUrl($s['url']??null)===null)return false;
        $related=$g['related_tool']??'';
        if(!is_string($related)||!preg_match('#^/(?:tools/[a-z0-9-]+|report-job)$#',$related))return false;
        return true;
    }
}
