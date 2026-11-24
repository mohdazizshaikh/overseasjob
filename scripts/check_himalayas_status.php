<?php
declare(strict_types=1);
/**
 * Historical compatibility CLI — permanently disabled.
 * Founder v1.4.1 contract: Himalayas publishes browse/search, not per-job
 * status or a 200/410 closure protocol. Never run invented per-job probes,
 * including with fixtures masquerading as upstream lifecycle evidence.
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit(2);}
fwrite(STDERR,"Undocumented Himalayas per-job status probe disabled (including simulated fixtures). Use documented, authorised browse, expiry and freshness instead.\n");
exit(3);
