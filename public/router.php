<?php
/** Local development router — ONLY permit explicit assets under public/assets.
 * Apache production should use public/.htaccess and document root public/; this is not a production front controller.
 */
declare(strict_types=1);
$requested=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
$public=realpath(__DIR__);
if(str_contains($requested,"\0") || str_contains($requested,'..') || preg_match('~(?:^|/)\.[^/]*~',$requested)) {
    http_response_code(404);exit('Not found');
}
if(preg_match('~^/assets/[A-Za-z0-9_/-]+\.(?:js|mjs|css|png|jpg|jpeg|webp|ico|svg)$~i',$requested)) {
    $full=realpath(__DIR__.$requested);
    if($full!==false && str_starts_with($full,$public.DIRECTORY_SEPARATOR) && is_file($full))return false;
}
require __DIR__.'/index.php';
