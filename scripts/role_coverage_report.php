<?php
/** M17: read-only offline cohort report. Never contact sources, rewrite jobs or certify licensing. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require dirname(__DIR__).'/src/RoleCoverage.php';
$report=RoleCoverage::summarize(dirname(__DIR__).'/storage/role_coverage.ndjson',time());
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(!$report['log_valid'])exit(2);
