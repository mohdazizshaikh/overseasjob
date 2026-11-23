#!/bin/sh
set -eu
ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
find "$ROOT/public" "$ROOT/src" "$ROOT/scripts" -type f -name '*.php' -print | while IFS= read -r file; do php -l "$file" | grep -q 'No syntax errors'; done
php "$ROOT/tests/php_tests.php"
php "$ROOT/tests/provider_http_tests.php"
php "$ROOT/tests/source_permission_gate_tests.php"
php "$ROOT/tests/module_register_tests.php"
php "$ROOT/tests/operational_tests.php"
php "$ROOT/tests/launch_release_gate_tests.php"
php "$ROOT/tests/backup_tests.php"
php "$ROOT/tests/shared_host_layout_tests.php"
php "$ROOT/tests/analytics_tests.php"
php "$ROOT/tests/owner_auth_tests.php"
php "$ROOT/tests/employer_contract_tests.php"
php "$ROOT/tests/partner_api_tests.php"
php "$ROOT/tests/employer_review_portal_tests.php"
php "$ROOT/tests/recruiter_recovery_tests.php"
php "$ROOT/tests/ops_snapshot_tests.php"
php "$ROOT/tests/source_coverage_tests.php"
php "$ROOT/tests/role_coverage_tests.php"
node "$ROOT/tests/js_tests.mjs"
node "$ROOT/tests/tracker_tests.mjs"
node "$ROOT/tests/new_tools_tests.mjs"
node "$ROOT/tests/advanced_tools_tests.mjs"
node "$ROOT/tests/daily_radar_privacy_tests.mjs"
sh "$ROOT/tests/http_smoke.sh"
sh "$ROOT/tests/http_v060_smoke.sh"
sh "$ROOT/tests/http_partner_pilot_smoke.sh"
sh "$ROOT/tests/http_employer_review_smoke.sh"

php "$ROOT/scripts/gate0_preflight.php" | php -r '$d=json_decode(stream_get_contents(STDIN),true);if(!is_array($d)||!($d["runtime"]["php_8_2_plus"]??false)||($d["external_hosting_plan_verified"]??true)){fwrite(STDERR,"FAIL local preflight truth contract\n");exit(1);}echo "PASS local Gate0 environment preflight truth check\n";'
php "$ROOT/scripts/coverage_report.php" | php -r '$d=json_decode(stream_get_contents(STDIN),true);if(!is_array($d)||!array_key_exists("14_distinct_calendar_days_measured",$d)){fwrite(STDERR,"FAIL observed coverage report\n");exit(1);}echo "PASS observed coverage metadata (no fabricated days)\n";'
php "$ROOT/scripts/role_coverage_report.php" | php -r '$d=json_decode(stream_get_contents(STDIN),true);if(!is_array($d)||!array_key_exists("14_days_for_2_to_3_role_cohorts_shape_valid",$d)){fwrite(STDERR,"FAIL role-cohort evidence report\n");exit(1);}echo "PASS role-cohort evidence metadata (no fabricated 14 days)\n";'

php "$ROOT/scripts/verify_release_manifest.php"
