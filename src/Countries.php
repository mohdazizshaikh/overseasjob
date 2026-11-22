<?php
declare(strict_types=1);
/** ISO 3166-1 exact country-name/code resolver. Unknown values are never interpreted as exclusions. */
final class Countries
{
    public static function alpha2(mixed $input): ?string
    {
        static $aliases=null;
        if (!is_string($input) || trim($input)==='' || strlen($input)>140) return null;
        if ($aliases===null) {
            $map=json_decode((string)file_get_contents(__DIR__.'/country_aliases.json'),true);
            if (!is_array($map)) throw new RuntimeException('Missing ISO country lookup data');
            $aliases=$map;
        }
        $name=trim(preg_replace('/\s+/u',' ',str_replace('_',' ',trim($input))) ?? $input);
        $key=function_exists('mb_strtolower') ? mb_strtolower($name,'UTF-8') : strtolower($name);
        return $aliases[$key]??null;
    }
    /** Country object may include both code and name. If inconsistent, refuse to decide. */
    public static function fromProviderValue(mixed $value): ?string
    {
        if (is_string($value)) return self::alpha2($value);
        if (!is_array($value)) return null;
        $byCode=isset($value['alpha2']) ? self::alpha2($value['alpha2']) : null;
        $byName=isset($value['name']) ? self::alpha2($value['name']) : null;
        if (array_key_exists('alpha2',$value) && $byCode===null) return null;
        if (array_key_exists('name',$value) && $byName===null) return null;
        if ($byCode && $byName && $byCode!==$byName) return null;
        return $byCode??$byName;
    }
}
