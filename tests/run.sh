#!/bin/sh
set -eu
ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
find "$ROOT/public" "$ROOT/src" "$ROOT/scripts" -type f -name '*.php' -print | while IFS= read -r file; do php -l "$file" | grep -q 'No syntax errors'; done
php "$ROOT/tests/php_tests.php"
php "$ROOT/tests/operational_tests.php"
php "$ROOT/tests/backup_tests.php"
php "$ROOT/tests/shared_host_layout_tests.php"
php "$ROOT/tests/analytics_tests.php"
php "$ROOT/tests/owner_auth_tests.php"
php "$ROOT/tests/employer_contract_tests.php"
node "$ROOT/tests/js_tests.mjs"
node "$ROOT/tests/tracker_tests.mjs"
node "$ROOT/tests/new_tools_tests.mjs"
node "$ROOT/tests/advanced_tools_tests.mjs"
node "$ROOT/tests/daily_radar_privacy_tests.mjs"
sh "$ROOT/tests/http_smoke.sh"
sh "$ROOT/tests/http_v060_smoke.sh"

php "$ROOT/scripts/gate0_preflight.php" | php -r '$d=json_decode(stream_get_contents(STDIN),true);if(!is_array($d)||!($d["runtime"]["php_8_2_plus"]??false)||($d["external_hosting_plan_verified"]??true)){fwrite(STDERR,"FAIL local preflight truth contract\n");exit(1);}echo "PASS local Gate0 environment preflight truth check\n";'
php "$ROOT/scripts/coverage_report.php" | php -r '$d=json_decode(stream_get_contents(STDIN),true);if(!is_array($d)||!array_key_exists("14_distinct_calendar_days_measured",$d)){fwrite(STDERR,"FAIL observed coverage report\n");exit(1);}echo "PASS observed coverage metadata (no fabricated days)\n";'
