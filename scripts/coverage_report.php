<?php
/** M17: contract-validated live provider observations only; never infer actual upstream truth from local rows. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
require dirname(__DIR__).'/src/SourceCoverage.php';
$report=SourceCoverage::summarize(dirname(__DIR__).'/storage/coverage.ndjson',time());
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(!$report['log_valid'])exit(2); // Refuse to treat a corrupted private log as a sound sample.
