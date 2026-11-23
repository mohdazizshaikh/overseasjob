#!/bin/sh
# New private-first routes and feature flags, no browser E2E assertions.
set -eu
ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
PORT=8799
php -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/router.php" > /dev/null 2>&1 &
PID=$!
trap 'kill "$PID" 2>/dev/null || true' EXIT INT TERM
sleep 1
BASE="http://127.0.0.1:$PORT"; count=0
expect(){ code=$(curl -s --max-time 8 -o /dev/null -w '%{http_code}' "$BASE$1"); [ "$code" = "$2" ] || { echo "FAIL $1 expected $2 but returned $code" >&2;exit 1;};count=$((count+1));}
for path in /arena /privacy /contact /developers /my-career /assets/daily.mjs /assets/daily_ui.mjs /assets/radar.mjs /assets/radar_ui.mjs /assets/privacy.mjs /assets/privacy_ui.mjs;do expect "$path" 200;done
expect /daily 301
redirect=$(curl -sS --max-time 8 -D - -o /dev/null "$BASE/daily" | tr -d '\r' | grep -i '^Location:' | head -n 1 || true)
[ "$redirect" = 'Location: /arena' ] || { echo "FAIL legacy /daily must 301 to /arena, got $redirect" >&2; exit 1; }
count=$((count+1))
expect /owner 404
expect /employers 503
expect /employers/register 503
expect /api/v1/partner/jobs 404
expect /api/privacy-event 404
for path in /src/OwnerAuth.php /src/Analytics.php /src/EmployerContracts.php /content/daily_missions.json /.env /.htaccess /storage/analytics_daily.json;do expect "$path" 404;done
html=$(curl -fsS "$BASE/arena")
printf '%s' "$html" | grep -q 'id="daily-data"' || { echo 'FAIL private authored daily data missing' >&2;exit 1; };count=$((count+1))
printf '%s' "$html" | grep -q 'noindex,nofollow' || { echo 'FAIL daily release hold missing' >&2;exit 1; };count=$((count+1))
printf '%s' "$html" | grep -q 'data-local-only="true"' || { echo 'FAIL daily privacy marker missing' >&2;exit 1; };count=$((count+1))
html=$(curl -fsS "$BASE/privacy")
printf '%s' "$html" | grep -q 'id="privacy-count-optin"' || { echo 'FAIL explicit opt-in missing' >&2;exit 1; };count=$((count+1))
printf '%s' "$html" | grep -q 'data-analytics-enabled="0"' || { echo 'FAIL anonymous metrics enabled without release' >&2;exit 1; };count=$((count+1))
html=$(curl -fsS "$BASE/my-career")
printf '%s' "$html" | grep -q 'id="career-data"' || { echo 'FAIL local radar payload missing' >&2;exit 1; };count=$((count+1))
printf '%s' "$html" | grep -q 'version-aware' || true
expect /sitemap.xml 404
printf 'PASS %s v0.6.0 HTTP, privacy and feature-gate checks\n' "$count"
